"use client";

import { useRouter } from "next/navigation";
import { useRef, useState, useTransition } from "react";
import type { DistrictPreset } from "@/lib/defaults";
import { quoteDelivery, type DeliveryArea, type DeliveryConfig, type LatLng } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { nextColor, OUTSIDE_COLOR, quoteColor, zoneColor } from "@/lib/zoneColors";
import { saveDelivery } from "../../actions";
import { Money, Num } from "../fields";
import { DeliveryMap } from "./DeliveryMap";

// Раздел «Доставка»: слева зоны, районы и магазины, справа карта.
// Цвета на карте = зоны. Районы и магазины перетаскиваются прямо на карте.
export function DeliveryEditor(props: { delivery: DeliveryConfig; districts: DistrictPreset[] }) {
  const router = useRouter();
  const [cfg, setCfg] = useState(props.delivery);
  const [districts, setDistricts] = useState(props.districts);
  const [selected, setSelected] = useState<number | null>(null);
  const [selectedArea, setSelectedArea] = useState<number | null>(null);
  const [draft, setDraft] = useState<LatLng[] | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [dirty, setDirty] = useState(false);
  const [pending, start] = useTransition();
  const centerRef = useRef<(() => LatLng) | null>(null);
  const listRef = useRef<HTMLDivElement>(null);

  function changeCfg(next: DeliveryConfig) {
    setCfg(next);
    setDirty(true);
    setMsg(null);
  }
  function changeDistricts(next: DistrictPreset[]) {
    setDistricts(next);
    setDirty(true);
    setMsg(null);
  }
  const setZone = (i: number, p: Partial<DeliveryConfig["zones"][number]>) => changeCfg({ ...cfg, zones: cfg.zones.map((z, j) => (j === i ? { ...z, ...p } : z)) });
  const areas = cfg.areas ?? [];
  const setAreas = (next: DeliveryArea[]) => changeCfg({ ...cfg, areas: next });
  const setArea = (i: number, p: Partial<DeliveryArea>) => setAreas(areas.map((a, j) => (j === i ? { ...a, ...p } : a)));
  const usedColors = [...cfg.zones.map((z, i) => zoneColor(i + 1, z.color)), ...areas.map((a) => a.color), cfg.outsideColor || OUTSIDE_COLOR];

  function finishDraft() {
    if (!draft || draft.length < 3) return;
    let n = areas.length + 1;
    while (areas.some((a) => a.name === `Участок ${n}`)) n++;
    setAreas([...areas, { name: `Участок ${n}`, color: nextColor(usedColors), priceKop: 3000, noDelivery: false, points: draft }]);
    setSelectedArea(areas.length);
    setDraft(null);
  }

  function save() {
    start(async () => {
      try {
        const r = await saveDelivery({ delivery: cfg, districts });
        setMsg(r.ok ? { ok: true, text: "Сохранено, на сайте уже новые цены" } : { ok: false, text: r.error ?? "Ошибка" });
        if (r.ok) {
          setDirty(false);
          router.refresh();
        }
      } catch {
        setMsg({ ok: false, text: "Не удалось сохранить: нет связи с сервером. Ничего не потеряно, нажмите «Сохранить» ещё раз." });
      }
    });
  }

  function select(i: number) {
    setSelected(i);
    listRef.current?.querySelector(`[data-i="${i}"]`)?.scrollIntoView({ block: "nearest", behavior: "smooth" });
  }

  const last = cfg.zones.length - 1;
  const lastKm = cfg.zones[last]?.maxKm ?? 0;
  // районы по зонам: сколько в каждой
  const quotes = districts.map((d) => quoteDelivery(d, 0, cfg));

  return (
    <>
      <div className="row between">
        <div className="stack" style={{ gap: 4 }}>
          <h1>Доставка</h1>
          <span className="muted">Цвет на карте — зона доставки. Районы и магазины можно перетаскивать прямо на карте.</span>
        </div>
        <div className="row" style={{ gap: 12 }}>
          {msg && <span className={msg.ok ? "pill ok" : "error"}>{msg.text}</span>}
          {dirty && !msg && <span className="small muted">Есть несохранённые изменения</span>}
          <button type="button" className="btn" disabled={pending} onClick={save}>{pending ? "Сохраняем…" : "Сохранить"}</button>
        </div>
      </div>

      <div className="delivery-grid">
        <div className="stack" style={{ gap: 16, minWidth: 0 }}>
          <div className="card stack" style={{ gap: 10 }}>
            <span className="label" style={{ fontSize: 18 }}>Зоны</span>
            <span className="small muted">Расстояние считаем от ближайшего магазина по прямой.</span>
            {cfg.zones.map((z, i) => (
              <div key={i} className="zone-edit">
                <input type="color" className="color-pick sm" value={zoneColor(i + 1, z.color)} aria-label={`Цвет зоны ${i + 1}`} onChange={(e) => setZone(i, { color: e.target.value })} />
                <b>Зона {i + 1}</b>
                <span className="small muted">{i === last ? "дальше, до МКАД" : "до"}</span>
                {i === last ? <span /> : <span className="row" style={{ gap: 4 }}><Num value={z.maxKm} step={0.5} width={70} onChange={(n) => setZone(i, { maxKm: n })} /> км</span>}
                <span className="row" style={{ gap: 4 }}><Money kop={z.priceKop} onChange={(k) => setZone(i, { priceKop: k })} /> BYN</span>
                <button type="button" className="btn-ghost" disabled={cfg.zones.length === 1} aria-label="Убрать зону" onClick={() => changeCfg({ ...cfg, zones: cfg.zones.map((z, j) => ({ ...z, color: zoneColor(j + 1, z.color) })).filter((_, j) => j !== i) })}>×</button>
              </div>
            ))}
            <button
              type="button"
              className="btn-ghost small"
              style={{ alignSelf: "flex-start" }}
              onClick={() => {
                // новая зона встаёт перед последней («до МКАД»)
                const prev = cfg.zones[last - 1]?.maxKm ?? 0;
                const zone = { maxKm: Math.round((prev + Math.max(1, (lastKm - prev) / 2)) * 2) / 2, priceKop: cfg.zones[last].priceKop, color: nextColor(usedColors) };
                // у старых зон фиксируем их цвет, чтобы он не сдвинулся с номером
                const own = cfg.zones.map((z, j) => ({ ...z, color: zoneColor(j + 1, z.color) }));
                changeCfg({ ...cfg, zones: [...own.slice(0, last), zone, own[last]] });
              }}
            >
              + Добавить зону
            </button>
            <div className="zone-edit">
              <input type="color" className="color-pick sm" value={cfg.outsideColor || OUTSIDE_COLOR} aria-label="Цвет «за МКАД»" onChange={(e) => changeCfg({ ...cfg, outsideColor: e.target.value })} />
              <b>За МКАД</b>
              <span className="small muted">+ за км</span>
              <span className="row" style={{ gap: 4 }}><Money kop={cfg.outsideMkadPerKmKop} onChange={(k) => changeCfg({ ...cfg, outsideMkadPerKmKop: k })} /> BYN</span>
              <span className="row small" style={{ gap: 4, gridColumn: "3 / -1" }}>не дальше <Num value={cfg.maxOutsideMkadKm} width={60} onChange={(n) => changeCfg({ ...cfg, maxOutsideMkadKm: n })} /> км</span>
            </div>
            <span className="small muted">
              За МКАД: цена зоны {cfg.zones.length} ({formatByn(cfg.zones[last]?.priceKop ?? 0)}) + {formatByn(cfg.outsideMkadPerKmKop)} за каждый км от МКАД.
            </span>
            <div className="stack" style={{ gap: 8, marginTop: 6 }}>
              <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Бесплатно от, BYN</span><Money kop={cfg.freeFromKop} onChange={(k) => changeCfg({ ...cfg, freeFromKop: k })} /></label>
              <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Скидка за самовывоз, %</span><Num value={cfg.pickupDiscountPct} width={70} onChange={(n) => changeCfg({ ...cfg, pickupDiscountPct: n })} /></label>
              <label className="sum-row" style={{ justifyContent: "flex-start", gap: 12 }}><span>Радиус МКАД, км</span><Num value={cfg.mkadRadiusKm} step={0.1} width={70} onChange={(n) => changeCfg({ ...cfg, mkadRadiusKm: n })} /></label>
            </div>
          </div>

          <div className="card stack" style={{ gap: 10 }}>
            <div className="row between">
              <span className="label" style={{ fontSize: 18 }}>Свои участки</span>
              {!draft && (
                <button type="button" className="btn-ghost small" onClick={() => { setDraft([]); setSelectedArea(null); }}>
                  + Нарисовать участок
                </button>
              )}
            </div>
            <span className="small muted">
              Участок важнее кругов: внутри него своя цена или «не возим». Например, «Смолевичи — 35 BYN» или «Радошковичи — не возим».
              Бесплатная доставка от суммы на участки не действует.
            </span>
            {draft && (
              <div className="zone draw-hint">
                <b>Кликайте по карте по краю участка, точка за точкой</b>
                <span className="small muted">Поставлено точек: {draft.length}. Замкнуть не нужно, последняя соединится с первой.</span>
                <div className="row" style={{ gap: 8 }}>
                  <button type="button" className="btn" disabled={draft.length < 3} onClick={finishDraft}>Готово</button>
                  <button type="button" className="btn btn-outline" disabled={draft.length === 0} onClick={() => setDraft(draft.slice(0, -1))}>Убрать последнюю</button>
                  <button type="button" className="btn-ghost" onClick={() => setDraft(null)}>Отмена</button>
                </div>
              </div>
            )}
            {areas.map((a, i) => (
              <div key={i} className={`area-row ${selectedArea === i ? "on" : ""}`} onClick={() => !draft && setSelectedArea(i)}>
                <input type="color" className="color-pick sm" value={a.color} aria-label={`Цвет участка ${a.name}`} onChange={(e) => setArea(i, { color: e.target.value })} />
                <input className="input" value={a.name} maxLength={40} onFocus={() => setSelectedArea(i)} onChange={(e) => setArea(i, { name: e.target.value })} />
                {a.noDelivery ? <span className="small muted">—</span> : <span className="row" style={{ gap: 4 }}><Money kop={a.priceKop} onChange={(k) => setArea(i, { priceKop: k })} /> BYN</span>}
                <label className="check small" style={{ gap: 6 }}>
                  <input type="checkbox" checked={a.noDelivery} onChange={(e) => setArea(i, { noDelivery: e.target.checked })} />
                  не возим
                </label>
                <button
                  type="button"
                  className="btn-ghost"
                  aria-label={`Убрать участок ${a.name}`}
                  onClick={(e) => {
                    e.stopPropagation();
                    setAreas(areas.filter((_, j) => j !== i));
                    setSelectedArea(null);
                  }}
                >
                  ×
                </button>
              </div>
            ))}
            {selectedArea !== null && areas[selectedArea] && !draft && (
              <span className="small muted">
                «{areas[selectedArea].name}»: тяните белые точки на карте. Полупрозрачные точки посередине сторон добавляют новую, двойной клик по точке её убирает.
              </span>
            )}
          </div>

          <div className="card stack" style={{ gap: 10 }}>
            <div className="row between">
              <span className="label" style={{ fontSize: 18 }}>Районы · {districts.length}</span>
              <button
                type="button"
                className="btn-ghost small"
                onClick={() => {
                  const c = centerRef.current?.() ?? { lat: 53.9, lng: 27.56 };
                  let n = 1;
                  while (districts.some((d) => d.name === `Новый район ${n}`)) n++;
                  changeDistricts([...districts, { name: `Новый район ${n}`, ...c }]);
                  setSelected(districts.length);
                }}
              >
                + Добавить в центр карты
              </button>
            </div>
            <span className="small muted">Покупатель выбирает район в корзине, цена — по точке района. Нажмите на район, чтобы найти его на карте.</span>
            <div ref={listRef} className="district-list">
              {districts.map((d, i) => {
                const q = quotes[i];
                const color = quoteColor(q, cfg.zones, cfg.outsideColor);
                return (
                  <div key={i} data-i={i} className={`district-row ${selected === i ? "on" : ""}`} onClick={() => setSelected(i)}>
                    <i className="zone-dot" style={{ background: color }} />
                    <input
                      className="input"
                      value={d.name}
                      maxLength={40}
                      onFocus={() => setSelected(i)}
                      onChange={(e) => changeDistricts(districts.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))}
                    />
                    <span className="small" style={{ whiteSpace: "nowrap" }}>
                      {q.ok ? (
                        <>
                          <b>{formatByn(q.priceKop)}</b>{" "}
                          <span className="muted">{q.area ? q.area.name : q.outsideMkadKm > 0 ? `за МКАД ${q.outsideMkadKm} км` : `зона ${q.zone}`}</span>
                        </>
                      ) : (
                        <span className="error" style={{ padding: 0 }}>{q.area ? `не возим · ${q.area.name}` : "слишком далеко"}</span>
                      )}
                    </span>
                    <button
                      type="button"
                      className="btn-ghost"
                      aria-label={`Убрать район ${d.name}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        changeDistricts(districts.filter((_, j) => j !== i));
                        setSelected(null);
                      }}
                    >
                      ×
                    </button>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="card stack" style={{ gap: 10 }}>
            <span className="label" style={{ fontSize: 18 }}>Магазины</span>
            <span className="small muted">Доставка едет из ближайшего. Точку магазина можно передвинуть на карте (значок ⌂).</span>
            {cfg.shops.map((s, i) => {
              const set = (p: Partial<typeof s>) => changeCfg({ ...cfg, shops: cfg.shops.map((x, j) => (j === i ? { ...x, ...p } : x)) });
              return (
                <div key={s.code} className="shop-edit">
                  <input className="input" value={s.name} aria-label="Название" onChange={(e) => set({ name: e.target.value })} />
                  <input className="input" value={s.address} aria-label="Адрес" onChange={(e) => set({ address: e.target.value })} />
                  <input className="input" value={s.hours} aria-label="Часы работы" onChange={(e) => set({ hours: e.target.value })} />
                </div>
              );
            })}
          </div>
        </div>

        <div className="delivery-map-wrap">
          <DeliveryMap
            cfg={cfg}
            districts={districts}
            selected={selected}
            centerRef={centerRef}
            onSelect={select}
            onMoveDistrict={(i, p) => {
              changeDistricts(districts.map((x, j) => (j === i ? { ...x, ...p } : x)));
              select(i);
            }}
            onMoveShop={(i, p) => changeCfg({ ...cfg, shops: cfg.shops.map((x, j) => (j === i ? { ...x, location: p } : x)) })}
            selectedArea={selectedArea}
            onSelectArea={setSelectedArea}
            onAreaPoints={(i, points) => setArea(i, { points })}
            draft={draft}
            onDraftPoint={(p) => setDraft((d) => (d ? [...d, p] : d))}
          />
          <div className="map-legend">
            {cfg.zones.map((z, i) => (
              <span key={i}>
                <i className="zone-dot" style={{ background: zoneColor(i + 1, z.color) }} />
                {i === last ? "дальше, до МКАД" : `до ${z.maxKm} км`} · {formatByn(z.priceKop)}
              </span>
            ))}
            <span>
              <i className="zone-dot" style={{ background: cfg.outsideColor || OUTSIDE_COLOR }} />
              за МКАД · {formatByn(cfg.zones[last]?.priceKop ?? 0)} + {formatByn(cfg.outsideMkadPerKmKop)}/км
            </span>
            {areas.map((a, i) => (
              <span key={`a${i}`}>
                <i className="zone-dot" style={{ background: a.color }} />
                {a.name} · {a.noDelivery ? "не возим" : formatByn(a.priceKop)}
              </span>
            ))}
            <span className="muted">пунктир — МКАД и предел «дальше не возим»</span>
          </div>
        </div>
      </div>
    </>
  );
}
