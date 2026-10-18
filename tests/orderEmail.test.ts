import { describe, expect, it } from "vitest";
import { renderOrderEmail, type OrderEmailData } from "@/lib/orderEmail";

const base: OrderEmailData = {
  number: 5552,
  createdAt: new Date("2026-10-17T06:16:00Z"),
  status: "Новый",
  paid: false,
  payment: "Картой (ссылкой)",
  customerName: "Анастасия <b>",
  customerPhone: "+375291112233",
  recipientName: "Галина Федоровна",
  recipientPhone: "+375293874937",
  callRecipient: false,
  anonymous: true,
  method: "DELIVERY",
  address: "Притыцкого 39, кв 64",
  addressUnknown: false,
  shopName: "Уручье",
  deliveryDate: new Date("2026-10-17T00:00:00Z"),
  deliverySlot: "14–16",
  comment: "Как можно быстрее",
  cardText: "",
  items: [{ name: "Композиция «Розовое облако»", size: "S", qty: 1, priceKop: 15000, photo: "https://artflora.by/a.jpg" }],
  itemsKop: 15000,
  discountKop: 0,
  deliveryKop: 0,
  totalKop: 15000,
  adminUrl: "https://artflora.by/admin/orders/1",
};

describe("письмо о заказе", () => {
  it("тема и главное в тексте", () => {
    const m = renderOrderEmail(base);
    expect(m.subject).toBe("Заказ №5552 на 150 BYN — ждёт обработки");
    expect(m.text).toContain("Адрес доставки: Притыцкого 39, кв 64");
    expect(m.text).toContain("Телефон получателя: +375 29 387-49-37");
    expect(m.text).toContain("Дата заказа: 17.10.2026, 09:16");
    expect(m.text).toContain("не говорить, от кого букет");
    expect(m.html).toContain("https://artflora.by/a.jpg");
    expect(m.html).toContain("бесплатно");
  });
  it("данные клиента экранируются", () => {
    const m = renderOrderEmail(base);
    expect(m.html).toContain("Анастасия &lt;b&gt;");
    expect(m.html).not.toContain("Анастасия <b>");
  });
  it("адрес неизвестен и самовывоз", () => {
    expect(renderOrderEmail({ ...base, addressUnknown: true, deliverySlot: "" }).text).toContain("Адрес и время уточнить у получателя");
    const p = renderOrderEmail({ ...base, method: "PICKUP", shopName: "Малиновка, ул. Яна Чечота 7", discountKop: 1500, totalKop: 13500 });
    expect(p.text).toContain("Самовывоз: Малиновка, ул. Яна Чечота 7");
    expect(p.text).not.toContain("Получатель");
    expect(p.subject).toContain("135 BYN");
  });
});
