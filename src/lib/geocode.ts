import "server-only";
import { normalizeAddress } from "./address";
import type { LatLng } from "./delivery";

// Поиск координат адреса через OpenStreetMap (Nominatim), только Минск и район.
// Бесплатно, но не больше 1 запроса в секунду. Если заказов станет много,
// лучше перейти на Яндекс Геокодер (нужен ключ).
const cache = new Map<string, { at: number; value: LatLng | null }>();

export async function geocode(address: string): Promise<LatLng | null> {
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

  try {
    const res = await fetch(url, {
      headers: { "User-Agent": `artflora-site (${process.env.SITE_URL ?? "artflora.by"})` },
      signal: AbortSignal.timeout(5000),
    });
    if (!res.ok) return null;
    const data = (await res.json()) as { lat: string; lon: string; place_rank?: number }[];
    // Город или район целиком (place_rank < 26) — это не адрес: цена была бы случайной.
    const hit = data.find((d) => (d.place_rank ?? 30) >= 26);
    const value = hit ? { lat: parseFloat(hit.lat), lng: parseFloat(hit.lon) } : null;
    cache.set(key, { at: Date.now(), value });
    return value;
  } catch {
    return null;
  }
}
