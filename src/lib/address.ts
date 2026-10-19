import { distanceKm } from "./delivery";

// Nominatim плохо понимает сокращения: «пр. Независимости 180» он находит
// как центр Минска. Разворачиваем привычные сокращения в полные слова.
const ABBREVIATIONS: [RegExp, string][] = [
  [/(^|[\s,])(пр-т|просп\.?|пр\.?)(?=\s)/gi, "$1проспект"],
  [/(^|[\s,])ул\.?(?=\s)/gi, "$1улица"],
  [/(^|[\s,])пер\.?(?=\s)/gi, "$1переулок"],
  [/(^|[\s,])(б-р|бул\.)(?=\s)/gi, "$1бульвар"],
  [/(^|[\s,])пл\.?(?=\s)/gi, "$1площадь"],
  [/(^|[\s,])(пр-д|прз\.)(?=\s)/gi, "$1проезд"],
  [/(^|[\s,])(д\.|дом)\s*(?=\d)/gi, "$1"],
];

export function normalizeAddress(address: string): string {
  let s = address
    .trim()
    .replace(/\s+/g, " ")
    .replace(/(^|[\s,])(пр|просп|ул|пер|пл|бул)\.(?=[А-ЯЁ])/gi, "$1$2. ");
  for (const [re, to] of ABBREVIATIONS) s = s.replace(re, to);
  return s;
}

// «ул. Немига, 5, кв. 12, подъезд 2, этаж 4» — так адрес видит флорист и курьер.
export function fullAddress(o: { address: string; apartment?: string; entrance?: string; floor?: string; foundAs?: string }): string {
  return [
    o.foundAs ? `${o.foundAs} (ввели: ${o.address.trim()})` : o.address.trim(),
    o.apartment?.trim() && `кв. ${o.apartment.trim()}`,
    o.entrance?.trim() && `подъезд ${o.entrance.trim()}`,
    o.floor?.trim() && `этаж ${o.floor.trim()}`,
  ]
    .filter(Boolean)
    .join(", ");
}

// Ответ геокодера (Nominatim) — только нужные поля.
export type GeoResult = {
  place_id: number;
  lat: string;
  lon: string;
  place_rank?: number;
  display_name: string;
  address?: Record<string, string | undefined>;
};

// Вариант адреса, из которого покупатель выбирает, если таких адресов несколько.
export type AddressChoice = {
  placeId: number;
  point: { lat: number; lng: number };
  label: string; // «улица Немига, 5»
  area: string; // «Центральный район, Минск» или «Гричино, Дзержинский район»
  minsk: boolean;
};

const MINSK_CENTER = { lat: 53.9006, lng: 27.559 };

export function toChoice(r: GeoResult): AddressChoice {
  const a = r.address ?? {};
  const road = a.road ?? a.pedestrian ?? a.neighbourhood ?? "";
  const label = [road, a.house_number ?? ""].filter(Boolean).join(", ");
  const minsk = (a.city ?? "").toLowerCase() === "минск";
  // Населённый пункт: в деревнях OSM кладёт в city название сельсовета — его не показываем, слишком длинно.
  const place = minsk ? "Минск" : (a.town ?? a.village ?? a.hamlet ?? a.allotments ?? a.city ?? a.suburb ?? "");
  const area = (minsk ? [a.city_district, place] : [place, a.county]).filter(Boolean).join(", ");
  return {
    placeId: r.place_id,
    point: { lat: parseFloat(r.lat), lng: parseFloat(r.lon) },
    label,
    area: area || r.display_name.split(",").slice(1, 3).join(",").trim(),
    minsk,
  };
}

// Из ответа геокодера делаем список разных адресов: один дом в OSM бывает многими точками
// (магазины, кафе, подъезды) — такие склеиваем. Минск первым, дальше — ближние к Минску.
// Город или район целиком — не адрес.
export function groupChoices(results: GeoResult[], max = 6): AddressChoice[] {
  const usable = results.filter((r) => (r.place_rank ?? 30) >= 26);
  const withHouse = usable.filter((r) => r.address?.house_number);
  const out: AddressChoice[] = [];
  for (const c of (withHouse.length ? withHouse : usable).map(toChoice)) {
    if (!out.some((o) => o.label === c.label && o.area === c.area)) out.push(c);
  }
  return out
    .sort((x, y) => Number(y.minsk) - Number(x.minsk) || distanceKm(x.point, MINSK_CENTER) - distanceKm(y.point, MINSK_CENTER))
    .slice(0, max);
}
