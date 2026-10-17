import "server-only";
import { db } from "./db";
import { formatByn } from "./money";

// Уведомление о новом заказе в Telegram (если заданы TELEGRAM_BOT_TOKEN и TELEGRAM_CHAT_ID).
export async function notifyNewOrder(orderId: number) {
  const token = process.env.TELEGRAM_BOT_TOKEN;
  const chat = process.env.TELEGRAM_CHAT_ID;
  if (!token || !chat) return;
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
  await fetch(`https://api.telegram.org/bot${token}/sendMessage`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ chat_id: chat, text: lines.join("\n") }),
    signal: AbortSignal.timeout(5000),
  });
}
