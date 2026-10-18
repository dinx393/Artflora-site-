import { formatByn } from "./money";
import { formatPhone } from "./phone";

// Письмо магазину о новом заказе: те же сведения, что приходили раньше на почту,
// в цветах сайта («Ягодный сад» + пудровая лаванда). Только разметка, без отправки.

export type OrderEmailData = {
  number: number;
  createdAt: Date;
  status: string; // «Новый»
  paid: boolean;
  payment: string; // «ЕРИП»
  customerName: string;
  customerPhone: string;
  recipientName: string;
  recipientPhone: string;
  callRecipient: boolean;
  anonymous: boolean;
  method: "DELIVERY" | "PICKUP";
  address: string;
  addressUnknown: boolean;
  shopName: string; // «Уручье, пр. Независимости 168/1»
  deliveryDate: Date;
  deliverySlot: string;
  comment: string;
  cardText: string;
  items: { name: string; size: string; qty: number; priceKop: number; photo: string | null }[];
  itemsKop: number;
  discountKop: number;
  deliveryKop: number;
  totalKop: number;
  adminUrl: string;
};

const C = {
  cherry: "#7E1F3C",
  ink: "#3a2a30",
  muted: "#7a6a72",
  lavender: "#D6CBE3",
  lavenderInk: "#6B4E8C",
  blush: "#F6F3FA",
  line: "#E7E0EF",
  green: "#5E6B32",
};

const esc = (s: string) =>
  s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");

