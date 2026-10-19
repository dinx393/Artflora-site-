import "server-only";
import { normalizeAddress } from "./address";
import type { LatLng } from "./delivery";

// Поиск координат адреса через OpenStreetMap (Nominatim), только Минск и район.
// Бесплатно, но не больше 1 запроса в секунду. Если заказов станет много,
// лучше перейти на Яндекс Геокодер (нужен ключ).

// Что нашли по адресу: точка для расчёта и подпись, чтобы покупатель видел, какой адрес мы поняли.
export type Found = { point: LatLng; label: string; area: string };

const cache = new Map<string, { at: number; value: Found | null }>();
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

export async function geocode(address: string): Promise<LatLng | null> {
  return (await geocodeDetailed(address))?.point ?? null;
}

type NominatimAddress = Record<string, string | undefined>;

function areaOf(a: NominatimAddress): string {
  // «Первомайский район, Минск» или «Боровляны» для пригорода
  const district = a.city_district ?? a.suburb ?? "";
  const city = a.city ?? a.town ?? a.village ?? a.hamlet ?? "";
  return [district, city].filter(Boolean).join(", ");
}

function labelOf(a: NominatimAddress): string {
  return [a.road ?? a.pedestrian ?? a.neighbourhood ?? "", a.house_number ?? ""].filter(Boolean).join(", ");
}

export async function geocodeDetailed(address: string): Promise<Found | null> {
  const key = address.trim().toLowerCase();
  if (key.length < 4) return null;
  const hit = cache.get(key);
  if (hit && Date.now() - hit.at < 24 * 3600_000) return hit.value;

  const full = normalizeAddress(address);
  const q = /минск|minsk|заславл|боровлян|колодищ|ждановичи|сеница|лесной/i.test(full)
    ? full
    : `${full}, Минск`;
  const url = new URL("https://nominatim.openstreetmap.org/search");
  url.searchParams.set("format", "jsonv2");
  url.searchParams.set("q", q);
  url.searchParams.set("countrycodes", "by");
  url.searchParams.set("viewbox", "27.1,54.15,28.0,53.7");
  url.searchParams.set("bounded", "1");
  url.searchParams.set("limit", "3");
  url.searchParams.set("accept-language", "ru");
  url.searchParams.set("addressdetails", "1");

  try {
    const res = await throttled(() =>
      fetch(url, {
        headers: { "User-Agent": `artflora-site (${process.env.SITE_URL ?? "artflora.by"})` },
        signal: AbortSignal.timeout(5000),
      }),
    );
    if (!res.ok) return null;
    const data = (await res.json()) as { lat: string; lon: string; place_rank?: number; address?: NominatimAddress }[];
    // Город или район целиком (place_rank < 26) — это не адрес: цена была бы случайной.
    // Сначала ищем точный дом, если его нет на карте — улицу.
    const hit = data.find((d) => d.address?.house_number) ?? data.find((d) => (d.place_rank ?? 30) >= 26);
    const value = hit
      ? {
          point: { lat: parseFloat(hit.lat), lng: parseFloat(hit.lon) },
          label: labelOf(hit.address ?? {}),
          area: areaOf(hit.address ?? {}),
        }
      : null;
    if (cache.size >= CACHE_MAX) cache.delete(cache.keys().next().value!);
    cache.set(key, { at: Date.now(), value });
    return value;
  } catch {
    return null;
  }
}
