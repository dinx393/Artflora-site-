// Расчёт доставки. Чистые функции, без базы и сети, чтобы их легко проверять тестами.
//
// Правила (из макетов):
//  - везём из ближайшего магазина;
//  - зоны по расстоянию от магазина: 6 / 10 / 14 / 18 BYN;
//  - за МКАД: последняя зона + 1 BYN за каждый км от МКАД;
//  - бесплатно от суммы freeFromKop (внутри МКАД; за МКАД доплата за км остаётся);
//  - самовывоз: скидка pickupDiscountPct на букеты.

export type LatLng = { lat: number; lng: number };

export type Shop = {
  code: string;
  name: string; // «Уручье»
  address: string; // «пр. Независимости, 168/1»
  hours: string;
  location: LatLng;
};

export type DeliveryZone = { maxKm: number; priceKop: number };

export type DeliveryConfig = {
  shops: Shop[];
  zones: DeliveryZone[]; // по возрастанию maxKm, последняя — до МКАД
  mkadCenter: LatLng;
  mkadRadiusKm: number;
  outsideMkadPerKmKop: number;
  freeFromKop: number;
  pickupDiscountPct: number;
  maxOutsideMkadKm: number; // дальше этого за МКАД не возим (владелец: до 30 км от Минска)
};

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
      zone: number; // 1..zones.length, за МКАД — последняя
      outsideMkadKm: number; // 0 внутри МКАД
      baseKop: number; // цена без учёта бесплатной доставки
      priceKop: number; // к оплате
      free: boolean;
      leftToFreeKop: number; // сколько не хватает до бесплатной доставки
    }
  | { ok: false; reason: "too-far"; km: number }; // km — сколько за МКАД

export function quoteDelivery(point: LatLng, subtotalKop: number, cfg: DeliveryConfig): DeliveryQuote {
  const { shop, km } = nearestShop(point, cfg.shops);
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
