import { describe, expect, it } from "vitest";
import { DEFAULT_DELIVERY, DEFAULT_DISTRICTS } from "@/lib/defaults";
import { pickupDiscountKop, quoteDelivery } from "@/lib/delivery";

const at = (name: string) => {
  const d = DEFAULT_DISTRICTS.find((x) => x.name === name)!;
  return { lat: d.lat, lng: d.lng };
};

describe("доставка", () => {
  it("возим до 30 км за МКАД, дальше — нет", () => {
    // Борисов ~ 60 км за МКАД, Смолевичи ~ 26 км
    expect(quoteDelivery({ lat: 54.2279, lng: 28.505 }, 13000, DEFAULT_DELIVERY).ok).toBe(false);
    expect(quoteDelivery({ lat: 54.0285, lng: 28.0886 }, 13000, DEFAULT_DELIVERY).ok).toBe(true);
  });
  it("рядом с магазином — первая зона, 6 BYN", () => {
    const q = quoteDelivery(at("Уручье"), 13000, DEFAULT_DELIVERY);
    expect(q.ok && q.zone).toBe(1);
    expect(q.ok && q.priceKop).toBe(600);
    expect(q.ok && q.shop.code).toBe("uruchye");
  });
  it("Малиновку везём из магазина на Чечота", () => {
    const q = quoteDelivery(at("Малиновка"), 13000, DEFAULT_DELIVERY);
    expect(q.ok && q.shop.code).toBe("malinovka");
  });
  it("центр — третья зона, 14 BYN (как в макете)", () => {
    const q = quoteDelivery(at("Центр"), 13000, DEFAULT_DELIVERY);
    expect(q.ok && q.priceKop).toBe(1400);
    expect(q.ok && q.leftToFreeKop).toBe(7000);
  });
  it("за МКАД: 18 BYN + 1 BYN за км", () => {
    const q = quoteDelivery(at("Заславль"), 13000, DEFAULT_DELIVERY);
    expect(q.ok).toBe(true);
    if (q.ok) {
      expect(q.outsideMkadKm).toBeGreaterThan(0);
      expect(q.priceKop).toBe(1800 + q.outsideMkadKm * 100);
    }
  });
  it("бесплатно от 200 BYN внутри МКАД", () => {
    const q = quoteDelivery(at("Центр"), 20000, DEFAULT_DELIVERY);
    expect(q.ok && q.priceKop).toBe(0);
  });
  it("за МКАД при бесплатной доставке остаётся доплата за км", () => {
    const q = quoteDelivery(at("Заславль"), 25000, DEFAULT_DELIVERY);
    expect(q.ok && q.priceKop).toBe(q.ok ? q.outsideMkadKm * 100 : -1);
  });
  it("слишком далеко — отказ", () => {
    const q = quoteDelivery({ lat: 53.13, lng: 26.01 }, 13000, DEFAULT_DELIVERY); // Барановичи
    expect(q.ok).toBe(false);
  });
  it("самовывоз −10% на букеты", () => expect(pickupDiscountKop(13000, DEFAULT_DELIVERY)).toBe(1300));
});
