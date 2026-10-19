"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState, useTransition } from "react";
import { formatByn, kopToInput, parseByn } from "@/lib/money";
import { bouquetVisibility, defaultSize, sizePriceKop, type PricedBouquet, type SizeCode } from "@/lib/pricing";
import { deleteFlower, saveFlowers } from "../../actions";

type Row = {
  id?: number;
  key: string;
  name: string;
  kind: "FLOWER" | "GREEN";
  unit: "STEM" | "PIECE";
  priceText: string;
  prevPriceKop: number | null;
  inStock: boolean;
};
type BouquetLite = {
  id: number;
  name: string;
  enabled: boolean;
  packagingKop: number;
  sizes: { code: SizeCode; items: { flowerId: number; qty: number }[] }[];
};

function toPriced(b: BouquetLite, flowers: Map<number, { name: string; priceKop: number; inStock: boolean }>): PricedBouquet {
  return {
    enabled: b.enabled,
    packagingKop: b.packagingKop,
    sizes: b.sizes.map((s) => ({
      code: s.code,
      note: "",
      items: s.items.map((i) => {
        const f = flowers.get(i.flowerId) ?? { name: "?", priceKop: 0, inStock: false };
        return { qty: i.qty, flower: { id: i.flowerId, ...f } };
      }),
    })),
  };
}

function summary(b: PricedBouquet) {
  const vis = bouquetVisibility(b);
  const s = defaultSize(b);
  return { vis, price: s ? sizePriceKop(s, b.packagingKop) : null };
}

