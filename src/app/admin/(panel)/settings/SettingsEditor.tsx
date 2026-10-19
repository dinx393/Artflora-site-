"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import type { DeliveryConfig } from "@/lib/delivery";
import type { DistrictPreset, ShopSettings } from "@/lib/defaults";
import { kopToInput, parseByn } from "@/lib/money";
import { saveAddons, saveSettings } from "../../actions";

type Addon = { id?: number; code: string; name: string; priceKop: number; included: boolean; active: boolean };

function Num({ value, onChange, step = 1, width = 90 }: { value: number; onChange: (n: number) => void; step?: number; width?: number }) {
  return <input className="num" style={{ width }} type="number" step={step} value={Number.isFinite(value) ? value : ""} onChange={(e) => onChange(parseFloat(e.target.value))} />;
}

function Money({ kop, onChange }: { kop: number; onChange: (k: number) => void }) {
  const [text, setText] = useState(kopToInput(kop));
  return (
    <input
      className="num"
      value={text}
      inputMode="decimal"
      onChange={(e) => {
        setText(e.target.value);
        const k = parseByn(e.target.value);
        if (k !== null) onChange(k);
      }}
    />
  );
}

export function SettingsEditor(props: { shop: ShopSettings; delivery: DeliveryConfig; districts: DistrictPreset[]; addons: Addon[] }) {
  const router = useRouter();
  const [shop, setShop] = useState(props.shop);
  const [d, setD] = useState(props.delivery);
  const [districts, setDistricts] = useState(props.districts);
  const [addons, setAddons] = useState(props.addons);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  function save() {
    start(async () => {
      const a = await saveSettings({ shop, delivery: d, districts });
      const b = a.ok ? await saveAddons(addons) : a;
      setMsg(b.ok ? { ok: true, text: "Настройки сохранены" } : { ok: false, text: b.error ?? "Ошибка" });
      if (b.ok) router.refresh();
    });
  }

  const field = (label: string, key: keyof ShopSettings, placeholder?: string) => (
    <label className="field">
      <span>{label}</span>
      <input className="input" value={String(shop[key])} placeholder={placeholder} onChange={(e) => setShop({ ...shop, [key]: e.target.value })} />
    </label>
  );

  return (
    <>
      <div className="row between">
        <h1>Настройки</h1>
        <button type="button" className="btn" disabled={pending} onClick={save}>{pending ? "Сохраняем…" : "Сохранить"}</button>
      </div>
      {msg && <p className={msg.ok ? "pill ok" : "error"} style={{ alignSelf: "flex-start" }}>{msg.text}</p>}

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Контакты</span>
        <div className="grid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: 12 }}>
          {field("Телефон в шапке", "phone")}
          {field("Ссылка Viber", "viber", "viber://chat?number=%2B375...")}
          {field("Ссылка Telegram", "telegram", "https://t.me/...")}
          {field("Ссылка WhatsApp (пусто — по номеру телефона)", "whatsapp", "https://wa.me/375...")}
          {field("Ссылка Instagram", "instagram")}
          {field("Почта для писем о заказах (можно несколько через запятую)", "orderEmail", "zakaz@artflora.by")}
        </div>
        {field("Строка над каталогом (акция)", "promoLine", "До Дня матери 14 октября · предзаказ −10%")}
        <label className="field">
          <span>Реквизиты продавца в подвале (обязательно по закону РБ)</span>
          <textarea className="input" rows={2} value={shop.sellerDetails} onChange={(e) => setShop({ ...shop, sellerDetails: e.target.value })} />
        </label>
        <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}>
          <span>Принимаем заказ на сегодня минимум за, часов:</span>
          <Num value={shop.leadHours} step={0.5} onChange={(n) => setShop({ ...shop, leadHours: n })} />
        </label>
      </div>

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Доставка</span>
        <span className="small muted">Зоны считаются по расстоянию от ближайшего магазина. За МКАД: последняя зона + доплата за каждый км.</span>
        <table className="table" style={{ maxWidth: 420 }}>
          <thead><tr><th>Зона</th><th>До, км</th><th>Цена, BYN</th><th /></tr></thead>
          <tbody>
            {d.zones.map((z, i) => (
              <tr key={i}>
                <td>{i + 1}</td>
                <td><Num value={z.maxKm} step={0.5} onChange={(n) => setD({ ...d, zones: d.zones.map((x, j) => (j === i ? { ...x, maxKm: n } : x)) })} /></td>
                <td><Money kop={z.priceKop} onChange={(k) => setD({ ...d, zones: d.zones.map((x, j) => (j === i ? { ...x, priceKop: k } : x)) })} /></td>
                <td><button type="button" className="btn-ghost" onClick={() => setD({ ...d, zones: d.zones.filter((_, j) => j !== i) })}>×</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <button type="button" className="btn-ghost small" style={{ alignSelf: "flex-start" }} onClick={() => setD({ ...d, zones: [...d.zones, { maxKm: 999, priceKop: 2000 }] })}>+ Добавить зону</button>
        <div className="stack" style={{ gap: 8 }}>
          <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Бесплатная доставка от, BYN</span><Money kop={d.freeFromKop} onChange={(k) => setD({ ...d, freeFromKop: k })} /></label>
          <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>За МКАД, BYN за км</span><Money kop={d.outsideMkadPerKmKop} onChange={(k) => setD({ ...d, outsideMkadPerKmKop: k })} /></label>
          <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Скидка за самовывоз, %</span><Num value={d.pickupDiscountPct} onChange={(n) => setD({ ...d, pickupDiscountPct: n })} /></label>
          <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Возим не дальше, км за МКАД</span><Num value={d.maxOutsideMkadKm} onChange={(n) => setD({ ...d, maxOutsideMkadKm: n })} /></label>
          <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Радиус МКАД от центра, км</span><Num value={d.mkadRadiusKm} step={0.1} onChange={(n) => setD({ ...d, mkadRadiusKm: n })} /></label>
        </div>
      </div>

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Магазины</span>
        <span className="small muted">Координаты можно взять на Яндекс Картах: правый клик по точке → «Что здесь?».</span>
        {d.shops.map((s, i) => {
          const set = (p: Partial<typeof s>) => setD({ ...d, shops: d.shops.map((x, j) => (j === i ? { ...x, ...p } : x)) });
          return (
            <div key={s.code} className="row" style={{ gap: 8 }}>
              <input className="input" style={{ width: 140 }} value={s.name} onChange={(e) => set({ name: e.target.value })} />
              <input className="input" style={{ width: 240 }} value={s.address} onChange={(e) => set({ address: e.target.value })} />
              <input className="input" style={{ width: 130 }} value={s.hours} onChange={(e) => set({ hours: e.target.value })} />
              <Num value={s.location.lat} step={0.0001} width={110} onChange={(n) => set({ location: { ...s.location, lat: n } })} />
              <Num value={s.location.lng} step={0.0001} width={110} onChange={(n) => set({ location: { ...s.location, lng: n } })} />
            </div>
          );
        })}
      </div>

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Районы для быстрого выбора</span>
        {districts.map((r, i) => {
          const set = (p: Partial<DistrictPreset>) => setDistricts(districts.map((x, j) => (j === i ? { ...x, ...p } : x)));
          return (
            <div key={i} className="row" style={{ gap: 8 }}>
              <input className="input" style={{ width: 180 }} value={r.name} onChange={(e) => set({ name: e.target.value })} />
              <Num value={r.lat} step={0.0001} width={110} onChange={(n) => set({ lat: n })} />
              <Num value={r.lng} step={0.0001} width={110} onChange={(n) => set({ lng: n })} />
              <button type="button" className="btn-ghost" onClick={() => setDistricts(districts.filter((_, j) => j !== i))}>×</button>
            </div>
          );
        })}
        <button type="button" className="btn-ghost small" style={{ alignSelf: "flex-start" }} onClick={() => setDistricts([...districts, { name: "Новый район", lat: 53.9, lng: 27.56 }])}>+ Добавить район</button>
      </div>

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Дополнения к букету</span>
        <table className="table">
          <thead><tr><th>Название</th><th>Цена, BYN</th><th>Входит в комплект</th><th>Показывать</th></tr></thead>
          <tbody>
            {addons.map((a, i) => {
              const set = (p: Partial<Addon>) => setAddons(addons.map((x, j) => (j === i ? { ...x, ...p } : x)));
              return (
                <tr key={a.id ?? a.code}>
                  <td><input className="input" style={{ padding: "8px 10px" }} value={a.name} onChange={(e) => set({ name: e.target.value })} /></td>
                  <td><Money kop={a.priceKop} onChange={(k) => set({ priceKop: k })} /></td>
                  <td><input type="checkbox" checked={a.included} onChange={(e) => set({ included: e.target.checked })} /></td>
                  <td><button type="button" className={`switch ${a.active ? "on" : ""}`} onClick={() => set({ active: !a.active })} aria-label="Показывать" /></td>
                </tr>
              );
            })}
          </tbody>
        </table>
        <button
          type="button"
          className="btn-ghost small"
          style={{ alignSelf: "flex-start" }}
          onClick={() => setAddons([...addons, { code: `addon-${Date.now().toString(36)}`, name: "Новое дополнение", priceKop: 0, included: false, active: false }])}
        >
          + Добавить
        </button>
      </div>
    </>
  );
}
