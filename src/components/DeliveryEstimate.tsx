"use client";

import { useState } from "react";
import type { DistrictPreset } from "@/lib/defaults";
import { formatByn } from "@/lib/money";

export type QuoteResponse =
  | {
      ok: true;
      shop: { code: string; name: string; address: string };
      km: number;
      zone: number;
      outsideMkadKm: number;
      priceKop: number;
      baseKop: number;
      free: boolean;
      leftToFreeKop: number;
      point: { lat: number; lng: number };
    }
  | { ok: false; reason: string };

export async function fetchQuote(input: {
  address?: string;
  point?: { lat: number; lng: number };
  subtotalKop: number;
}): Promise<QuoteResponse> {
  const res = await fetch("/api/delivery", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  return res.json();
}

// Блок «Куда доставить?» на странице букета: примерная цена доставки.
export function DeliveryEstimate({
  subtotalKop,
  districts,
  freeFromKop,
  leadHours,
}: {
  subtotalKop: number;
  districts: DistrictPreset[];
  freeFromKop: number;
  leadHours: number;
}) {
  const [address, setAddress] = useState("");
  const [district, setDistrict] = useState<string | null>(null);
  const [quote, setQuote] = useState<QuoteResponse | null>(null);
  const [busy, setBusy] = useState(false);

  async function run(input: { address?: string; point?: { lat: number; lng: number } }) {
    setBusy(true);
    try {
      setQuote(await fetchQuote({ ...input, subtotalKop }));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="card stack">
      <div className="row between" style={{ alignItems: "baseline" }}>
        <span className="label">Куда доставить?</span>
        {quote?.ok && (
          <span style={{ fontFamily: "var(--serif)", fontSize: 24, fontWeight: 700 }}>
            {quote.priceKop === 0 ? "Доставка бесплатно" : `Доставка ${formatByn(quote.priceKop)}`}
          </span>
        )}
      </div>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          setDistrict(null);
          if (address.trim()) run({ address });
        }}
      >
        <input
          className="input"
          value={address}
          onChange={(e) => setAddress(e.target.value)}
          onBlur={() => address.trim() && !busy && run({ address })}
          placeholder="Улица и дом, например пр. Независимости, 180"
        />
      </form>
      <div className="small muted">или выберите район:</div>
      <div className="row" style={{ gap: 6 }}>
        {districts.map((d) => (
          <button
            key={d.name}
            type="button"
            className={`chip ${district === d.name ? "on" : ""}`}
            style={{ fontSize: 14 }}
            onClick={() => {
              setDistrict(d.name);
              run({ point: { lat: d.lat, lng: d.lng } });
            }}
          >
            {d.name}
          </button>
        ))}
      </div>
      {quote && !quote.ok && (
        <div className="error">
          {quote.reason === "too-far" ? "Так далеко мы не возим, позвоните нам." : "Не нашли такой адрес. Уточните улицу и дом."}
        </div>
      )}
      {quote?.ok && (
        <div className="small muted">
          Зона {quote.zone}, ближайший магазин {quote.shop.name}, ~{quote.km} км
          {quote.outsideMkadKm > 0 ? `, ${quote.outsideMkadKm} км за МКАД` : ""}.
        </div>
      )}
      <div className="small muted">
        Бесплатно от {formatByn(freeFromKop)}. Привезём от {leadHours} часов. Точную цену посчитаем по адресу в корзине.
      </div>
    </div>
  );
}
