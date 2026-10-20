// Расчёт доставки. Чистые функции, без базы и сети, чтобы их легко проверять тестами.
//
// Правила (из макетов):
//  - везём из ближайшего магазина;
//  - зоны по расстоянию от магазина: 6 / 10 / 14 / 18 BYN;
//  - за МКАД: последняя зона + 1 BYN за каждый км от МКАД;
//  - бесплатно от суммы freeFromKop (внутри МКАД; за МКАД доплата за км остаётся);
//  - самовывоз: скидка pickupDiscountPct на букеты;
//  - свои участки (нарисованы в админке на карте) важнее кругов: там своя цена или «не возим».

export type LatLng = { lat: number; lng: number };

export type Shop = {
  code: string;
  name: string; // «Уручье»
  address: string; // «пр. Независимости, 168/1»
  hours: string;
  location: LatLng;
};

export type DeliveryZone = { maxKm: number; priceKop: number; color?: string };

// Участок, нарисованный владельцем на карте: например, «Смолевичи — 35 BYN» или «Радошковичи — не возим».
export type DeliveryArea = { name: string; color: string; priceKop: number; noDelivery: boolean; points: LatLng[] };

export type DeliveryConfig = {
  shops: Shop[];
  zones: DeliveryZone[]; // по возрастанию maxKm, последняя — до МКАД
  mkadCenter: LatLng;
  mkadRadiusKm: number;
  outsideMkadPerKmKop: number;
  freeFromKop: number;
  pickupDiscountPct: number;
  maxOutsideMkadKm: number; // дальше этого за МКАД не возим (владелец: до 30 км от Минска)
  outsideColor?: string; // цвет «за МКАД» на карте в админке
  areas?: DeliveryArea[]; // свои участки; если участки пересекаются, действует верхний в списке
};

// Точка внутри многоугольника (луч вправо, считаем пересечения). Для участков в пару десятков км плоской карты хватает.
export function insidePolygon(p: LatLng, poly: LatLng[]): boolean {
  let inside = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const a = poly[i];
    const b = poly[j];
    if (a.lat > p.lat !== b.lat > p.lat && p.lng < ((b.lng - a.lng) * (p.lat - a.lat)) / (b.lat - a.lat) + a.lng) inside = !inside;
  }
  return inside;
}

export function areaAt(point: LatLng, cfg: Pick<DeliveryConfig, "areas">): DeliveryArea | null {
  return cfg.areas?.find((a) => a.points.length >= 3 && insidePolygon(point, a.points)) ?? null;
}

export function distanceKm(a: LatLng, b: LatLng): number {
  const R = 6371;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const h =
    Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

export function nearestShop(point: LatLng, shops: Shop[]): { shop: Shop; km: number } {
  let best = { shop: shops[0], km: distanceKm(point, shops[0].location) };
  for (const shop of shops.slice(1)) {
    const km = distanceKm(point, shop.location);
    if (km < best.km) best = { shop, km };
  }
  return best;
}

export type DeliveryQuote =
  | {
      ok: true;
      shop: Shop;
      km: number; // до ближайшего магазина
      zone: number; // 1..zones.length, за МКАД — последняя; 0 — свой участок
      outsideMkadKm: number; // 0 внутри МКАД
      baseKop: number; // цена без учёта бесплатной доставки
      priceKop: number; // к оплате
      free: boolean;
      leftToFreeKop: number; // сколько не хватает до бесплатной доставки
      area?: { name: string; color: string }; // точка попала в свой участок
    }
  | { ok: false; reason: "too-far"; km: number; area?: { name: string; color: string } }; // km — сколько за МКАД

export function quoteDelivery(point: LatLng, subtotalKop: number, cfg: DeliveryConfig): DeliveryQuote {
  const { shop, km } = nearestShop(point, cfg.shops);
  const area = areaAt(point, cfg);
  if (area) {
    const tag = { name: area.name, color: area.color };
    if (area.noDelivery) return { ok: false, reason: "too-far", km: 0, area: tag };
    // Цена участка фиксированная; бесплатная доставка от суммы на участки не действует (они обычно за городом).
    return {
      ok: true,
      shop,
      km: Math.round(km * 10) / 10,
      zone: 0,
      outsideMkadKm: 0,
      baseKop: area.priceKop,
      priceKop: area.priceKop,
      free: false,
      leftToFreeKop: 0,
      area: tag,
    };
  }
  const fromCenter = distanceKm(point, cfg.mkadCenter);
  const outsideMkadKm = Math.max(0, Math.ceil(fromCenter - cfg.mkadRadiusKm));
  if (outsideMkadKm > cfg.maxOutsideMkadKm) return { ok: false, reason: "too-far", km: outsideMkadKm };
  const last = cfg.zones.length - 1;

  let zoneIdx = cfg.zones.findIndex((z) => km <= z.maxKm);
  if (zoneIdx === -1 || outsideMkadKm > 0) zoneIdx = last;

  const zoneKop = cfg.zones[zoneIdx].priceKop;
  const extraKop = outsideMkadKm * cfg.outsideMkadPerKmKop;
  const baseKop = zoneKop + extraKop;
  const free = subtotalKop >= cfg.freeFromKop;
  const priceKop = free ? extraKop : baseKop;

  return {
    ok: true,
    shop,
    km: Math.round(km * 10) / 10,
    zone: zoneIdx + 1,
    outsideMkadKm,
    baseKop,
    priceKop,
    free,
    leftToFreeKop: Math.max(0, cfg.freeFromKop - subtotalKop),
  };
}

export function pickupDiscountKop(bouquetsKop: number, cfg: Pick<DeliveryConfig, "pickupDiscountPct">): number {
  return Math.round((bouquetsKop * cfg.pickupDiscountPct) / 100);
}

// Минимальная цена доставки — для надписи «Доставка от 6 BYN».
export function minDeliveryKop(cfg: DeliveryConfig): number {
  return Math.min(...cfg.zones.map((z) => z.priceKop));
}
