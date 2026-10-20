"use client";

import { useState } from "react";
import { kopToInput, parseByn } from "@/lib/money";

// Небольшие поля ввода для админки: число и сумма в BYN (хранится в копейках).
export function Num({ value, onChange, step = 1, width = 90 }: { value: number; onChange: (n: number) => void; step?: number; width?: number }) {
  return <input className="num" style={{ width }} type="number" step={step} value={Number.isFinite(value) ? value : ""} onChange={(e) => onChange(parseFloat(e.target.value))} />;
}

export function Money({ kop, onChange }: { kop: number; onChange: (k: number) => void }) {
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
