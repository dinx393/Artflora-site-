"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import type { CartState } from "@/lib/cart";
import type { DistrictPreset } from "@/lib/defaults";
import { formatByn } from "@/lib/money";
import { addDays, availableSlots, minskNow, SLOTS } from "@/lib/slots";
import { SUBSTITUTION_NOTE } from "./BuyBox";
import { useCart } from "./CartProvider";
import { fetchQuote, type QuoteResponse } from "./DeliveryEstimate";
import { LeafIcon } from "./icons";

type ShopLite = { code: string; name: string; address: string };

type Totals = {
  priced: {
    lines: {
      bouquetId: number;
      slug: string;
      name: string;
      photo: string;
      size: "S" | "M" | "L";
      sizes: { code: "S" | "M" | "L"; priceKop: number }[];
      qty: number;
      priceKop: number;
    }[];
    addons: { addonId: number; name: string; qty: number; priceKop: number }[];
    removed: string[];
    bouquetsKop: number;
    addonsKop: number;
  };
  discountKop: number;
  deliveryKop: number;
  totalKop: number;
};

const PAYMENTS = [
  { code: "CARD", title: "Картой", note: "Пришлём ссылку на оплату после подтверждения заказа" },
  { code: "ERIP", title: "ЕРИП", note: "Пришлём код для оплаты после подтверждения заказа" },
  { code: "VIBER_LINK", title: "Ссылкой в Viber", note: "После того как согласуем фото" },
] as const;

