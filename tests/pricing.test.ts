import { describe, expect, it } from "vitest";
import { availableSizes, bouquetVisibility, compositionText, defaultSize, sizeAvailable, sizePriceKop, type PricedBouquet } from "@/lib/pricing";

const rose = { id: 1, name: "Роза кустовая 60 см", priceKop: 1000, inStock: true };
const eustoma = { id: 2, name: "Эустома", priceKop: 1300, inStock: true };
const eucalyptus = { id: 3, name: "Эвкалипт", priceKop: 500, inStock: true };
const peony = { id: 4, name: "Пион", priceKop: 2000, inStock: false };

const bag: PricedBouquet = {
  enabled: true,
  sizes: [
    { code: "M", note: "", packagingKop: 1500, items: [{ qty: 5, flower: rose }, { qty: 3, flower: eustoma }, { qty: 2, flower: eucalyptus }] },
    { code: "L", note: "", packagingKop: 2500, items: [{ qty: 7, flower: rose }, { qty: 1, flower: peony }] },
  ],
};

describe("цена букета", () => {
  it("Σ цветы × количество + упаковка (сумка «rose» из макета = 114 BYN)", () => {
    expect(sizePriceKop(bag.sizes[0])).toBe(11400);
  });
  it("у каждого размера своя упаковка", () => {
    expect(sizePriceKop({ items: [{ qty: 1, flower: rose }], packagingKop: 500 })).toBe(1500);
    expect(sizePriceKop(bag.sizes[1])).toBe(7 * 1000 + 2000 + 2500);
  });
  it("размер с отсутствующим цветком не предлагается", () => {
    expect(defaultSize(bag)?.code).toBe("M");
    expect(bouquetVisibility(bag)).toEqual({ visible: true });
  });
  it("по умолчанию — размер, отмеченный «на фото»; если его не собрать — M", () => {
    const small = { ...bag, sizes: [{ code: "S" as const, note: "", packagingKop: 1000, items: [{ qty: 3, flower: rose }] }, ...bag.sizes] };
    expect(defaultSize({ ...small, photoSize: "S" })?.code).toBe("S");
    expect(defaultSize({ ...small, photoSize: "L" })?.code).toBe("M"); // в L нет пиона
  });
  it("размеры от XS до XXL идут по порядку", () => {
    const one = (code: "XS" | "XXL" | "M") => ({ code, note: "", packagingKop: 0, items: [{ qty: 1, flower: rose }] });
    expect(availableSizes({ sizes: [one("XXL"), one("M"), one("XS")] }).map((s) => s.code)).toEqual(["XS", "M", "XXL"]);
  });
  it("букет скрывается, если ни один размер не собрать", () => {
    const sunny: PricedBouquet = { enabled: true, sizes: [{ code: "M", note: "", packagingKop: 800, items: [{ qty: 7, flower: peony }] }] };
    expect(bouquetVisibility(sunny)).toEqual({ visible: false, reason: "no-flowers", missing: ["Пион"] });
  });
  it("выключенный вручную букет скрыт", () => {
    expect(bouquetVisibility({ ...bag, enabled: false })).toEqual({ visible: false, reason: "disabled" });
  });
});

describe("замены и дробное количество", () => {
  const veronica = { id: 5, name: "Вероника", priceKop: 500, inStock: false, substitutes: [{ name: "Статица", inStock: true }] };
  const lonely = { id: 6, name: "Пион", priceKop: 2000, inStock: false, substitutes: [{ name: "Ранункулюс", inStock: false }] };
  it("нет цветка, но есть замена — размер остаётся, цена прежняя, в составе замена", () => {
    const s = { code: "M" as const, note: "", packagingKop: 0, items: [{ qty: 3, flower: veronica }, { qty: 1, flower: rose }] };
    expect(sizeAvailable(s)).toBe(true);
    expect(sizePriceKop(s)).toBe(2500);
    expect(compositionText(s.items)).toBe("Статица (вместо: вероника) × 3, Роза кустовая 60 см × 1");
  });
  it("замены тоже нет — размер скрыт", () => {
    expect(sizeAvailable({ code: "M", note: "", packagingKop: 0, items: [{ qty: 1, flower: lonely }] })).toBe(false);
  });
  it("1,5 ветки эвкалипта: цена округляется до копейки", () => {
    const euc = { ...eucalyptus, priceKop: 333 };
    expect(sizePriceKop({ packagingKop: 0, items: [{ qty: 1.5, flower: euc }] })).toBe(500);
    expect(compositionText([{ qty: 1.5, flower: euc }])).toBe("Эвкалипт × 1,5");
  });
});
