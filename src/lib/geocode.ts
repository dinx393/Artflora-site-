import "server-only";
import { groupChoices, normalizeAddress, type AddressChoice, type GeoResult } from "./address";
import type { LatLng } from "./delivery";

// Поиск адреса через OpenStreetMap (Nominatim): Минск и окрестности (примерно 30 км за МКАД).
// Бесплатно, но не больше 1 запроса в секунду. Если заказов станет много,
// лучше перейти на Яндекс Геокодер (нужен ключ).

const cache = new Map<string, { at: number; value: AddressChoice[] }>();
const CACHE_MAX = 5000;

// Запросы к Nominatim идут по одному, не чаще раза в секунду (их правило).
let queue: Promise<unknown> = Promise.resolve();
let lastAt = 0;
function throttled<T>(fn: () => Promise<T>): Promise<T> {
  const run = queue.then(async () => {
    const wait = lastAt + 1100 - Date.now();
    if (wait > 0) await new Promise((r) => setTimeout(r, wait));
    lastAt = Date.now();
    return fn();
  });
  queue = run.catch(() => {});
  return run;
}

// Все подходящие адреса: «Садовая, 5» есть и в Заславле, и в деревнях рядом — покупатель выбирает свой.
export async function findAddresses(address: string): Promise<AddressChoice[] | null> {
  const key = address.trim().toLowerCase();
  if (key.length < 4) return [];
  const hit = cache.get(key);
  if (hit && Date.now() - hit.at < 24 * 3600_000) return hit.value;

  // Если город не написан, ищем и в Минске, и вокруг: «Садовая, 5» в Минске геокодер
  // без подсказки не найдёт — вокруг слишком много деревень с такой улицей.
  const q = normalizeAddress(address);
  const hasPlace = /минск|minsk|район|деревн|посёл|посел|заславл|боровлян|колодищ|ждановичи|сеница|лесной|фаниполь|смолевич|мачулищ|логойск|дзержинск|руденск/i.test(q);
  const found = await Promise.all((hasPlace ? [q] : [`${q}, Минск`, q]).map(search));
  if (found.some((f) => f === null) && found.every((f) => !f?.length)) return null; // геокодер недоступен
  const value = groupChoices(found.flatMap((f) => f ?? []));
  if (cache.size >= CACHE_MAX) cache.delete(cache.keys().next().value!);
  cache.set(key, { at: Date.now(), value });
  return value;
}

async function search(q: string): Promise<GeoResult[] | null> {
  const url = new URL("https://nominatim.openstreetmap.org/search");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("q", q);
  url.searchParams.set("countrycodes", "by");
  url.searchParams.set("viewbox", "26.95,54.25,28.2,53.55");
  url.searchParams.set("bounded", "1");
  url.searchParams.set("limit", "30");
  url.searchParams.set("accept-language", "ru");
  url.searchParams.set("addressdetails", "1");
  try {
    const res = await throttled(() =>
      fetch(url, {
        headers: { "User-Agent": `artflora-site (${process.env.SITE_URL ?? "artflora.by"})` },
        signal: AbortSignal.timeout(5000),
      }),
    );
    if (!res.ok) return null; // null — геокодер недоступен, [] — не нашли
    return (await res.json()) as GeoResult[];
  } catch {
    return null;
  }
}

export type Resolved =
  | { ok: true; choice: AddressChoice }
  | { ok: false; reason: "not-found" | "unavailable" }
  | { ok: false; reason: "choose"; choices: AddressChoice[] };

// Какой адрес имел в виду покупатель. Если вариантов несколько, нужен placeId выбранного —
// сервер сам ищет адрес заново и берёт свой вариант, координатам из браузера не верим.
export async function resolveAddress(address: string, placeId?: number | null): Promise<Resolved> {
  const choices = await findAddresses(address);
  if (choices === null) return { ok: false, reason: "unavailable" };
  if (choices.length === 0) return { ok: false, reason: "not-found" };
  if (choices.length === 1) return { ok: true, choice: choices[0] };
  const picked = placeId ? choices.find((c) => c.placeId === placeId) : undefined;
  return picked ? { ok: true, choice: picked } : { ok: false, reason: "choose", choices };
}

export async function geocode(address: string): Promise<LatLng | null> {
  const r = await resolveAddress(address);
  return r.ok ? r.choice.point : null;
}
