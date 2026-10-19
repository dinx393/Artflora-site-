"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useMemo, useState, useTransition } from "react";
import { formatByn, kopToInput, parseByn } from "@/lib/money";
import { COLORS, FORMATS, OCCASIONS, type FormatCode } from "@/lib/occasions";
import { cloneToFormat, deleteBouquet, saveBouquet, uploadPhoto, type BouquetInput } from "../../../actions";

type Flower = { id: number; name: string; kind: "FLOWER" | "GREEN"; unit: "STEM" | "PIECE"; priceKop: number; inStock: boolean };
type SizeCode = "S" | "M" | "L";
type Initial = Omit<BouquetInput, "sizes"> & {
  format: FormatCode;
  sizes: { code: SizeCode; note: string; items: { flowerId: number; qty: number }[] }[];
};

export function BouquetEditor({
  initial,
  flowers,
  collections,
  siblings,
}: {
  initial: Initial;
  flowers: Flower[];
  collections: { id: number; name: string }[];
  siblings: { id: number; name: string; format: FormatCode }[];
}) {
  const router = useRouter();
  const [b, setB] = useState(initial);
  const [sizeCode, setSizeCode] = useState<SizeCode>("M");
  const [packagingText, setPackagingText] = useState(kopToInput(initial.packagingKop));
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [pending, start] = useTransition();
  const [uploading, setUploading] = useState(false);
  const [flowerQuery, setFlowerQuery] = useState("");
  const byId = useMemo(() => new Map(flowers.map((f) => [f.id, f])), [flowers]);

  const size = b.sizes.find((s) => s.code === sizeCode)!;
  const flowersKop = (s: Initial["sizes"][number]) => s.items.reduce((sum, i) => sum + (byId.get(i.flowerId)?.priceKop ?? 0) * i.qty, 0);
  const priceOf = (s: Initial["sizes"][number]) => (s.items.length ? flowersKop(s) + b.packagingKop : null);
  const missing = (s: Initial["sizes"][number]) => s.items.filter((i) => !byId.get(i.flowerId)?.inStock).map((i) => byId.get(i.flowerId)?.name);

  function patchSize(fn: (s: Initial["sizes"][number]) => Initial["sizes"][number]) {
    setB((x) => ({ ...x, sizes: x.sizes.map((s) => (s.code === sizeCode ? fn(s) : s)) }));
  }
  function setQty(flowerId: number, qty: number) {
    patchSize((s) => ({ ...s, items: s.items.map((i) => (i.flowerId === flowerId ? { ...i, qty: Math.max(1, qty) } : i)) }));
  }

  async function onUpload(files: FileList | null) {
    if (!files?.length) return;
    setUploading(true);
    const added: string[] = [];
    for (const file of Array.from(files)) {
      const fd = new FormData();
      fd.set("file", file);
      const r = await uploadPhoto(fd);
      if (r.ok && r.path) added.push(r.path);
      else setMsg({ ok: false, text: r.error ?? "Не удалось загрузить фото" });
    }
    setB((x) => ({ ...x, photos: [...x.photos, ...added] }));
    setUploading(false);
  }

  function save() {
    const pkg = parseByn(packagingText);
    if (pkg === null) {
      setMsg({ ok: false, text: "Проверьте стоимость упаковки" });
      return;
    }
    start(async () => {
      const r = await saveBouquet({ ...b, packagingKop: pkg });
      setMsg(r.ok ? { ok: true, text: "Сохранено, сайт обновлён" } : { ok: false, text: r.error ?? "Ошибка" });
      if (r.ok) router.refresh();
    });
  }

  const used = new Set(size.items.map((i) => i.flowerId));
  const norm = (t: string) => t.toLowerCase().replace(/ё/g, "е").trim();
  const candidates = flowers.filter((f) => !used.has(f.id) && norm(f.name).includes(norm(flowerQuery)));
  function addFlower(flowerId: number) {
    patchSize((s) => ({ ...s, items: [...s.items, { flowerId, qty: 1 }] }));
    setFlowerQuery("");
  }
  const sum = flowersKop(size);

  return (
    <>
      <div className="row between">
        <div className="stack" style={{ gap: 6 }}>
          <Link href="/admin/bouquets" className="small">← Все букеты</Link>
          <h1>{b.name || "Без названия"}</h1>
        </div>
        <div className="row">
          <span className="row small" style={{ gap: 8 }}>
            <button type="button" className={`switch ${b.enabled ? "on" : ""}`} onClick={() => setB({ ...b, enabled: !b.enabled })} aria-label="Показывать на сайте" />
            {b.enabled ? "На сайте" : "Выключен"}
          </span>
          <button type="button" className="btn btn-outline" onClick={() => { setB(initial); setPackagingText(kopToInput(initial.packagingKop)); setMsg(null); }}>Отменить</button>
          <button type="button" className="btn" disabled={pending} onClick={save}>{pending ? "Сохраняем…" : "Сохранить"}</button>
        </div>
      </div>
      {msg && <p className={msg.ok ? "pill ok" : "error"} style={{ alignSelf: "flex-start" }}>{msg.text}</p>}

      <div className="admin-grid">
        <div className="stack" style={{ gap: 20 }}>
          <div className="photo-row">
            {b.photos.map((p, i) => (
              <div key={p + i} className="ph" style={i === 0 ? { outline: "2px solid var(--cherry)" } : undefined}>
                <img src={p} alt="" />
                <button type="button" aria-label="Убрать фото" onClick={() => setB({ ...b, photos: b.photos.filter((_, n) => n !== i) })}>×</button>
                {i > 0 && (
                  <button type="button" style={{ left: 4, right: "auto", width: "auto", borderRadius: 999, padding: "0 8px", fontSize: 12 }} onClick={() => setB({ ...b, photos: [p, ...b.photos.filter((_, n) => n !== i)] })}>
                    главное
                  </button>
                )}
              </div>
            ))}
            <label className="ph" style={{ display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer", fontWeight: 700 }}>
              {uploading ? "Загрузка…" : "+ Фото"}
              <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => onUpload(e.target.files)} />
            </label>
          </div>

          <label className="field"><span>Название</span><input className="input" value={b.name} onChange={(e) => setB({ ...b, name: e.target.value })} /></label>
          <label className="field">
            <span>Адрес страницы: artflora.by/bouquet/…</span>
            <input className="input" value={b.slug} onChange={(e) => setB({ ...b, slug: e.target.value.toLowerCase() })} />
          </label>
          <label className="field">
            <span>Описание</span>
            <textarea className="input" rows={3} value={b.description} placeholder="Короткое описание для покупателя" onChange={(e) => setB({ ...b, description: e.target.value })} />
          </label>
          <div className="stack" style={{ gap: 8 }}>
            <span className="label">Поводы</span>
            <div className="row" style={{ gap: 6 }}>
              {OCCASIONS.map((o) => {
                const on = b.occasions.includes(o.slug);
                return (
                  <button key={o.slug} type="button" className={`chip ${on ? "on" : ""}`} onClick={() => setB({ ...b, occasions: on ? b.occasions.filter((x) => x !== o.slug) : [...b.occasions, o.slug] })}>
                    {o.label}
                  </button>
                );
              })}
            </div>
          </div>
          <div className="stack" style={{ gap: 8 }}>
            <span className="label">Цвет букета <span className="small muted">(для фильтра в каталоге, можно несколько)</span></span>
            <div className="row" style={{ gap: 6 }}>
              {COLORS.map((c) => {
                const on = b.colors.includes(c.slug);
                return (
                  <button key={c.slug} type="button" className={`chip ${on ? "on" : ""}`} onClick={() => setB({ ...b, colors: on ? b.colors.filter((x) => x !== c.slug) : [...b.colors, c.slug] })}>
                    <i className="swatch" style={{ background: c.swatch }} aria-hidden="true" />
                    {c.label}
                  </button>
                );
              })}
            </div>
          </div>
          <div className="row" style={{ gap: 12 }}>
            <label className="field" style={{ flex: "1 1 200px" }}>
              <span>Формат</span>
              <select className="input" value={b.format} onChange={(e) => setB({ ...b, format: e.target.value as FormatCode })}>
                {Object.entries(FORMATS).map(([k, f]) => <option key={k} value={k}>{f.label}</option>)}
              </select>
            </label>
            <label className="check" style={{ marginTop: 22 }}>
              <input type="checkbox" checked={b.isHit} onChange={(e) => setB({ ...b, isHit: e.target.checked })} /> Хит недели
            </label>
            <label className="check" style={{ marginTop: 22 }}>
              <input type="checkbox" checked={b.isExclusive} onChange={(e) => setB({ ...b, isExclusive: e.target.checked })} /> Эксклюзив недели
            </label>
          </div>

          <div className="card stack" style={{ gap: 14 }}>
            <div className="row between">
              <span className="label" style={{ fontSize: 18 }}>Состав</span>
              <div className="row" style={{ gap: 6 }}>
                <span className="small muted">Размер:</span>
                <div className="seg">
                  {b.sizes.map((s) => (
                    <button key={s.code} type="button" className={s.code === sizeCode ? "on" : ""} onClick={() => setSizeCode(s.code)}>
                      {s.code} · {priceOf(s) === null ? "пусто" : formatByn(priceOf(s)!)}
                    </button>
                  ))}
                </div>
              </div>
            </div>
            <label className="field">
              <span>Подпись размера на сайте</span>
              <input className="input" value={size.note} placeholder="Как на фото, 11 цветков" onChange={(e) => patchSize((s) => ({ ...s, note: e.target.value }))} />
            </label>
            {size.items.length === 0 && (
              <p className="small muted">
                В размере {sizeCode} пока нет ни одного цветка, поэтому на сайте его не видно. Найдите цветы ниже и укажите количество.
                Размер можно оставить пустым, если он не нужен.
              </p>
            )}
            {size.items.length > 0 && <table className="table">
              <thead><tr><th>Цветок</th><th>Количество</th><th>Цена</th><th>Сумма</th><th /></tr></thead>
              <tbody>
                {size.items.map((i) => {
                  const f = byId.get(i.flowerId);
                  return (
                    <tr key={i.flowerId}>
                      <td>
                        <b>{f?.name ?? "?"}</b>
                        <div className="small muted">{f?.kind === "GREEN" ? "Зелень" : "Цветы"}{f && !f.inStock ? " · нет в наличии" : ""}</div>
                      </td>
                      <td>
                        <div className="qty">
                          <button type="button" onClick={() => setQty(i.flowerId, i.qty - 1)}>−</button>
                          <input className="num" style={{ width: 52, border: 0, textAlign: "center" }} value={i.qty} inputMode="numeric" onChange={(e) => setQty(i.flowerId, parseInt(e.target.value || "1", 10) || 1)} />
                          <button type="button" onClick={() => setQty(i.flowerId, i.qty + 1)}>+</button>
                        </div>
                      </td>
                      <td className="small muted">{f ? `${formatByn(f.priceKop)} / ${f.unit === "STEM" ? "ветка" : "шт"}` : ""}</td>
                      <td style={{ fontWeight: 700 }}>{f ? formatByn(f.priceKop * i.qty) : ""}</td>
                      <td>
                        <button type="button" className="btn-ghost" aria-label="Убрать" onClick={() => patchSize((s) => ({ ...s, items: s.items.filter((x) => x.flowerId !== i.flowerId) }))}>×</button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>}
            <div className="stack" style={{ gap: 8 }}>
              <label className="field">
                <span>Добавить цветок</span>
                <input
                  className="input"
                  type="search"
                  value={flowerQuery}
                  placeholder="Начните писать: роза, эвкалипт…"
                  onChange={(e) => setFlowerQuery(e.target.value)}
                  onKeyDown={(e) => {
                    // Enter добавляет первый найденный цветок
                    if (e.key === "Enter") {
                      e.preventDefault();
                      if (flowerQuery.trim() && candidates[0]) addFlower(candidates[0].id);
                    }
                  }}
                />
              </label>
              <div className="row" style={{ gap: 6 }}>
                {candidates.map((f) => (
                  <button key={f.id} type="button" className="chip" style={{ fontSize: 14, minHeight: 36, padding: "6px 12px" }} onClick={() => addFlower(f.id)}>
                    {f.name}{f.inStock ? "" : " · нет в наличии"}
                  </button>
                ))}
                {candidates.length === 0 && (
                  <span className="small muted">
                    {flowerQuery.trim() ? "Такого цветка нет в справочнике. Добавьте его в разделе «Цветы»." : "Все цветы из справочника уже в составе."}
                  </span>
                )}
              </div>
            </div>
            {sizeCode !== "M" && size.items.length === 0 && (
              <button type="button" className="btn btn-outline" style={{ alignSelf: "flex-start" }} onClick={() => patchSize((s) => ({ ...s, items: b.sizes.find((x) => x.code === "M")!.items.map((i) => ({ ...i })) }))}>
                Скопировать состав из M
              </button>
            )}
            {missing(size).length > 0 && <p className="error">Нет в наличии: {missing(size).join(", ")}. Этот размер скрыт на сайте.</p>}
          </div>
        </div>

        <div className="stack" style={{ gap: 16, position: "sticky", top: 16 }}>
          <div className="card stack" style={{ gap: 10 }}>
            <div className="sum-row"><span>Цветы ({sizeCode})</span><b>{formatByn(sum)}</b></div>
            <label className="sum-row" style={{ alignItems: "center" }}>
              <span>Упаковка, BYN</span>
              <input
                className="num"
                value={packagingText}
                inputMode="decimal"
                onChange={(e) => {
                  setPackagingText(e.target.value);
                  const k = parseByn(e.target.value);
                  if (k !== null) setB({ ...b, packagingKop: k });
                }}
              />
            </label>
            <div className="sum-row" style={{ borderTop: "1px solid var(--line)", paddingTop: 10, alignItems: "baseline" }}>
              <b>Цена на сайте</b>
              <span className="sum-total">{size.items.length ? formatByn(sum + b.packagingKop) : "—"}</span>
            </div>
            <span className="small muted">Цветы {formatByn(sum)} + упаковка {formatByn(b.packagingKop)}</span>
          </div>

          <div className="card stack" style={{ gap: 10 }}>
            <span className="label">Коллекция</span>
            <select className="input" value={b.collectionId ?? ""} onChange={(e) => setB({ ...b, collectionId: e.target.value ? Number(e.target.value) : null })}>
              <option value="">Без коллекции</option>
              {collections.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <span className="small muted">На сайте покажем как «Этот же состав в другом виде»</span>
            {(Object.keys(FORMATS) as FormatCode[]).map((fmt) => {
              const sib = siblings.find((s) => s.format === fmt);
              return (
                <div key={fmt} className="sum-row small">
                  <span>{FORMATS[fmt].label}</span>
                  {sib?.id === b.id ? (
                    <span className="muted">Этот букет</span>
                  ) : sib ? (
                    <Link href={`/admin/bouquets/${sib.id}`}>{sib.name} · открыть</Link>
                  ) : (
                    <button type="button" className="btn-ghost small" onClick={() => start(() => cloneToFormat(b.id, fmt))}>+ Создать с этим составом</button>
                  )}
                </div>
              );
            })}
            <span className="small muted">Копия создаётся выключенной: добавьте фото и включите.</span>
          </div>

          <Link href={`/bouquet/${initial.slug}`} target="_blank" className="small">Посмотреть на сайте →</Link>
          <button
            type="button"
            className="btn-ghost small muted"
            style={{ alignSelf: "flex-start" }}
            onClick={() => {
              if (confirm("Удалить букет совсем? Заказы с ним сохранятся.")) start(() => deleteBouquet(b.id));
            }}
          >
            Удалить букет
          </button>
        </div>
      </div>
    </>
  );
}
