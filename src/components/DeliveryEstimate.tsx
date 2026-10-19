"use client";

import { useState } from "react";
import type { DistrictOption } from "@/lib/districts";
import { formatByn } from "@/lib/money";

// Блок «Куда доставить?» на странице букета: цена доставки по району.
export function DeliveryEstimate({
  districts,
  freeFromKop,
  maxOutsideKm,
  leadHours,
}: {
  districts: DistrictOption[];
  freeFromKop: number;
  maxOutsideKm: number;
  leadHours: number;
}) {
  const [name, setName] = useState("");
  const picked = districts.find((d) => d.name === name);

  return (
    <div className="card stack">
      <div className="row between" style={{ alignItems: "baseline" }}>
        <span className="label">Куда доставить?</span>
        {picked && (
          <span style={{ fontFamily: "var(--serif)", fontSize: 24, fontWeight: 700 }}>
            {picked.priceKop === 0 ? "Доставка бесплатно" : `Доставка ${formatByn(picked.priceKop)}`}
          </span>
        )}
      </div>
      <select className="input" value={name} onChange={(e) => setName(e.target.value)} aria-label="Район доставки">
        <option value="">Выберите район</option>
        {districts.map((d) => (
          <option key={d.name} value={d.name}>
            {d.name} — {d.priceKop === 0 ? "бесплатно" : formatByn(d.priceKop)}
          </option>
        ))}
      </select>
      {picked && <div className="small muted">Привезём из магазина {picked.shopName}.</div>}
      <div className="small muted">
        Возим по Минску и до {maxOutsideKm} км за МКАД. Бесплатно от {formatByn(freeFromKop)}. Привезём от {leadHours} часов.
      </div>
    </div>
  );
}
