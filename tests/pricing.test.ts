import { describe, expect, it } from "vitest";
import { availableSizes, bouquetVisibility, defaultSize, sizePriceKop, type PricedBouquet } from "@/lib/pricing";

const rose = { id: 1, name: "Роза кустовая 60 см", priceKop: 1000, inStock: true };
const eustoma = { id: 2, name: "Эустома", priceKop: 1300, inStock: true };
const eucalyptus = { id: 3, name: "Эвкалипт", priceKop: 500, inStock: true };
const peony = { id: 4, name: "Пион", priceKop: 2000, inStock: false };

const bag: PricedBouquet = {
  enabled: true,
  packagingKop: 1500,
  sizes: [
    { code: "M", note: "", items: [{ qty: 5, flower: rose }, { qty: 3, flower: eustoma }, { qty: 2, flower: eucalyptus }] },
    { code: "L", note: "", items: [{ qty: 7, flower: rose }, { qty: 1, flower: peony }] },
  ],
};

describe("цена букета", () => {
  it("Σ цветы × количество + упаковка (сумка «rose» из макета = 114 BYN)", () => {
    expect(sizePriceKop(bag.sizes[0], bag.packagingKop)).toBe(11400);
  });
  it("размер с отсутствующим цветком не предлагается", () => {
    expect(defaultSize(bag)?.code).toBe("M");
    expect(bouquetVisibility(bag)).toEqual({ visible: true });
  });
  it("по умолчанию — размер, отмеченный «на фото»; если его не собрать — M", () => {
    const small = { ...bag, sizes: [{ code: "S" as const, note: "", items: [{ qty: 3, flower: rose }] }, ...bag.sizes] };
    expect(defaultSize({ ...small, photoSize: "S" })?.code).toBe("S");
    expect(defaultSize({ ...small, photoSize: "L" })?.code).toBe("M"); // в L нет пиона
  });
  it("размеры от XS до XXL идут по порядку", () => {
    const one = (code: "XS" | "XXL" | "M") => ({ code, note: "", items: [{ qty: 1, flower: rose }] });
    expect(availableSizes({ sizes: [one("XXL"), one("M"), one("XS")] }).map((s) => s.code)).toEqual(["XS", "M", "XXL"]);
  });
  it("букет скрывается, если ни один размер не собрать", () => {
    const sunny: PricedBouquet = { enabled: true, packagingKop: 800, sizes: [{ code: "M", note: "", items: [{ qty: 7, flower: peony }] }] };
    expect(bouquetVisibility(sunny)).toEqual({ visible: false, reason: "no-flowers", missing: ["Пион"] });
  });
  it("выключенный вручную букет скрыт", () => {
    expect(bouquetVisibility({ ...bag, enabled: false })).toEqual({ visible: false, reason: "disabled" });
  });
});