// UUID v4. crypto.randomUUID есть не везде (например, сайт открыт по адресу в домашней сети без https).
function newKey(): string {
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = [...b].map((x) => x.toString(16).padStart(2, "0")).join("");
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

export function Checkout({
  shops,
  districts,
  pickupPct,
  freeFromKop,
  leadHours,
}: {
  shops: ShopLite[];
  districts: DistrictPreset[];
  pickupPct: number;
  freeFromKop: number;
  leadHours: number;
}) {
  const router = useRouter();
  const { cart, ready, setCart, clear } = useCart();
  const [step, setStep] = useState(1);
  const [totals, setTotals] = useState<Totals | null>(null);

  const [method, setMethod] = useState<"DELIVERY" | "PICKUP">("DELIVERY");
  const [address, setAddress] = useState("");
  // Адрес и время не знает клиент: уточним у получателя, доставку доплатят потом.
  const [addressUnknown, setAddressUnknown] = useState(false);
  const [district, setDistrict] = useState<string | null>(null);
  const [quote, setQuote] = useState<QuoteResponse | null>(null);
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(null);
  const [quoting, setQuoting] = useState(false);
  const [pickupShop, setPickupShop] = useState(shops[0]?.code ?? "");

  const today = useMemo(() => minskNow().date, []);
  const [dayMode, setDayMode] = useState<"today" | "tomorrow" | "other">("today");
  const [otherDate, setOtherDate] = useState(addDays(today, 2));
  const date = dayMode === "today" ? today : dayMode === "tomorrow" ? addDays(today, 1) : otherDate;
  const slots = availableSlots(date, leadHours);
  const [slot, setSlot] = useState<string>("");

  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("+375 ");
  const [recipientName, setRecipientName] = useState("");
  const [recipientPhone, setRecipientPhone] = useState("");
  const [callRecipient, setCallRecipient] = useState(true);
  const [anonymous, setAnonymous] = useState(false);
  const [comment, setComment] = useState("");
  const [payment, setPayment] = useState<(typeof PAYMENTS)[number]["code"]>("CARD");

  const [error, setError] = useState<{ text: string; field?: string } | null>(null);
  const [sending, setSending] = useState(false);
  // Ключ этого оформления: если запрос уйдёт дважды, сервер вернёт тот же заказ, а не создаст второй.
  const [idempotencyKey] = useState(newKey);

  // Если на сегодня интервалов не осталось, сразу предлагаем завтра.
  useEffect(() => {
    if (dayMode === "today" && availableSlots(today, leadHours).length === 0) setDayMode("tomorrow");
  }, [dayMode, today, leadHours]);
  useEffect(() => {
    if (!slots.includes(slot as never)) setSlot(slots[0] ?? "");
  }, [date, slots.join()]); // eslint-disable-line react-hooks/exhaustive-deps

  // Пересчёт корзины на сервере при каждом изменении.
  useEffect(() => {
    if (!ready) return;
    if (cart.lines.length === 0) {
      setTotals(null);
      return;
    }
    const ctrl = new AbortController();
    fetch("/api/cart", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ cart, method, point: method === "DELIVERY" && !addressUnknown ? point : null }),
      signal: ctrl.signal,
    })
      .then((r) => r.json())
      .then((t: Totals) => setTotals(t))
      .catch(() => {});
    return () => ctrl.abort();
  }, [cart, method, point, addressUnknown, ready]);

  // Убираем из корзины то, что закончилось.
  useEffect(() => {
    if (!totals || totals.priced.removed.length === 0) return;
    const keep = new Set(totals.priced.lines.map((l) => `${l.bouquetId}:${l.size}`));
    setCart((c) => ({ ...c, lines: c.lines.filter((l) => keep.has(`${l.bouquetId}:${l.size}`)) }));
  }, [totals, setCart]);

  async function runQuote(input: { address?: string; point?: { lat: number; lng: number } }) {
    setQuoting(true);
    try {
      const q = await fetchQuote({ ...input, subtotalKop: totals?.priced.bouquetsKop ?? 0 });
      setQuote(q);
      setPoint(q.ok ? q.point : null);
    } finally {
      setQuoting(false);
    }
  }

  // Подписи «До бесплатной доставки …» пересчитываем при изменении суммы.
  const leftToFree = Math.max(0, freeFromKop - (totals?.priced.bouquetsKop ?? 0));

  function update(fn: (c: CartState) => CartState) {
    setCart(fn);
  }

  async function submit() {
    setError(null);
    setSending(true);
    try {
      const res = await fetch("/api/orders", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          cart,
          method,
          address: method === "DELIVERY" && !addressUnknown ? address : "",
          addressUnknown: method === "DELIVERY" && addressUnknown,
          point: method === "DELIVERY" && !addressUnknown ? point : null,
          shop: method === "PICKUP" ? pickupShop : "",
          date,
          slot,
          customerName,
          customerPhone,
          recipientName,
          recipientPhone,
          callRecipient,
          anonymous,
          comment,
          payment,
          idempotencyKey,
        }),
      });
      const r = await res.json();
      if (r.ok) {
        clear();
        router.push(`/order/${r.number}?t=${r.token}`);
        return;
      }
      setError({ text: r.error ?? "Не получилось оформить заказ", field: r.field });
      if (r.field === "address" || r.field === "slot") setStep(2);
      if (r.field?.startsWith("customer") || r.field === "recipientPhone") setStep(3);
    } catch {
      setError({ text: "Нет связи с сайтом. Проверьте интернет и попробуйте ещё раз" });
    } finally {
      setSending(false);
    }
  }

  if (ready && cart.lines.length === 0) {
    return (
      <div className="section">
        <div className="empty panel">
          <h1 className="h2">В корзине пока пусто</h1>
          <p className="muted">Выберите букет в каталоге или начните с повода.</p>
          <div className="row" style={{ justifyContent: "center", gap: 8 }}>
            <Link href="/catalog?occasion=mom" className="chip">Маме</Link>
            <Link href="/catalog?occasion=love" className="chip">Любимой</Link>
            <Link href="/catalog?q=розы" className="chip">Розы</Link>
            <Link href="/catalog?price=to80" className="chip">До 80 BYN</Link>
          </div>
          <Link href="/catalog" className="btn btn-lg">Перейти в каталог</Link>
          <Link href="/#hits">Хиты этой недели →</Link>
        </div>
      </div>
    );
  }

  const lines = totals?.priced.lines ?? [];
  const unknown = method === "DELIVERY" && addressUnknown;
  const deliveryReady = method === "PICKUP" || unknown || (quote?.ok ?? false);
  const canNext1 = lines.length > 0;
  const canNext2 = unknown ? slots.length > 0 : deliveryReady && !!slot && (method === "PICKUP" || address.trim().length > 3);
  const recipientOk = !unknown || recipientPhone.replace(/\D/g, "").length >= 9;
  const canSubmit = canNext1 && canNext2 && recipientOk && customerName.trim().length > 0 && customerPhone.replace(/\D/g, "").length >= 9;

  return (
    <div className="wrap">
      <div className="steps desktop-only" style={{ paddingTop: 24 }}>
        <span className="on">1 Корзина</span>—<span className={step >= 2 ? "on" : ""}>2 Доставка</span>—
        <span className={step >= 3 ? "on" : ""}>3 Получатель и оплата</span>
      </div>
      <div className="mobile-only row between" style={{ paddingTop: 20 }}>
        <h1 style={{ fontSize: 34 }}>{["Корзина", "Доставка", "Получатель"][step - 1]}</h1>
        <span className="small muted">Шаг {step} из 3</span>
      </div>

      <div className="checkout" data-step={step}>
        <div className="checkout-main">
          {/* Шаг 1. Корзина */}
          <section className="step-1 stack" style={{ gap: 12 }}>
            <Link href="/catalog" className="small desktop-only" style={{ fontWeight: 700 }}>← Продолжить покупки</Link>
            <h1 className="h2 desktop-only">Ваша <span className="script">корзина</span></h1>
            {!totals && <p className="muted">Считаем…</p>}
            {totals?.priced.removed.length ? (
              <p className="error">Закончились и убраны из корзины: {totals.priced.removed.join(", ")}</p>
            ) : null}
            <div>
              {lines.map((l) => (
                <div key={`${l.bouquetId}:${l.size}`} className="line">
                  <Link href={`/bouquet/${l.slug}`}><img src={l.photo} alt={l.name} /></Link>
                  <div className="stack" style={{ gap: 8 }}>
                    <Link href={`/bouquet/${l.slug}`} className="pcard-title" style={{ fontSize: 22 }}>{l.name}</Link>
                    <div className="row" style={{ gap: 10 }}>
                      {l.sizes.length > 1 && (
                        <div className="seg" role="group" aria-label="Размер">
                          {l.sizes.map((s) => (
                            <button
                              key={s.code}
                              type="button"
                              className={s.code === l.size ? "on" : ""}
                              onClick={() =>
                                update((c) => ({
                                  ...c,
                                  lines: c.lines.map((x) => (x.bouquetId === l.bouquetId && x.size === l.size ? { ...x, size: s.code } : x)),
                                }))
                              }
                            >
                              {s.code}
                            </button>
                          ))}
                        </div>
                      )}
                      <div className="qty">
                        <button
                          type="button"
                          aria-label="Меньше"
                          onClick={() =>
                            update((c) => ({
                              ...c,
                              lines: c.lines.map((x) => (x.bouquetId === l.bouquetId && x.size === l.size ? { ...x, qty: Math.max(1, x.qty - 1) } : x)),
                            }))
                          }
                        >
                          −
                        </button>
                        <span>{l.qty}</span>
                        <button
                          type="button"
                          aria-label="Больше"
                          onClick={() =>
                            update((c) => ({
                              ...c,
                              lines: c.lines.map((x) => (x.bouquetId === l.bouquetId && x.size === l.size ? { ...x, qty: Math.min(20, x.qty + 1) } : x)),
                            }))
                          }
                        >
                          +
                        </button>
                      </div>
                      <button
                        type="button"
                        className="btn-ghost small muted"
                        onClick={() =>
                          update((c) => ({ ...c, lines: c.lines.filter((x) => !(x.bouquetId === l.bouquetId && x.size === l.size)) }))
                        }
                      >
                        Удалить
                      </button>
                    </div>
                  </div>
                  <span className="price line-price">{formatByn(l.priceKop * l.qty)}</span>
                </div>
              ))}
              {totals?.priced.addons.map((a) => (
                <div key={a.addonId} className="row between" style={{ padding: "12px 0", borderBottom: "1px solid var(--line)" }}>
                  <span style={{ fontWeight: 700 }}>{a.name}</span>
                  <span className="row" style={{ gap: 12 }}>
                    {formatByn(a.priceKop * a.qty)}
                    <button type="button" className="btn-ghost small muted" onClick={() => update((c) => ({ ...c, addons: c.addons.filter((x) => x.addonId !== a.addonId) }))}>
                      Удалить
                    </button>
                  </span>
                </div>
              ))}
            </div>
            <div className="zone" style={{ fontWeight: 700 }}>В подарок к заказу: открытка и 2 пакетика подкормки</div>
            <label className="field">
              <span>Текст открытки</span>
              <textarea
                className="input"
                rows={2}
                maxLength={500}
                value={cart.cardText}
                onChange={(e) => update((c) => ({ ...c, cardText: e.target.value }))}
                placeholder="Мама, с днём рождения! Люблю тебя"
              />
            </label>
          </section>

          {/* Шаг 2. Доставка и время */}
          <section className="step-2 stack" style={{ gap: 16 }}>
            <h2 className="h2 desktop-only">Как <span className="script">получить</span></h2>
            <div className="seg" style={{ alignSelf: "flex-start" }}>
              <button type="button" className={method === "DELIVERY" ? "on" : ""} onClick={() => setMethod("DELIVERY")}>Доставка</button>
              <button type="button" className={method === "PICKUP" ? "on" : ""} onClick={() => setMethod("PICKUP")}>Самовывоз −{pickupPct}%</button>
            </div>

            {method === "DELIVERY" && (
              <label className="check">
                <input type="checkbox" checked={addressUnknown} onChange={(e) => setAddressUnknown(e.target.checked)} />
                Не знаю адрес, уточните у получателя
              </label>
            )}
            {unknown ? (
              <div className="zone">
                <b>Адрес и время уточним у получателя</b>
                <span className="small muted">
                  Сейчас вы оплачиваете только букет. Мы позвоним получателю, узнаем адрес и удобное время и пришлём вам
                  доплату за доставку по обычным тарифам.
                </span>
              </div>
            ) : method === "DELIVERY" ? (
              <div className="stack">
                <label className="field">
                  <span>Адрес доставки</span>
                  <input
                    className="input"
                    value={address}
                    onChange={(e) => {
                      setAddress(e.target.value);
                      setDistrict(null);
                    }}
                    onBlur={() => address.trim().length > 3 && runQuote({ address })}
                    onKeyDown={(e) => {
                      if (e.key === "Enter") {
                        e.preventDefault();
                        runQuote({ address });
                      }
                    }}
                    placeholder="Улица, дом, квартира"
                    aria-invalid={error?.field === "address"}
                  />
                </label>
                <div className="small muted">Или выберите район, а точный адрес напишите выше:</div>
                <div className="row" style={{ gap: 6 }}>
                  {districts.map((d) => (
                    <button
                      key={d.name}
                      type="button"
                      className={`chip ${district === d.name ? "on" : ""}`}
                      style={{ fontSize: 14 }}
                      onClick={() => {
                        setDistrict(d.name);
                        runQuote({ point: { lat: d.lat, lng: d.lng } });
                      }}
                    >
                      {d.name}
                    </button>
                  ))}
                </div>
                {quoting && <p className="small muted">Считаем доставку…</p>}
                {quote && !quote.ok && (
                  <p className="error">
                    {quote.reason === "too-far" ? "Так далеко мы не возим, позвоните нам." : quote.reason === "busy" ? "Слишком много запросов, попробуйте через минуту или выберите район." : "Не нашли такой адрес. Уточните улицу и дом или выберите район."}
                  </p>
                )}
                {quote?.ok && (
                  <div className="zone">
                    <div className="row between">
                      <b>Зона {quote.zone}</b>
                      <span className="price" style={{ fontSize: 24 }}>
                        {totals && totals.deliveryKop === 0 ? "Бесплатно" : formatByn(totals?.deliveryKop ?? quote.priceKop)}
                      </span>
                    </div>
                    <span className="small muted">
                      Ближайший магазин {quote.shop.name}, ~{quote.km} км{quote.outsideMkadKm > 0 ? `, ${quote.outsideMkadKm} км за МКАД` : ""}
                    </span>
                    <span className="small muted">Привезём от {leadHours} часов</span>
                    {leftToFree > 0 && <span className="small" style={{ color: "var(--olive)", fontWeight: 700 }}>До бесплатной доставки {formatByn(leftToFree)}</span>}
                  </div>
                )}
              </div>
            ) : (
              <div className="stack">
                <p className="muted">Соберём к выбранному времени и пришлём фото. Скидка {pickupPct}% на букет.</p>
                <div className="row" style={{ gap: 8 }}>
                  {shops.map((s) => (
                    <button key={s.code} type="button" className={`opt ${pickupShop === s.code ? "on" : ""}`} onClick={() => setPickupShop(s.code)}>
                      <b>{s.name}</b>
                      <span className="note">{s.address}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            <div className="stack">
              <div className="label">{method === "DELIVERY" ? "Когда привезти" : "Когда заберёте"}</div>
              <div className="row" style={{ gap: 8 }}>
                <button type="button" className={`chip ${dayMode === "today" ? "on" : ""}`} disabled={availableSlots(today, leadHours).length === 0} onClick={() => setDayMode("today")}>
                  Сегодня
                </button>
                <button type="button" className={`chip ${dayMode === "tomorrow" ? "on" : ""}`} onClick={() => setDayMode("tomorrow")}>Завтра</button>
                <button type="button" className={`chip ${dayMode === "other" ? "on" : ""}`} onClick={() => setDayMode("other")}>Другая дата</button>
                {dayMode === "other" && (
                  <input type="date" className="input" style={{ width: "auto" }} min={addDays(today, 1)} max={addDays(today, 90)} value={otherDate} onChange={(e) => setOtherDate(e.target.value)} />
                )}
              </div>
              {unknown ? (
                <p className="small muted">Точное время согласуем с получателем.</p>
              ) : (
              <div className="row" style={{ gap: 8 }}>
                {SLOTS.map((s) => (
                  <button key={s} type="button" className={`chip ${slot === s ? "on" : ""}`} disabled={!slots.includes(s)} onClick={() => setSlot(s)}>
                    {s}
                  </button>
                ))}
              </div>
              )}
              {error?.field === "slot" && <p className="error">{error.text}</p>}
            </div>
          </section>

          {/* Шаг 3. Получатель и оплата */}
          <section className="step-3 stack" style={{ gap: 16 }}>
            <h2 className="h2 desktop-only">Получатель <span className="script">и оплата</span></h2>
            <div className="grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 12 }}>
              <label className="field">
                <span>Ваше имя</span>
                <input className="input" value={customerName} onChange={(e) => setCustomerName(e.target.value)} autoComplete="name" />
              </label>
              <label className="field">
                <span>Ваш телефон</span>
                <input className="input" type="tel" value={customerPhone} onChange={(e) => setCustomerPhone(e.target.value)} autoComplete="tel" aria-invalid={error?.field === "customerPhone"} />
              </label>
              {method === "DELIVERY" && (
                <>
                  <label className="field">
                    <span>Имя получателя</span>
                    <input className="input" value={recipientName} onChange={(e) => setRecipientName(e.target.value)} placeholder="Если дарите не себе" />
                  </label>
                  <label className="field">
                    <span>{unknown ? "Телефон получателя — по нему уточним адрес" : "Телефон получателя"}</span>
                    <input
                      className="input"
                      type="tel"
                      value={recipientPhone}
                      onChange={(e) => setRecipientPhone(e.target.value)}
                      placeholder={unknown ? "+375 …" : undefined}
                      aria-invalid={error?.field === "recipientPhone" || (unknown && !recipientOk && recipientPhone.length > 0)}
                    />
                  </label>
                </>
              )}
            </div>
            {method === "DELIVERY" && (
              <div className="stack" style={{ gap: 8 }}>
                <label className="check"><input type="checkbox" checked={callRecipient} onChange={(e) => setCallRecipient(e.target.checked)} />Позвонить получателю перед доставкой</label>
                <label className="check"><input type="checkbox" checked={anonymous} onChange={(e) => setAnonymous(e.target.checked)} />Не говорить, от кого букет</label>
              </div>
            )}
            <label className="field">
              <span>Комментарий для флориста</span>
              <textarea className="input" rows={2} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Например: подъезд со двора, домофон 25" />
            </label>
            <div className="label">Оплата</div>
            <div className="row" style={{ gap: 8, alignItems: "stretch" }}>
              {PAYMENTS.map((p) => (
                <button key={p.code} type="button" className={`opt ${payment === p.code ? "on" : ""}`} style={{ flex: "1 1 180px" }} onClick={() => setPayment(p.code)}>
                  <b style={{ fontSize: 16 }}>{p.title}</b>
                  <span className="note">{p.note}</span>
                </button>
              ))}
            </div>
          </section>
        </div>

        {/* Итог */}
        <aside className={`summary card ${step < 3 ? "desktop-only" : ""}`}>
          <h3 style={{ fontSize: 28 }}>Ваш заказ</h3>
          <div className="sum-row"><span>Букеты × {lines.reduce((s, l) => s + l.qty, 0)}</span><span>{formatByn(totals?.priced.bouquetsKop ?? 0)}</span></div>
          {(totals?.priced.addonsKop ?? 0) > 0 && <div className="sum-row"><span>Дополнения</span><span>{formatByn(totals!.priced.addonsKop)}</span></div>}
          <div className="sum-row"><span>Открытка и подкормка</span><span style={{ color: "var(--olive)", fontWeight: 700 }}>в подарок</span></div>
          {method === "PICKUP" ? (
            <div className="sum-row"><span>Скидка за самовывоз</span><span>{formatByn(-(totals?.discountKop ?? 0))}</span></div>
          ) : (
            <div className="sum-row">
              <span>Доставка</span>
              <span>{unknown ? "доплата после уточнения" : quote?.ok ? (totals?.deliveryKop ? formatByn(totals.deliveryKop) : "бесплатно") : "по адресу"}</span>
            </div>
          )}
          <div className="sum-row" style={{ alignItems: "baseline", borderTop: "1px solid var(--line)", paddingTop: 12 }}>
            <b>Итого</b>
            <span className="sum-total">{formatByn(totals?.totalKop ?? 0)}</span>
          </div>
          <div className="notice">
            <span style={{ color: "var(--olive)" }}><LeafIcon /></span>
            <span>{SUBSTITUTION_NOTE}</span>
          </div>
          {error && error.field !== "slot" && <p className="error">{error.text}</p>}
          <button type="button" className="btn btn-lg btn-block desktop-only" disabled={!canSubmit || sending} onClick={submit}>
            {sending ? "Отправляем…" : "Оформить заказ"}
          </button>
          {!canSubmit && (
            <p className="small muted desktop-only">
              {!unknown && (!deliveryReady || (method === "DELIVERY" && address.trim().length <= 3)) ? "Укажите адрес доставки. " : ""}
              {!unknown && !slot ? "Выберите время. " : ""}
              {!recipientOk ? "Укажите телефон получателя. " : ""}
              {!customerName.trim() ? "Укажите имя и телефон." : ""}
            </p>
          )}
          <p className="small muted">Пришлём фото букета до отправки. Оплата после того, как подтвердим заказ.</p>
        </aside>
      </div>

      {/* Нижняя панель на телефоне: шаги */}
      <div className="mobile-bar">
        {step > 1 && (
          <button type="button" className="btn btn-outline" style={{ flex: "0 0 auto" }} onClick={() => setStep(step - 1)}>←</button>
        )}
        <span className="price" style={{ fontSize: 24 }}>{formatByn(totals?.totalKop ?? 0)}</span>
        {step < 3 ? (
          <button type="button" className="btn" disabled={step === 1 ? !canNext1 : !canNext2} onClick={() => { setStep(step + 1); window.scrollTo({ top: 0 }); }}>
            Далее
          </button>
        ) : (
          <button type="button" className="btn" disabled={!canSubmit || sending} onClick={submit}>
            {sending ? "Отправляем…" : "Оформить заказ"}
          </button>
        )}
      </div>
    </div>
  );
}
