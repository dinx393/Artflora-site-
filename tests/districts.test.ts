import { describe, expect, it } from "vitest";
import { DEFAULT_DELIVERY, DEFAULT_DISTRICTS } from "@/lib/defaults";
import { districtOptions } from "@/lib/districts";

describe("районы доставки", () => {
  const list = districtOptions(DEFAULT_DISTRICTS, DEFAULT_DELIVERY);

  it("у каждого района своя цена, по алфавиту", () => {
    expect(list.find((d) => d.name === "Уручье")?.priceKop).toBe(600);
    expect(list.find((d) => d.name === "Боровляны")!.priceKop).toBeGreaterThan(1800);
    expect(list.map((d) => d.name)).toEqual([...list.map((d) => d.name)].sort((a, b) => a.localeCompare(b, "ru")));
  });

  it("слишком далёкие районы не показываем", () => {
    const far = districtOptions([{ name: "Борисов", lat: 54.2279, lng: 28.505 }], DEFAULT_DELIVERY);
    expect(far).toEqual([]);
  });
});
