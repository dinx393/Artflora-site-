import "server-only";
import nodemailer, { type Transporter } from "nodemailer";
import { PAY_LABEL, STATUS_LABEL } from "@/app/admin/(panel)/orders/status";
import { db } from "./db";
import { formatByn } from "./money";
import { renderOrderEmail } from "./orderEmail";
import { getSetting } from "./settings";

const MAX_ATTEMPTS = 10;

export function telegramConfigured(): boolean {
  return !!process.env.TELEGRAM_BOT_TOKEN && !!process.env.TELEGRAM_CHAT_ID;
}

export function smtpConfigured(): boolean {
  return !!process.env.SMTP_HOST && !!process.env.SMTP_USER && !!process.env.SMTP_PASS;
}

// Куда слать письма о заказах: поле в админке → «Настройки» (можно несколько адресов через запятую).
async function orderEmailTo(): Promise<string> {
  return (await getSetting("shop")).orderEmail?.trim() ?? "";
}

export async function emailConfigured(): Promise<boolean> {
  return smtpConfigured() && !!(await orderEmailTo());
}

// Уведомление о новом заказе: в Telegram (если заданы TELEGRAM_BOT_TOKEN и TELEGRAM_CHAT_ID)
// и письмом на почту магазина (если настроена почта, см. README). Каналы независимы.
// Результат записываем в заказ: не дошло — повторим позже (startNotifyRetries), а админка покажет предупреждение.
export async function notifyNewOrder(orderId: number) {
  const o = await db.order.findUnique({ where: { id: orderId }, select: { notifiedAt: true, emailedAt: true } });
  if (!o) return;
  if (!o.notifiedAt && telegramConfigured()) await notifyTelegram(orderId);
  if (!o.emailedAt && (await emailConfigured())) await notifyEmail(orderId);
}

async function notifyTelegram(orderId: number) {
  try {
    await sendToTelegram(orderId);
    await db.order.update({ where: { id: orderId }, data: { notifiedAt: new Date(), notifyError: "" } });
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    console.error(`Уведомление о заказе id=${orderId} в Telegram не отправлено: ${msg}`);
    await db.order
      .update({ where: { id: orderId }, data: { notifyAttempts: { increment: 1 }, notifyError: msg.slice(0, 300) } })
      .catch(() => {});
  }
}

async function notifyEmail(orderId: number) {
  try {
    await sendEmail(orderId);
    await db.order.update({ where: { id: orderId }, data: { emailedAt: new Date(), emailError: "" } });
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    console.error(`Письмо о заказе id=${orderId} не отправлено: ${msg}`);
    await db.order
      .update({ where: { id: orderId }, data: { emailAttempts: { increment: 1 }, emailError: msg.slice(0, 300) } })
      .catch(() => {});
  }
}

// Раз в минуту повторяем уведомления, которые не дошли (за последние 2 дня, до 10 попыток).
let retryTimer: ReturnType<typeof setInterval> | null = null;
export function startNotifyRetries() {
  if (retryTimer || (!telegramConfigured() && !smtpConfigured())) return;
  retryTimer = setInterval(async () => {
    try {
      const pending = await db.order.findMany({
        where: {
          createdAt: { gt: new Date(Date.now() - 2 * 86400_000), lt: new Date(Date.now() - 30_000) },
          OR: [
            ...(telegramConfigured() ? [{ notifiedAt: null, notifyAttempts: { lt: MAX_ATTEMPTS } }] : []),
            ...(smtpConfigured() ? [{ emailedAt: null, emailAttempts: { lt: MAX_ATTEMPTS } }] : []),
          ],
        },
        select: { id: true },
        orderBy: { createdAt: "asc" },
        take: 20,
      });
      for (const o of pending) await notifyNewOrder(o.id);
    } catch (e) {
      console.error("Повтор уведомлений не удался:", e);
    }
  }, 60_000);
  retryTimer.unref?.();
}

