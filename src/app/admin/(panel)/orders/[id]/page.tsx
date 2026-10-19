import { formatPhone } from "@/lib/phone";
import Link from "next/link";
import { notFound } from "next/navigation";
import { db } from "@/lib/db";
import { formatByn } from "@/lib/money";
import { getSetting } from "@/lib/settings";
import { SLOTS } from "@/lib/slots";
import { setOrderAddress, updateOrder } from "../../../actions";
import { PAY_LABEL, STATUS_LABEL } from "../status";
import { Byn } from "@/components/Byn";

export default async function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  const id = Number((await params).id);
  if (!Number.isInteger(id)) notFound();
  const [o, delivery] = await Promise.all([db.order.findUnique({ where: { id }, include: { items: true } }), getSetting("delivery")]);
  if (!o) notFound();
  const shop = delivery.shops.find((s) => s.code === o.shop);
  const tel = (p: string) => <a href={`tel:${p}`}>{formatPhone(p)}</a>;

  return (
    <>
      <Link href="/admin/orders" className="small">← Все заказы</Link>
      <div className="row between">
        <h1>Заказ № {o.number}</h1>
        <span className="pill" style={{ fontSize: 15 }}>{STATUS_LABEL[o.status]}</span>
      </div>
      <div className="admin-grid">
        <div className="stack" style={{ gap: 16 }}>
          <div className="card stack" style={{ gap: 8 }}>
            <span className="label">Что собрать</span>
            {o.items.map((i) => (
              <div key={i.id} className="stack" style={{ gap: 2, paddingBottom: 8, borderBottom: "1px solid var(--line-soft)" }}>
                <div className="sum-row"><b>{i.name}{i.size ? `, ${i.size}` : ""} × {i.qty}</b><span>{formatByn(i.priceKop * i.qty)}</span></div>
                {i.composition && <span className="small muted">{i.composition}</span>}
              </div>
            ))}
            <div className="sum-row"><span>Открытка</span><span className="small">{o.cardText ? `«${o.cardText}»` : "без текста"}</span></div>
          </div>
          <div className="card stack" style={{ gap: 6 }}>
            <span className="label">{o.deliveryMethod === "PICKUP" ? "Самовывоз" : "Доставка"}</span>
            <span><b>{o.deliveryDate.toLocaleDateString("ru-RU", { weekday: "long", day: "numeric", month: "long", timeZone: "UTC" })}</b>, {o.deliverySlot || "время уточнить"}</span>
            {o.deliveryMethod === "DELIVERY" ? (
              <>
                {o.addressUnknown ? (
                  <>
                    <span className="pill" style={{ alignSelf: "flex-start", background: "var(--lavender)" }}>Адрес и время уточнить у получателя</span>
                    <form key={`${o.address}-${o.deliverySlot}-${o.deliveryKop}`} action={setOrderAddress} className="stack" style={{ gap: 8, marginTop: 6 }}>
                      <input type="hidden" name="id" value={o.id} />
                      <label className="field">
                        <span>Адрес, который назвал получатель</span>
                        <input className="input" name="address" defaultValue={o.address} placeholder="Улица, дом, квартира" />
                      </label>
                      <div className="row" style={{ gap: 8, alignItems: "flex-end" }}>
                        <label className="field">
                          <span>Время</span>
                          <select className="input" name="slot" defaultValue={o.deliverySlot}>
                            <option value="">не знаем</option>
                            {SLOTS.map((s) => <option key={s} value={s}>{s}</option>)}
                          </select>
                        </label>
                        <label className="field" style={{ flex: "1 1 120px" }}>
                          <span>Доплата за доставку, BYN</span>
                          <input className="input" name="delivery" inputMode="decimal" defaultValue={(o.deliveryKop / 100).toString()} />
                        </label>
                        <button className="btn">Сохранить</button>
                      </div>
                    </form>
                  </>
                ) : (
                  <>
                    <span>{o.address}</span>
                    <span className="small muted">Зона {o.deliveryZone ?? "?"}, ~{o.distanceKm ?? "?"} км от магазина {shop?.name ?? o.shop}</span>
                  </>
                )}
                <span>Получатель: {o.recipientName || "—"} {o.recipientPhone && tel(o.recipientPhone)}</span>
                {o.callRecipient && <span className="small">Позвонить получателю перед доставкой</span>}
                {o.anonymous && <span className="small" style={{ fontWeight: 800 }}>Не говорить, от кого букет</span>}
              </>
            ) : (
              <span>Магазин: {shop ? `${shop.name}, ${shop.address}` : o.shop}</span>
            )}
          </div>
          <div className="card stack" style={{ gap: 6 }}>
            <span className="label">Клиент</span>
            <span>{o.customerName}, {tel(o.customerPhone)}</span>
            {o.comment && <span className="muted">Комментарий: {o.comment}</span>}
            <span className="small muted">Заказ оформлен {o.createdAt.toLocaleString("ru-RU", { timeZone: "Europe/Minsk" })}</span>
          </div>
        </div>
        <div className="card stack" style={{ gap: 10 }}>
          <div className="sum-row"><span>Товары</span><span>{formatByn(o.itemsKop)}</span></div>
          {o.discountKop > 0 && <div className="sum-row"><span>Скидка за самовывоз</span><span>{formatByn(-o.discountKop)}</span></div>}
          {o.deliveryMethod === "DELIVERY" && (
            <div className="sum-row">
              <span>{o.addressUnknown ? "Доплата за доставку" : "Доставка"}</span>
              <span>{o.addressUnknown && o.deliveryKop === 0 ? "ещё не назначена" : formatByn(o.deliveryKop)}</span>
            </div>
          )}
          <div className="sum-row" style={{ alignItems: "baseline" }}><b>Итого</b><span className="sum-total"><Byn kop={o.totalKop} /></span></div>
          <div className="sum-row"><span>Оплата</span><span>{PAY_LABEL[o.paymentMethod]} · {o.paid ? "оплачен" : "не оплачен"}</span></div>
          <form key={`${o.status}-${o.paid}`} action={updateOrder} className="stack" style={{ gap: 8 }}>
            <input type="hidden" name="id" value={o.id} />
            <label className="field">
              <span>Статус</span>
              <select className="input" name="status" defaultValue={o.status}>
                {Object.entries(STATUS_LABEL).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
              </select>
            </label>
            <label className="field">
              <span>Оплата</span>
              <select className="input" name="paid" defaultValue={o.paid ? "1" : "0"}>
                <option value="0">Не оплачен</option>
                <option value="1">Оплачен</option>
              </select>
            </label>
            <button className="btn">Сохранить</button>
          </form>
        </div>
      </div>
    </>
  );
}
