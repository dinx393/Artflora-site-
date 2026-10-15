import { describe, expect, it } from "vitest";
import { availableSlots, isSlotAllowed } from "@/lib/slots";

// 11:30 по Минску = 08:30 UTC
const now = new Date("2026-10-15T08:30:00Z");

describe("интервалы доставки", () => {
  it("сегодня — только через 2 часа и позже", () => {
    expect(availableSlots("2026-10-15", 2, now)).toEqual(["14–16", "16–18", "18–21"]);
  });
  it("завтра — все интервалы", () => {
    expect(availableSlots("2026-10-16", 2, now)).toHaveLength(5);
  });
  it("вчера — ничего", () => {
    expect(availableSlots("2026-10-14", 2, now)).toEqual([]);
  });
  it("проверка интервала при оформлении", () => {
    expect(isSlotAllowed("2026-10-15", "12–14", 2, now)).toBe(false);
    expect(isSlotAllowed("2026-10-15", "14–16", 2, now)).toBe(true);
  });
});