async function sendToTelegram(orderId: number) {
  const token = process.env.TELEGRAM_BOT_TOKEN;
  const chat = process.env.TELEGRAM_CHAT_ID;
  const o = await db.order.findUnique({ where: { id: orderId }, include: { items: true } });
  if (!o) return;
  const lines = [
    `🌸 Новый заказ №${o.number} — ${formatByn(o.totalKop)}`,
    ...o.items.map((i) => `• ${i.name}${i.size ? `, ${i.size}` : ""} × ${i.qty}`),
    o.deliveryMethod === "PICKUP"
      ? `Самовывоз: ${o.shop}`
      : o.addressUnknown
        ? `Доставка: адрес и время уточнить у получателя ${o.recipientPhone}, доплата за доставку отдельно`
        : `Доставка: ${o.address} (зона ${o.deliveryZone ?? "?"})`,
    `Когда: ${o.deliveryDate.toISOString().slice(0, 10)}, ${o.deliverySlot || "время уточнить"}`,
    `Клиент: ${o.customerName}, ${o.customerPhone}`,
    `${process.env.SITE_URL ?? ""}/admin/orders/${o.id}`,
  ];
  const res = await fetch(`https://api.telegram.org/bot${token}/sendMessage`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ chat_id: chat, text: lines.join("\n") }),
    signal: AbortSignal.timeout(5000),
  });
  if (!res.ok) throw new Error(`Telegram ответил ${res.status}: ${(await res.text().catch(() => "")).slice(0, 200)}`);
}

let transport: Transporter | null = null;
function mailer() {
  if (!transport) {
    const port = Number(process.env.SMTP_PORT || 465);
    transport = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port,
      secure: port === 465,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
      connectionTimeout: 10_000,
      socketTimeout: 20_000,
    });
  }
  return transport;
}

// Полный адрес картинки для письма: почтовые программы не знают, где наш сайт.
function absolute(url: string | undefined, site: string): string | null {
  if (!url) return null;
  if (/^https?:\/\//.test(url)) return url;
  return site ? site + (url.startsWith("/") ? url : "/" + url) : null;
}

export async function buildOrderEmail(orderId: number) {
  const o = await db.order.findUnique({ where: { id: orderId }, include: { items: true } });
  if (!o) return null;
  const site = (process.env.SITE_URL ?? "").replace(/\/$/, "");
  const ids = o.items.map((i) => i.bouquetId).filter((x): x is number => x != null);
  const [bouquets, delivery] = await Promise.all([
    db.bouquet.findMany({ where: { id: { in: ids } }, select: { id: true, photos: true } }),
    getSetting("delivery"),
  ]);
  const photo = new Map(bouquets.map((b) => [b.id, b.photos[0]]));
  const shop = delivery.shops.find((s) => s.code === o.shop);
  return renderOrderEmail({
    number: o.number,
    createdAt: o.createdAt,
    status: STATUS_LABEL[o.status] ?? o.status,
    paid: o.paid,
    payment: PAY_LABEL[o.paymentMethod] ?? o.paymentMethod,
    customerName: o.customerName,
    customerPhone: o.customerPhone,
    recipientName: o.recipientName,
    recipientPhone: o.recipientPhone,
    callRecipient: o.callRecipient,
    anonymous: o.anonymous,
    method: o.deliveryMethod,
    address: o.address,
    addressUnknown: o.addressUnknown,
    shopName: shop ? `${shop.name}, ${shop.address}` : o.shop,
    deliveryDate: o.deliveryDate,
    deliverySlot: o.deliverySlot,
    comment: o.comment,
    cardText: o.cardText,
    items: o.items.map((i) => ({
      name: i.name,
      size: i.size,
      qty: i.qty,
      priceKop: i.priceKop,
      photo: absolute(i.bouquetId != null ? photo.get(i.bouquetId) : undefined, site),
    })),
    itemsKop: o.itemsKop,
    discountKop: o.discountKop,
    deliveryKop: o.deliveryKop,
    totalKop: o.totalKop,
    adminUrl: `${site}/admin/orders/${o.id}`,
  });
}

async function sendEmail(orderId: number) {
  const mail = await buildOrderEmail(orderId);
  if (!mail) return;
  await mailer().sendMail({
    from: { name: "Артфлора — заказы", address: process.env.SMTP_FROM || process.env.SMTP_USER! },
    to: await orderEmailTo(),
    subject: mail.subject,
    html: mail.html,
    text: mail.text,
  });
}
