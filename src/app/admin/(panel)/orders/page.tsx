import { formatPhone } from "@/lib/phone";
import Link from "next/link";
import type { OrderStatus } from "@prisma/client";
import { db } from "@/lib/db";
import { smtpConfigured, telegramConfigured } from "@/lib/notify";
import { formatByn } from "@/lib/money";
import { STATUS_LABEL } from "./status";

export default async function Orders({ searchParams }: { searchParams: Promise<{ status?: string }> }) {
  const status = (await searchParams).status;
  const active = !status || status === "active";
  const where = active
    ? { status: { in: ["NEW", "CONFIRMED", "PHOTO_SENT", "ON_THE_WAY"] as OrderStatus[] } }
    : status === "all"
      ? {}
      : { status: status as OrderStatus };
  const orders = await db.order.findMany({ where, include: { items: true }, orderBy: [{ deliveryDate: "asc" }, { deliverySlot: "asc" }], take: 200 });
  const counts = await db.order.groupBy({ by: ["status"], _count: true });
  const count = (s: string) => counts.find((c) => c.status === s)?._count ?? 0;
  // Заказы, о которых не пришло уведомление в Telegram или письмо: их нужно посмотреть здесь.
  const [unnotified, unemailed] = await Promise.all([
    telegramConfigured() ? db.order.count({ where: { notifiedAt: null, notifyAttempts: { gt: 0 } } }) : 0,
    smtpConfigured() ? db.order.count({ where: { emailedAt: null, emailAttempts: { gt: 0 } } }) : 0,
  ]);

  return (
    <>
      <h1>Заказы</h1>
      {unnotified > 0 && (
        <p className="error">Уведомление в Telegram не дошло по {unnotified} заказ(ам). Сайт повторяет отправку сам, но проверьте новые заказы здесь.</p>
      )}
      {unemailed > 0 && (
        <p className="error">Письмо о заказе не ушло на почту по {unemailed} заказ(ам). Сайт повторяет отправку сам; если не проходит, проверьте настройки почты.</p>
      )}
      <div className="tabs">
        <Link href="/admin/orders" className={`chip ${active ? "on" : ""}`}>В работе</Link>
        {Object.entries(STATUS_LABEL).map(([k, label]) => (
          <Link key={k} href={`/admin/orders?status=${k}`} className={`chip ${status === k ? "on" : ""}`}>{label} · {count(k)}</Link>
        ))}
        <Link href="/admin/orders?status=all" className={`chip ${status === "all" ? "on" : ""}`}>Все</Link>
      </div>
      {orders.length === 0 ? (
        <p className="muted">Заказов нет.</p>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>№</th><th>Когда</th><th>Что</th><th>Куда</th><th>Клиент</th><th>Сумма</th><th>Статус</th></tr></thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td><Link href={`/admin/orders/${o.id}`} style={{ fontWeight: 800 }}>{o.number}</Link></td>
                  <td className="small">{o.deliveryDate.toLocaleDateString("ru-RU", { day: "numeric", month: "short", timeZone: "UTC" })}, {o.deliverySlot || "время ?"}</td>
                  <td className="small">{o.items.map((i) => `${i.name}${i.size ? ` ${i.size}` : ""}${i.qty > 1 ? ` ×${i.qty}` : ""}`).join(", ")}</td>
                  <td className="small">{o.deliveryMethod === "PICKUP" ? `Самовывоз · ${o.shop}` : o.address || (o.addressUnknown ? "Уточнить у получателя" : "")}</td>
                  <td className="small">{o.customerName}<br />{formatPhone(o.customerPhone)}</td>
                  <td style={{ fontWeight: 800 }}>{formatByn(o.totalKop)}{o.paid && <div className="pill ok">оплачен</div>}</td>
                  <td><span className={`pill ${o.status === "NEW" ? "warn" : o.status === "DONE" ? "ok" : ""}`}>{STATUS_LABEL[o.status]}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
