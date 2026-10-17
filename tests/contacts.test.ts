import { describe, expect, it } from "vitest";
import { contactLinks } from "@/lib/contacts";

const shop = { phone: "+375 29 683-28-32", telegram: "https://t.me/", whatsapp: "", viber: "viber://chat?number=%2B375296832832" };

describe("номер в шапке", () => {
  it("четыре варианта: три мессенджера и звонок", () => {
    expect(contactLinks(shop).map((l) => l.label)).toEqual(["Telegram", "WhatsApp", "Viber", "Позвонить"]);
  });
  it("пустые ссылки строим по номеру телефона", () => {
    const [tg, wa, , call] = contactLinks(shop);
    expect(tg.href).toBe("https://t.me/+375296832832");
    expect(wa.href).toBe("https://wa.me/375296832832");
    expect(call.href).toBe("tel:+375296832832");
  });
  it("заполненные в настройках ссылки не трогаем", () => {
    const [tg, wa] = contactLinks({ ...shop, telegram: "https://t.me/artflora_by", whatsapp: "https://wa.me/375291112233" });
    expect(tg.href).toBe("https://t.me/artflora_by");
    expect(wa.href).toBe("https://wa.me/375291112233");
  });
});
