import { describe, expect, it } from "vitest";
import { formatPhone } from "@/lib/phone";

describe("formatPhone", () => {
  it("приводит белорусские номера к виду +375 29 111-22-33", () => {
    expect(formatPhone("+375291112233")).toBe("+375 29 111-22-33");
    expect(formatPhone("80291112233")).toBe("+375 29 111-22-33");
    expect(formatPhone("+48123456789")).toBe("+48123456789");
  });
});
