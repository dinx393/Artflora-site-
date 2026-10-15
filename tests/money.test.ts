import { describe, expect, it } from "vitest";
import { formatByn, parseByn, percentOff } from "@/lib/money";

describe("деньги", () => {
  it("показывает целые рубли без копеек", () => expect(formatByn(13000)).toBe("130 BYN"));
  it("показывает копейки через запятую", () => expect(formatByn(17250)).toBe("172,50 BYN"));
  it("разделяет тысячи", () => expect(formatByn(125000)).toBe("1 250 BYN"));
  it("минус для скидки", () => expect(formatByn(-1300)).toBe("−13 BYN"));
  it("читает ввод с запятой и точкой", () => {
    expect(parseByn("10,5")).toBe(1050);
    expect(parseByn("6.50")).toBe(650);
    expect(parseByn("abc")).toBeNull();
  });
  it("считает процент", () => expect(percentOff(13000, 10)).toBe(1300));
});
