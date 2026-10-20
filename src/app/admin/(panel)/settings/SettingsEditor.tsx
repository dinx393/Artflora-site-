"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import type { ShopSettings } from "@/lib/defaults";
import type { ColorOption } from "@/lib/occasions";
import { Money, Num } from "../fields";
import { saveAddons, saveSettings, testOrderEmail } from "../../actions";

type Addon = { id?: number; code: string; name: string; priceKop: number; included: boolean; active: boolean };

function moveItem<T>(list: T[], from: number, to: number): T[] {
  const next = [...list];
  const [x] = next.splice(from, 1);
  next.splice(to, 0, x);
  return next;
}

export function SettingsEditor(props: {
  shop: ShopSettings;
  addons: Addon[];
  bouquets: { slug: string; name: string }[];
  smtpReady: boolean;
  colors: ColorOption[];
}) {
  const router = useRouter();
  const [shop, setShop] = useState(props.shop);
  const [colors, setColors] = useState(props.colors);
  const [addons, setAddons] = useState(props.addons);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();
  const [testing, startTest] = useTransition();
  const [mailMsg, setMailMsg] = useState<{ ok: boolean; text: string } | null>(null);

  function testMail() {
    startTest(async () => {
      const r = await testOrderEmail(shop.orderEmail);
      setMailMsg(r.ok ? { ok: true, text: `Письмо отправлено на ${shop.orderEmail}. Проверьте почту (и папку «Спам»).` } : { ok: false, text: r.error ?? "Ошибка" });
    });
  }

  function save() {
    start(async () => {
      try {
        const a = await saveSettings({ shop, colors });
        if (!a.ok) return setMsg({ ok: false, text: a.error ?? "Ошибка" });
        const b = await saveAddons(addons);
        setMsg(b.ok ? { ok: true, text: "Настройки сохранены" } : { ok: false, text: b.error ?? "Ошибка" });
        if (b.ok && b.addons) {
          setAddons(b.addons); // новые дополнения получили номера в базе
          router.refresh();
        }
      } catch {
        setMsg({ ok: false, text: "Не удалось сохранить: нет связи с сервером. Ничего не потеряно, нажмите «Сохранить» ещё раз." });
      }
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
        <div className="row" style={{ gap: 12, alignItems: "center" }}>
          <button type="button" className="btn btn-outline" disabled={testing} onClick={testMail}>
            {testing ? "Отправляем…" : "Отправить пробное письмо"}
          </button>
          <span className="small muted">
            {props.smtpReady ? "Почта-отправитель подключена." : "Почта-отправитель ещё не подключена (SMTP в файле .env, см. README)."}
          </span>
        </div>
        {mailMsg && <p className={mailMsg.ok ? "pill ok" : "error"} style={{ alignSelf: "flex-start" }}>{mailMsg.text}</p>}
        {field("Строка над каталогом (акция)", "promoLine", "До Дня матери 14 октября · предзаказ −10%")}
        <label className="field">
          <span>«Букет дня» на главной (фото лучше вертикальное, букет по центру)</span>
          <select className="input" value={shop.dayBouquet} onChange={(e) => setShop({ ...shop, dayBouquet: e.target.value })}>
            <option value="">Сам выберет: первый хит</option>
            {props.bouquets.map((b) => (
              <option key={b.slug} value={b.slug}>{b.name}</option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>Реквизиты продавца в подвале (обязательно по закону РБ)</span>
          <textarea className="input" rows={2} value={shop.sellerDetails} onChange={(e) => setShop({ ...shop, sellerDetails: e.target.value })} />
        </label>
        <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}>
          <span>Принимаем заказ на сегодня минимум за, часов:</span>
          <Num value={shop.leadHours} step={0.5} onChange={(n) => setShop({ ...shop, leadHours: n })} />
        </label>
      </div>

      <p className="small muted">
        Зоны доставки, районы и магазины — в разделе <Link href="/admin/delivery" style={{ fontWeight: 700 }}>«Доставка»</Link>, там они на карте.
      </p>

      <div className="card stack" style={{ gap: 12 }}>
        <span className="label" style={{ fontSize: 18 }}>Цвета букетов</span>
        <span className="small muted">
          Из них выбирают цвет в карточке букета, и по ним фильтрует каталог. Нажмите на кружок, чтобы выбрать оттенок. В каталоге видны
          только цвета, которые есть хотя бы у одного букета.
        </span>
        {colors.map((c, i) => {
          const set = (p: Partial<ColorOption>) => setColors(colors.map((x, j) => (j === i ? { ...x, ...p } : x)));
          const hex = /^#[0-9a-f]{6}$/i.test(c.swatch);
          return (
            <div key={c.slug} className="row" style={{ gap: 8, alignItems: "center" }}>
              {hex ? (
                <input type="color" className="color-pick" value={c.swatch} onChange={(e) => set({ swatch: e.target.value })} aria-label={`Оттенок: ${c.label}`} />
              ) : (
                <i className="swatch" style={{ background: c.swatch, width: 36, height: 36 }} title="Микс — разноцветный кружок" />
              )}
              <input className="input" style={{ width: 220 }} value={c.label} maxLength={30} onChange={(e) => set({ label: e.target.value })} />
              <button type="button" className="btn-ghost" disabled={i === 0} onClick={() => setColors(moveItem(colors, i, i - 1))} aria-label="Выше">▲</button>
              <button type="button" className="btn-ghost" disabled={i === colors.length - 1} onClick={() => setColors(moveItem(colors, i, i + 1))} aria-label="Ниже">▼</button>
              <button type="button" className="btn-ghost" onClick={() => setColors(colors.filter((_, j) => j !== i))} aria-label="Убрать">×</button>
            </div>
          );
        })}
        <button
          type="button"
          className="btn-ghost small"
          style={{ alignSelf: "flex-start" }}
          onClick={() => setColors([...colors, { slug: `c${Date.now().toString(36)}`, label: "Новый цвет", swatch: "#88aa66" }])}
        >
          + Добавить цвет
        </button>
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
