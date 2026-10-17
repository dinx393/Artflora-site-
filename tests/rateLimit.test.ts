import { beforeEach, describe, expect, it } from "vitest";
import { allow, forget, resetRateLimits } from "@/lib/rateLimit";

beforeEach(resetRateLimits);

describe("ограничение частоты", () => {
  it("вход: 5 попыток с одного адреса за 15 минут, потом стоп", () => {
    const now = 1_000_000;
    for (let i = 0; i < 5; i++) expect(allow("login", "1.1.1.1", now + i)).toBe(true);
    expect(allow("login", "1.1.1.1", now + 10)).toBe(false);
    expect(allow("login", "2.2.2.2", now + 10)).toBe(true);
  });
  it("через 15 минут снова можно", () => {
    const now = 1_000_000;
    for (let i = 0; i < 5; i++) allow("login", "1.1.1.1", now);
    expect(allow("login", "1.1.1.1", now + 15 * 60_000 + 1)).toBe(true);
  });
  it("общий лимит держит, даже если адреса разные", () => {
    const now = 1_000_000;
    for (let i = 0; i < 30; i++) expect(allow("login", `10.0.0.${i}`, now)).toBe(true);
    expect(allow("login", "10.0.1.1", now)).toBe(false);
  });
  it("удачный вход обнуляет счётчик адреса", () => {
    const now = 1_000_000;
    for (let i = 0; i < 4; i++) allow("login", "1.1.1.1", now);
    forget("login", "1.1.1.1");
    for (let i = 0; i < 5; i++) expect(allow("login", "1.1.1.1", now)).toBe(true);
  });
});