export function FlowersEditor({
  initial,
  bouquets,
}: {
  initial: { id: number; name: string; kind: "FLOWER" | "GREEN"; unit: "STEM" | "PIECE"; priceKop: number; prevPriceKop: number | null; inStock: boolean }[];
  bouquets: BouquetLite[];
}) {
  const router = useRouter();
  const start0 = initial.map<Row>((f) => ({ ...f, key: String(f.id), priceText: kopToInput(f.priceKop) }));
  const [rows, setRows] = useState<Row[]>(start0);
  const [filter, setFilter] = useState<"all" | "FLOWER" | "GREEN">("all");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();

  const before = useMemo(() => new Map(initial.map((f) => [f.id, { name: f.name, priceKop: f.priceKop, inStock: f.inStock }])), [initial]);
  const after = useMemo(
    () => new Map(rows.filter((r) => r.id).map((r) => [r.id!, { name: r.name, priceKop: parseByn(r.priceText) ?? 0, inStock: r.inStock }])),
    [rows],
  );
  const usage = useMemo(() => {
    const m = new Map<number, number>();
    bouquets.forEach((b) => new Set(b.sizes.flatMap((s) => s.items.map((i) => i.flowerId))).forEach((id) => m.set(id, (m.get(id) ?? 0) + 1)));
    return m;
  }, [bouquets]);

  // «Что изменится на сайте»
  const changes = bouquets
    .map((b) => ({ b, was: summary(toPriced(b, before)), now: summary(toPriced(b, after)) }))
    .filter(({ was, now }) => was.price !== now.price || was.vis.visible !== now.vis.visible || (!now.vis.visible && now.vis.reason === "no-flowers"));
  const realChanges = changes.filter(({ was, now }) => was.price !== now.price || was.vis.visible !== now.vis.visible);

  function patch(key: string, p: Partial<Row>) {
    setRows((rs) => rs.map((r) => (r.key === key ? { ...r, ...p } : r)));
  }
  function bump(key: string, delta: number) {
    setRows((rs) =>
      rs.map((r) => {
        if (r.key !== key) return r;
        const k = Math.max(0, (parseByn(r.priceText) ?? 0) + delta);
        return { ...r, priceText: kopToInput(k) };
      }),
    );
  }

  function save() {
    const bad = rows.find((r) => parseByn(r.priceText) === null || !r.name.trim());
    if (bad) {
      setMsg({ ok: false, text: `Проверьте цену или название: ${bad.name || "новый цветок"}` });
      return;
    }
    start(async () => {
      const r = await saveFlowers(rows.map((x) => ({ id: x.id, name: x.name, kind: x.kind, unit: x.unit, priceKop: parseByn(x.priceText)!, inStock: x.inStock })));
      setMsg(r.ok ? { ok: true, text: "Сохранено, цены на сайте обновлены" } : { ok: false, text: r.error ?? "Ошибка" });
      if (r.ok) router.refresh();
    });
  }

  const shown = rows.filter((r) => filter === "all" || r.kind === filter);

  // Порядок строк меняют перетаскиванием (за ⠿) или стрелками; сохраняется кнопкой «Сохранить».
  const [dragKey, setDragKey] = useState<string | null>(null);
  function moveBefore(from: string, to: string) {
    if (from === to) return;
    setRows((rs) => {
      const item = rs.find((r) => r.key === from)!;
      const rest = rs.filter((r) => r.key !== from);
      const at = rest.findIndex((r) => r.key === to);
      const fromIdx = rs.findIndex((r) => r.key === from);
      const toIdx = rs.findIndex((r) => r.key === to);
      // тянем вниз — ставим после цели, вверх — перед ней
      rest.splice(fromIdx < toIdx ? at + 1 : at, 0, item);
      return rest;
    });
  }
  function step(key: string, dir: -1 | 1) {
    const i = shown.findIndex((r) => r.key === key);
    const other = shown[i + dir];
    if (other) moveBefore(key, other.key);
  }

  return (
    <>
      <div className="row between">
        <div className="stack" style={{ gap: 4 }}>
          <h1>Цветы</h1>
          <span className="muted">Розничная цена за штуку или ветку. Меняете цену здесь, и все букеты с этим цветком пересчитаются сами.</span>
          <span className="small muted">Порядок меняется перетаскиванием за ⠿ (или стрелками ▲▼), потом «Сохранить».</span>
        </div>
        <button
          type="button"
          className="btn"
          onClick={() => setRows((rs) => [...rs, { key: `new-${Date.now()}`, name: "", kind: "FLOWER", unit: "STEM", priceText: "0,00", prevPriceKop: null, inStock: true }])}
        >
          + Добавить цветок
        </button>
      </div>
      <div className="tabs">
        {(["all", "FLOWER", "GREEN"] as const).map((k) => (
          <button key={k} type="button" className={`chip ${filter === k ? "on" : ""}`} onClick={() => setFilter(k)}>
            {k === "all" ? "Все" : k === "FLOWER" ? "Цветы" : "Зелень"}
          </button>
        ))}
      </div>

      <div className="admin-grid">
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr><th aria-label="Порядок" /><th>Цветок</th><th>Цена, BYN</th><th>Было</th><th>Наличие</th><th>Букетов</th><th /></tr>
            </thead>
            <tbody>
              {shown.map((r) => (
                <tr
                  key={r.key}
                  className={`${r.inStock ? "" : "off"} ${dragKey === r.key ? "dragging" : ""}`}
                  onDragOver={(e) => {
                    if (!dragKey) return;
                    e.preventDefault();
                    if (dragKey !== r.key) moveBefore(dragKey, r.key);
                  }}
                  onDrop={(e) => e.preventDefault()}
                >
                  <td className="drag-cell">
                    <span
                      className="drag-handle"
                      draggable
                      title="Перетащите, чтобы поменять порядок"
                      onDragStart={(e) => {
                        setDragKey(r.key);
                        e.dataTransfer.effectAllowed = "move";
                        e.dataTransfer.setData("text/plain", r.key);
                        const tr = (e.currentTarget as HTMLElement).closest("tr");
                        if (tr) e.dataTransfer.setDragImage(tr, 20, 20);
                      }}
                      onDragEnd={() => setDragKey(null)}
                    >
                      ⠿
                    </span>
                    <span className="drag-steps">
                      <button type="button" aria-label="Выше" onClick={() => step(r.key, -1)}>▲</button>
                      <button type="button" aria-label="Ниже" onClick={() => step(r.key, 1)}>▼</button>
                    </span>
                  </td>
                  <td>
                    <input className="input" style={{ padding: "8px 10px", fontWeight: 700 }} value={r.name} placeholder="Название" onChange={(e) => patch(r.key, { name: e.target.value })} />
                    <div className="row small muted" style={{ gap: 6, marginTop: 4 }}>
                      <select value={r.kind} onChange={(e) => patch(r.key, { kind: e.target.value as Row["kind"] })} style={{ border: 0, background: "none" }}>
                        <option value="FLOWER">Цветы</option>
                        <option value="GREEN">Зелень</option>
                      </select>
                      ·
                      <select value={r.unit} onChange={(e) => patch(r.key, { unit: e.target.value as Row["unit"] })} style={{ border: 0, background: "none" }}>
                        <option value="STEM">за ветку</option>
                        <option value="PIECE">за штуку</option>
                      </select>
                    </div>
                  </td>
                  <td>
                    <div className="qty">
                      <button type="button" onClick={() => bump(r.key, -50)}>−</button>
                      <input className="num" style={{ border: 0, width: 70, textAlign: "center" }} value={r.priceText} inputMode="decimal" onChange={(e) => patch(r.key, { priceText: e.target.value })} />
                      <button type="button" onClick={() => bump(r.key, 50)}>+</button>
                    </div>
                  </td>
                  <td className="small muted">{r.prevPriceKop !== null ? kopToInput(r.prevPriceKop) : "—"}</td>
                  <td>
                    <button type="button" className={`chip ${r.inStock ? "on" : ""}`} style={{ minHeight: 34, padding: "4px 12px" }} onClick={() => patch(r.key, { inStock: !r.inStock })}>
                      {r.inStock ? "Есть" : "Нет"}
                    </button>
                  </td>
                  <td>{r.id ? usage.get(r.id) ?? 0 : 0}</td>
                  <td>
                    <button
                      type="button"
                      className="btn-ghost"
                      aria-label="Удалить цветок"
                      onClick={() => {
                        if (!r.id) return setRows((rs) => rs.filter((x) => x.key !== r.key));
                        start(async () => {
                          const res = await deleteFlower(r.id!);
                          if (res.ok) setRows((rs) => rs.filter((x) => x.key !== r.key));
                          else setMsg({ ok: false, text: res.error ?? "Ошибка" });
                        });
                      }}
                    >
                      ×
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="card stack" style={{ gap: 10, position: "sticky", top: 16 }}>
          <span className="label" style={{ fontSize: 18 }}>Что изменится на сайте</span>
          {realChanges.length === 0 ? (
            <span className="small muted">Пока ничего. Поменяйте цену или наличие слева.</span>
          ) : (
            <span className="small muted">После сохранения изменится {realChanges.length} {realChanges.length === 1 ? "букет" : realChanges.length < 5 ? "букета" : "букетов"}.</span>
          )}
          {changes.map(({ b, was, now }) => (
            <div key={b.id} className="sum-row small" style={{ alignItems: "baseline" }}>
              <span style={{ fontWeight: 700 }}>{b.name}</span>
              {!now.vis.visible && now.vis.reason === "no-flowers" ? (
                <span className="pill warn">{was.vis.visible ? "Скроется" : "Скрыт"}: нет {now.vis.missing.join(", ").toLowerCase()}</span>
              ) : !was.vis.visible && now.vis.visible ? (
                <span className="pill ok">Вернётся на сайт</span>
              ) : (
                <span>
                  {was.price !== null ? formatByn(was.price) : "—"} → <b>{now.price !== null ? formatByn(now.price) : "—"}</b>{" "}
                  {was.price !== null && now.price !== null && (
                    <span style={{ color: now.price > was.price ? "var(--danger)" : "var(--olive)" }}>
                      {now.price > was.price ? "+" : "−"}
                      {formatByn(Math.abs(now.price - was.price)).replace(" BYN", "")}
                    </span>
                  )}
                </span>
              )}
            </div>
          ))}
          {msg && <p className={msg.ok ? "pill ok" : "error"}>{msg.text}</p>}
          <button type="button" className="btn btn-lg" disabled={pending} onClick={save}>{pending ? "Сохраняем…" : "Сохранить и обновить сайт"}</button>
          <span className="small muted">
            Цена букета = цветы по цене из этой таблицы × количество + упаковка. Если цветка нет в наличии, букет скрывается с сайта и вернётся сам, когда цветок появится.
          </span>
        </div>
      </div>
    </>
  );
}
