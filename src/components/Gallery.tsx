"use client";

import { useState } from "react";

const VIEWS = ["Общий вид", "Ближе", "Сбоку", "Детали"];

export function Gallery({ photos, name, hit }: { photos: string[]; name: string; hit: boolean }) {
  const [i, setI] = useState(0);
  return (
    <div className="gallery">
      <div className="gallery-main">
        <img src={photos[i]} alt={name} />
        {hit && <span className="badge" style={{ left: 16, top: 16 }}>Хит недели</span>}
        <span className="badge" style={{ left: 16, top: "auto", bottom: 16, fontWeight: 400 }}>
          {VIEWS[i] ?? `Фото ${i + 1}`}
        </span>
      </div>
      {photos.length > 1 && (
        <div className="thumbs">
          {photos.map((p, n) => (
            <button key={p + n} type="button" className={n === i ? "on" : ""} aria-label={VIEWS[n] ?? `Фото ${n + 1}`} onClick={() => setI(n)}>
              <img src={p} alt="" />
            </button>
          ))}
        </div>
      )}
      <div className="small muted">Перед отправкой пришлём фото именно вашего букета.</div>
    </div>
  );
}
