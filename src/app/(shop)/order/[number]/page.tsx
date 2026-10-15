import { formatPhone } from "@/lib/phone";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { db } from "@/lib/db";
import { formatByn } from "@/lib/money";
import { orderToken } from "@/lib/orderToken";
import { getSetting } from "@/lib/settings";

export const metadata: Metadata = { title: "Заказ принят", robots: { index: false } };

const PAY: Record<string, string> = { CARD: "картой на сайте", ERIP: "через ЕРИП", VIBER_LINK: "ссылкой в Viber после фото" };

function humanDate(d: Date): string {
  return d.toLocaleDateString("ru-RU", { day: "numeric", month: "long", timeZone: "UTC" });
}

export default async function OrderDone({
  params,
  searchParams,
}: {
  params: Promise<{ number: string }>;
  searchParams: Promise<{ t?: string }>;
}) {
  const number = Number((await params).number);
  const { t } = await searchParams;
  if (!Number.isInteger(number) || t !== orderToken(number)) notFound();
  const [order, shop, delivery] = await Promise.all([
    db.order.findUnique({ where: { number }, include: { items: true } }),
    getSetting("shop"),
    getSetting("delivery"),
  ]);
  if (!order) notFound();
  const pickupShop = delivery.shops.find((s) => s.code === order.shop);

  return (
    <div className="section row" style={{ gap: 40, alignItems: "flex-start" }}>
      <div className="stack" style={{ flex: "1 1 420px", gap: 20 }}>
        <h1 className="h2" style={{ fontSize: 56 }}>
          Спасибо! Заказ <span className="script">принят</span>
        </h1>
        <p className="muted" style={{ fontSize: 18 }}>
          Номер заказа {order.number}. Мы позвоним или напишем на {formatPhone(order.customerPhone)}. Что будет дальше:
        </p>
        <ol className="stack" style={{ gap: 14, paddingLeft: 20, margin: 0 }}>
          <li>
            <b>Флорист соберёт букет</b>
            <div className="muted">К выбранному времени: {humanDate(order.deliveryDate)}, {order.deliverySlot}</div>
          </li>
          <li>
            <b>Пришлём фото в Viber</b>
            <div className="muted">Если что-то не так, поправим до отправки</div>
          </li>
          <li>
            {order.deliveryMethod === "DELIVERY" ? (
              <>
                <b>Курьер позвонит получателю</b>
                <div className="muted">И привезёт букет по адресу</div>
              </>
            ) : (
              <>
                <b>Заберите букет в магазине</b>
                <div className="muted">{pickupShop ? `${pickupShop.name}, ${pickupShop.address}` : order.shop}</div>
              </>
            )}
          </li>
        </ol>
        <div className="row">
          <a href={shop.viber} className="btn btn-lg">Открыть чат в Viber</a>
          <Link href="/" className="btn btn-lg btn-outline">На главную</Link>
        </div>
      </div>
      <div className="card stack" style={{ flex: "1 1 340px", gap: 10 }}>
        <h2 style={{ fontSize: 28 }}>Заказ № {order.number}</h2>
        {order.deliveryMethod === "DELIVERY" && (
          <p className="small muted">Получатель: {[order.recipientName || order.customerName, order.address].filter(Boolean).join(", ")}</p>
        )}
        {order.items.map((i) => (
          <div key={i.id} className="sum-row">
            <span>{i.name}{i.size ? `, ${i.size}` : ""}{i.qty > 1 ? ` × ${i.qty}` : ""}</span>
            <span>{formatByn(i.priceKop * i.qty)}</span>
          </div>
        ))}
        <div className="sum-row"><span>Открытка и подкормка</span><span style={{ color: "var(--olive)", fontWeight: 700 }}>в подарок</span></div>
        {order.discountKop > 0 && <div className="sum-row"><span>Скидка за самовывоз</span><span>{formatByn(-order.discountKop)}</span></div>}
        {order.deliveryMethod === "DELIVERY" && (
          <div className="sum-row"><span>Доставка{order.deliveryZone ? `, зона ${order.deliveryZone}` : ""}</span><span>{order.deliveryKop ? formatByn(order.deliveryKop) : "бесплатно"}</span></div>
        )}
        <div className="sum-row"><span>Оплата</span><span>{PAY[order.paymentMethod]}</span></div>
        <div className="sum-row" style={{ borderTop: "1px solid var(--line)", paddingTop: 10, alignItems: "baseline" }}>
          <b>Итого</b><span className="sum-total">{formatByn(order.totalKop)}</span>
        </div>
      </div>
      <div className="panel row between" style={{ flex: "1 1 100%", gap: 16 }}>
        <div className="stack" style={{ gap: 6, flex: "1 1 360px" }}>
          <div style={{ fontFamily: "var(--serif)", fontSize: 28, fontWeight: 600 }}>Напомнить в следующий раз?</div>
          <div className="muted">Сохраните повод и дату, например день рождения мамы. За 5 дней пришлём подборку в Viber и скидку 5%.</div>
        </div>
        <Link href="/remind" className="btn btn-lg">Сохранить дату</Link>
      </div>
    </div>
  );
}
