import { describe, expect, it } from "vitest";
import { parseColors, toggleColor } from "@/lib/occasions";

describe("цвета в фильтре каталога", () => {
  it("читает несколько цветов и отбрасывает неизвестные и повторы", () => {
    expect(parseColors("pink,red,nope,pink")).toEqual(["pink", "red"]);
    expect(parseColors(undefined)).toEqual([]);
  });

  it("добавляет и убирает цвет, порядок как в списке цветов", () => {
    expect(toggleColor([], "red")).toBe("red");
    expect(toggleColor(["red"], "white")).toBe("white,red");
    expect(toggleColor(["white", "red"], "red")).toBe("white");
    expect(toggleColor(["red"], "red")).toBeUndefined();
  });
});
