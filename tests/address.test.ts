import { describe, expect, it } from "vitest";
import { normalizeAddress } from "../src/lib/address";

describe("normalizeAddress", () => {
  it("разворачивает сокращения улиц", () => {
    expect(normalizeAddress("пр. Независимости 180")).toBe("проспект Независимости 180");
    expect(normalizeAddress("пр-т Дзержинского, д. 5")).toBe("проспект Дзержинского, 5");
    expect(normalizeAddress("ул.Есенина 10")).toBe("улица Есенина 10");
    expect(normalizeAddress("ул. Яна Чечота 7")).toBe("улица Яна Чечота 7");
    expect(normalizeAddress("пер. Корженевского 3")).toBe("переулок Корженевского 3");
  });
  it("не трогает слова, которые начинаются так же", () => {
    expect(normalizeAddress("улица Притыцкого 100")).toBe("улица Притыцкого 100");
    expect(normalizeAddress("Партизанский проспект 1")).toBe("Партизанский проспект 1");
  });
});
