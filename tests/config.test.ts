import { describe, expect, it } from "vitest";
import { configProblems } from "@/lib/config";

const strong = { SESSION_SECRET: "x".repeat(40), ADMIN_PASSWORD: "Очень-длинный-пароль-1" };

describe("настройки при запуске", () => {
  it("на своём компьютере можно с паролем по умолчанию", () => {
    expect(configProblems({ SITE_URL: "http://localhost:8000", ADMIN_PASSWORD: "artflora", SESSION_SECRET: "local-only-secret-change-me-on-server" })).toEqual([]);
  });
  it("на сервере пароль и ключ по умолчанию не пускают", () => {
    const p = configProblems({ SITE_URL: "https://artflora.by", ADMIN_PASSWORD: "artflora", SESSION_SECRET: "local-only-secret-change-me-on-server" });
    expect(p).toHaveLength(2);
  });
  it("на сервере нужен https", () => {
    expect(configProblems({ ...strong, SITE_URL: "http://artflora.by" })).toHaveLength(1);
  });
  it("правильные настройки сервера", () => {
    expect(configProblems({ ...strong, SITE_URL: "https://artflora.by" })).toEqual([]);
  });
  it("без ключа не запускаемся нигде", () => {
    expect(configProblems({ SITE_URL: "http://localhost:8000" })).toHaveLength(1);
  });
});
