import type { DistrictPreset } from "./defaults";
import { quoteDelivery, type DeliveryConfig } from "./delivery";

// Район доставки с ценой для списка в корзине и на странице букета.
// area — район попал в свой участок с фиксированной ценой (бесплатная доставка от суммы там не действует).
export type DistrictOption = { name: string; lat: number; lng: number; zone: number; priceKop: number; shopName: string; area?: string };

// В корзине цена — обычная (subtotalKop = 0), бесплатную доставку от суммы корзина покажет сама.
// На странице букета передаём цену букета, чтобы сразу было видно «бесплатно».
// Районы дальше разрешённого расстояния не показываем.
export function districtOptions(list: DistrictPreset[], cfg: DeliveryConfig, subtotalKop = 0): DistrictOption[] {
  return list
    .flatMap((d) => {
      const q = quoteDelivery(d, subtotalKop, cfg);
      return q.ok ? [{ name: d.name, lat: d.lat, lng: d.lng, zone: q.zone, priceKop: q.priceKop, shopName: q.shop.name, ...(q.area ? { area: q.area.name } : {}) }] : [];
    })
    .sort((a, b) => a.name.localeCompare(b.name, "ru"));
}
