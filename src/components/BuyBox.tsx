"use client";

import { useState } from "react";
import { useCart } from "./CartProvider";
import { AddToCartButton } from "./AddToCartButton";
import { Guarantee } from "./Guarantee";
import { LeafIcon } from "./icons";
import { formatByn, percentOff } from "@/lib/money";
import { Byn } from "@/components/Byn";

type Size = { code: "S" | "M" | "L"; note: string; priceKop: number };
type Addon = { id: number; name: string; priceKop: number; included: boolean };

export const SUBSTITUTION_NOTE =
  "Цветы — живой материал. Если нужного цветка или оттенка не окажется, флорист подберёт близкую замену и согласует её с вами до отправки.";

export function BuyBox({
  bouquetId,
  sizes,
  defaultSize,
  addons,
  pickupPct,
  viber,
  onSizeChange,
  afterSizes,
}: {
  bouquetId: number;
  sizes: Size[];
  defaultSize: "S" | "M" | "L";
  addons: Addon[];
  pickupPct: number;
  viber: string;
  onSizeChange?: (priceKop: number) => void;
  afterSizes?: React.ReactNode; // «Этот же состав в другом виде» или «Похожие букеты» сразу под размерами
}) {
  const { cart, setCart } = useCart();
  const [size, setSize] = useState(defaultSize);
  const [cardText, setCardText] = useState("");
  const current = sizes.find((s) => s.code === size) ?? sizes[0];
  const inCart = (id: number) => cart.addons.some((a) => a.addonId === id);

  function toggleAddon(id: number) {
    setCart((c) =>
      inCart(id) ? { ...c, addons: c.addons.filter((a) => a.addonId !== id) } : { ...c, addons: [...c.addons, { addonId: id, qty: 1 }] },
    );
  }

  return (
    <>
      <div className="row" style={{ alignItems: "baseline", gap: 14 }}>
        <span className="price" style={{ fontSize: 40 }}><Byn kop={current.priceKop} /></span>
        <span className="small muted">Самовывоз: {formatByn(current.priceKop - percentOff(current.priceKop, pickupPct))}</span>
      </div>

      {sizes.length > 1 && (
        <div className="stack" style={{ gap: 8 }}>
          <div className="label">Размер</div>
          <div className="row" style={{ gap: 8, flexWrap: "nowrap" }}>
            {sizes.map((s) => (
              <button
                key={s.code}
                type="button"
                className={`opt ${s.code === size ? "on" : ""}`}
                onClick={() => {
                  setSize(s.code);
                  onSizeChange?.(s.priceKop);
                }}
              >
                <b>
                  {s.code} · {formatByn(s.priceKop)}
                </b>
                {s.note && <span className="note">{s.note}</span>}
              </button>
            ))}
          </div>
        </div>
      )}

      {afterSizes}

      <div className="stack" style={{ gap: 8 }}>
        <div className="label">К букету</div>
        <div className="row" style={{ gap: 8, alignItems: "stretch" }}>
          <div className="opt on" style={{ flex: "1 1 150px", background: "var(--blush)", cursor: "default" }}>
            <span style={{ fontWeight: 700 }}>Открытка с текстом</span>
            <span className="ok">Добавлена · бесплатно</span>
          </div>
          {addons
            .filter((a) => !a.included)
            .map((a) => (
              <button key={a.id} type="button" className={`opt ${inCart(a.id) ? "on" : ""}`} style={{ flex: "1 1 150px" }} onClick={() => toggleAddon(a.id)}>
                <span style={{ fontWeight: 700 }}>{a.name}</span>
                <span className={inCart(a.id) ? "ok" : "note"}>
                  {inCart(a.id) ? `Добавлено · ${formatByn(a.priceKop)}` : formatByn(a.priceKop)}
                </span>
              </button>
            ))}
          {addons
            .filter((a) => a.included)
            .map((a) => (
              <div key={a.id} className="opt" style={{ flex: "1 1 150px", borderStyle: "dashed", cursor: "default" }}>
                <span style={{ fontWeight: 700 }}>{a.name}</span>
                <span className="ok">Уже в комплекте</span>
              </div>
            ))}
        </div>
        <label className="field">
          <span>Текст открытки</span>
          <textarea
            className="input"
            rows={2}
            maxLength={500}
            value={cardText}
            onChange={(e) => setCardText(e.target.value)}
            placeholder="Мама, с днём рождения! Люблю тебя"
          />
        </label>
      </div>

      <AddToCartButton bouquetId={bouquetId} size={size} cardText={cardText} large />

      <Guarantee />

      <div className="notice">
        <span style={{ color: "var(--olive)", marginTop: 1 }}>
          <LeafIcon />
        </span>
        <span>{SUBSTITUTION_NOTE}</span>
      </div>
      <a href={viber} style={{ alignSelf: "flex-start", fontWeight: 700, fontSize: 15 }}>
        Есть вопрос? Напишите флористу в Viber →
      </a>
    </>
  );
}
