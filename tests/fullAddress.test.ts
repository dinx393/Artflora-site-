import { describe, expect, it } from "vitest";
import { fullAddress } from "@/lib/address";

describe("адрес для курьера", () => {
  it("складывает улицу, квартиру, подъезд и этаж", () => {
    expect(fullAddress({ address: "ул. Немига, 5", apartment: "12", entrance: "2", floor: "4" })).toBe("ул. Немига, 5, кв. 12, подъезд 2, этаж 4");
  });
  it("пропускает пустые поля", () => {
    expect(fullAddress({ address: "пр. Независимости 180 ", apartment: "", floor: " " })).toBe("пр. Независимости 180");
  });
});
