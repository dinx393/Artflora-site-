import "server-only";
import { db } from "./db";
import { formatByn } from "./money";

const MAX_ATTEMPTS = 10;

export function telegramConfigured(): boolean {
  return !!process.env.TELEGRAM_BOT_TOKEN && !!process.env.TELEGRAM_CHAT_ID;
}

// Уведомление о новом заказе в Telegram (если заданы TELEGRAM_BOT_TOKEN и TELEGRAM_CHAT_ID).
// Результат записываем в заказ: не дошло — повторим позже (startNotifyRetries), а админка покажет предупреждение.
export async function notifyNewOrder(orderId: number) {
  if (!telegramConfigured()) return;
  try {
    await sendToTelegram(orderId);
    await db.order.update({ where: { id: orderId }, data: { notifiedAt: new Date(), notifyError: "" } });
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    console.error(`Уведомление о заказе id=${orderId} не отправлено: ${msg}`);
    await db.order
      .update({ where: { id: orderId }, data: { notifyAttempts: { increment: 1 }, notifyError: msg.slice(0, 300) } })
      .catch(() => {});
  }
}

// Раз в минуту повторяем уведомления, которые не дошли (за последние 2 дня, до 10 попыток).
let retryTimer: ReturnType<typeof setInterval> | null = null;
export function startNotifyRetries() {
  if (retryTimer || !telegramConfigured()) return;
  retryTimer = setInterval(async () => {
    try {
      const pending = await db.order.findMany({
        where: { notifiedAt: null, notifyAttempts: { lt: MAX_ATTEMPTS }, createdAt: { gt: new Date(Date.now() - 2 * 86400_000), lt: new Date(Date.now() - 30_000) } },
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