const dateTime = (d: Date) =>
  d.toLocaleString("ru-RU", { timeZone: "Europe/Minsk", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
const day = (d: Date) => d.toLocaleDateString("ru-RU", { timeZone: "UTC", weekday: "long", day: "numeric", month: "long" });

function deliveryLine(o: OrderEmailData): string {
  if (o.method === "PICKUP") return `Самовывоз: ${o.shopName}`;
  if (o.addressUnknown) return "Адрес и время уточнить у получателя, доплата за доставку отдельно";
  return o.address;
}

function rows(o: OrderEmailData): [string, string][] {
  const r: [string, string][] = [
    ["Статус", o.status],
    ["Оплата", `${o.paid ? "оплачен" : "не оплачен"} · ${o.payment}`],
    ["Имя", o.customerName],
    ["Телефон", formatPhone(o.customerPhone)],
  ];
  if (o.method === "DELIVERY") {
    r.push(["Получатель", o.recipientName || "—"]);
    if (o.recipientPhone) r.push(["Телефон получателя", formatPhone(o.recipientPhone)]);
  }
  r.push([o.method === "PICKUP" ? "Самовывоз" : "Адрес доставки", deliveryLine(o)]);
  r.push(["Когда", `${day(o.deliveryDate)}, ${o.deliverySlot || "время уточнить"}`]);
  const notes = [o.callRecipient && "позвонить получателю перед доставкой", o.anonymous && "не говорить, от кого букет"].filter(Boolean);
  if (notes.length) r.push(["Важно", notes.join("; ")]);
  r.push(["Открытка", o.cardText ? `«${o.cardText}»` : "без текста"]);
  if (o.comment) r.push(["Комментарий", o.comment]);
  r.push(["Дата заказа", dateTime(o.createdAt)]);
  return r;
}

export function renderOrderEmail(o: OrderEmailData): { subject: string; html: string; text: string } {
  const total = formatByn(o.totalKop);
  const state = o.paid ? "оплачен" : "ещё не оплачен, ждёт обработки";
  const subject = `Заказ №${o.number} на ${total} — ${o.paid ? "оплачен" : "ждёт обработки"}`;

  const td = `padding:12px 14px;border-bottom:1px solid ${C.line};vertical-align:top;font-size:15px;line-height:1.45`;
  const info = rows(o)
    .map(
      ([k, v]) =>
        `<tr><td style="${td};width:38%;color:${C.muted}">${esc(k)}</td><td style="${td};color:${C.ink}">${esc(v).replace(/\n/g, "<br>")}</td></tr>`,
    )
    .join("");

  const items = o.items
    .map((i) => {
      const img = i.photo
        ? `<img src="${esc(i.photo)}" width="64" height="64" alt="" style="display:block;width:64px;height:64px;object-fit:cover;border-radius:12px;background:${C.blush}">`
        : `<div style="width:64px;height:64px;border-radius:12px;background:${C.blush}"></div>`;
      return `<tr>
        <td style="${td};width:64px;padding-right:0">${img}</td>
        <td style="${td}"><div style="font-weight:700;color:${C.ink}">${esc(i.name)}</div>${i.size ? `<div style="color:${C.muted};font-size:14px">Размер ${esc(i.size)}</div>` : ""}</td>
        <td style="${td};text-align:right;white-space:nowrap;color:${C.ink}">${i.qty} шт × ${formatByn(i.priceKop)}</td>
      </tr>`;
    })
    .join("");

  const sum = (k: string, v: string, strong = false) =>
    `<tr><td style="padding:6px 0;font-size:15px;color:${strong ? C.ink : C.muted};${strong ? "font-weight:700;font-size:17px" : ""}">${k}</td><td style="padding:6px 0;text-align:right;font-size:15px;color:${C.ink};${strong ? "font-weight:700;font-size:17px" : ""}">${v}</td></tr>`;
  const deliveryText =
    o.method === "PICKUP"
      ? "самовывоз"
      : o.addressUnknown
        ? "доплатой после уточнения"
        : o.deliveryKop === 0
          ? `<span style="color:${C.green}">бесплатно</span>`
          : formatByn(o.deliveryKop);
  const sums = [
    sum("Букеты и дополнения", formatByn(o.itemsKop)),
    o.discountKop ? sum("Скидка за самовывоз", "−" + formatByn(o.discountKop)) : "",
    sum("Доставка", deliveryText),
    sum("Способ оплаты", esc(o.payment)),
    sum("Итого", total, true),
  ].join("");

  const html = `<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(subject)}</title></head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Manrope,'Segoe UI',Arial,sans-serif;color:${C.ink}">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">
  <tr><td style="height:6px;background:${C.lavender};border-radius:6px 6px 0 0"></td></tr>
  <tr><td style="padding:22px 4px 6px">
    <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:26px;color:${C.cherry};letter-spacing:.5px">Артфлора</div>
  </td></tr>
  <tr><td style="padding:8px 4px 18px">
    <div style="font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:32px;line-height:1.15;color:${C.ink}">
      <a href="${esc(o.adminUrl)}" style="color:${C.cherry};text-decoration:underline">Заказ №${o.number}</a> на сумму ${total}
      <span style="font-style:italic;color:${C.lavenderInk}">${state}</span>
    </div>
  </td></tr>
  <tr><td><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ${C.line};border-radius:14px;border-collapse:separate;overflow:hidden">${info}</table></td></tr>
  <tr><td style="padding:28px 4px 10px;font-family:'Cormorant Garamond',Cormorant,Georgia,serif;font-size:26px;color:${C.ink}">Покупатель заказал</td></tr>
  <tr><td><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ${C.line};border-radius:14px;border-collapse:separate;overflow:hidden">${items}</table></td></tr>
  <tr><td style="height:10px;line-height:10px;font-size:0">&nbsp;</td></tr>
  <tr><td style="padding:12px 14px;background:${C.blush};border-radius:14px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">${sums}</table></td></tr>
  <tr><td align="center" style="padding:26px 0 8px">
    <a href="${esc(o.adminUrl)}" style="display:inline-block;background:${C.cherry};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:999px">Открыть заказ в админке</a>
  </td></tr>
  <tr><td style="padding:22px 4px 0;border-top:1px solid ${C.line};color:${C.muted};font-size:13px;text-align:center">Письмо отправлено сайтом автоматически</td></tr>
</table></td></tr></table></body></html>`;

  const text = [
    `Заказ №${o.number} на сумму ${total} ${state}`,
    "",
    ...rows(o).map(([k, v]) => `${k}: ${v}`),
    "",
    "Покупатель заказал:",
    ...o.items.map((i) => `• ${i.name}${i.size ? `, размер ${i.size}` : ""} — ${i.qty} шт × ${formatByn(i.priceKop)}`),
    o.discountKop ? `Скидка: −${formatByn(o.discountKop)}` : "",
    `Доставка: ${deliveryText.replace(/<[^>]+>/g, "")}`,
    `Итого: ${total}`,
    "",
    o.adminUrl,
  ]
    .filter((l, i, a) => l !== "" || a[i - 1] !== "")
    .join("\n");

  return { subject, html, text };
}
