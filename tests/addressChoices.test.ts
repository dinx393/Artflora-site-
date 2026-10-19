import { describe, expect, it } from "vitest";
import { groupChoices, type GeoResult } from "@/lib/address";

const r = (id: number, display: string, address: Record<string, string>, rank = 30): GeoResult => ({
  place_id: id,
  lat: "53.9",
  lon: "27.5",
  place_rank: rank,
  display_name: display,
  address,
});

describe("похожие адреса", () => {
  it("один дом несколькими точками — один вариант", () => {
    const list = groupChoices([
      r(1, "5, улица Немига, Центральный район, Минск, 220030, Беларусь", { road: "улица Немига", house_number: "5", city_district: "Центральный район", city: "Минск" }),
      r(2, "Лидо, 5, улица Немига, Центральный район, Минск, 220030, Беларусь", { amenity: "Лидо", road: "улица Немига", house_number: "5", city_district: "Центральный район", city: "Минск" }),
    ]);
    expect(list).toHaveLength(1);
    expect(list[0].label).toBe("улица Немига, 5");
    expect(list[0].area).toBe("Центральный район, Минск");
  });

  it("Минск первым, деревня — с сельсоветом", () => {
    const list = groupChoices([
      r(1, "5, Садовая улица, Тарасово, Горанский сельский Совет, Минский район, Минская область, 223050, Беларусь", { road: "Садовая улица", house_number: "5", village: "Тарасово", city: "Горанский сельский Совет", county: "Минский район" }),
      r(2, "5, Садовая улица, Ленинский район, Минск, 220000, Беларусь", { road: "Садовая улица", house_number: "5", city_district: "Ленинский район", city: "Минск" }),
    ]);
    expect(list.map((c) => c.placeId)).toEqual([2, 1]);
    expect(list[1].area).toBe("Тарасово, Минский район");
    expect(list[0].area).toBe("Ленинский район, Минск");
  });

  it("деревни — от ближней к дальней", () => {
    const far = { ...r(1, "", { road: "Садовая улица", house_number: "5", town: "Логойск", county: "Логойский район" }), lat: "54.2", lon: "27.85" };
    const near = { ...r(2, "", { road: "Садовая улица", house_number: "5", town: "Боровляны", county: "Минский район" }), lat: "53.96", lon: "27.65" };
    expect(groupChoices([far, near]).map((c) => c.area)).toEqual(["Боровляны, Минский район", "Логойск, Логойский район"]);
  });

  it("город целиком — не адрес", () => {
    expect(groupChoices([r(1, "Минск, Беларусь", { city: "Минск" }, 16)])).toEqual([]);
  });
});
