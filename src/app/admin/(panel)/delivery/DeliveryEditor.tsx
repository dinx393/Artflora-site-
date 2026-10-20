"use client";

import { useRouter } from "next/navigation";
import { useRef, useState, useTransition } from "react";
import type { DistrictPreset } from "@/lib/defaults";
import { quoteDelivery, type DeliveryConfig, type LatLng } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { OUTSIDE_COLOR, zoneColor } from "@/lib/zoneColors";
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
                <i className="zone-dot" style={{ background: zoneColor(i + 1) }} />
                <b>Зона {i + 1}</b>
                <span className="small muted">{i === last ? "дальше, до МКАД" : "до"}</span>
                {i === last ? <span /> : <span className="row" style={{ gap: 4 }}><Num value={z.maxKm} step={0.5} width={70} onChange={(n) => setZone(i, { maxKm: n })} /> км</span>}
                <span className="row" style={{ gap: 4 }}><Money kop={z.priceKop} onChange={(k) => setZone(i, { priceKop: k })} /> BYN</span>
                <button type="button" className="btn-ghost" disabled={cfg.zones.length === 1} aria-label="Убрать зону" onClick={() => changeCfg({ ...cfg, zones: cfg.zones.filter((_, j) => j !== i) })}>×</button>
              </div>
            ))}
            <button
              type="button"
              className="btn-ghost small"
              style={{ alignSelf: "flex-start" }}
              onClick={() => {
                // новая зона встаёт перед последней («до МКАД»)
                const prev = cfg.zones[last - 1]?.maxKm ?? 0;
                const zone = { maxKm: Math.round((prev + Math.max(1, (lastKm - prev) / 2)) * 2) / 2, priceKop: cfg.zones[last].priceKop };
                changeCfg({ ...cfg, zones: [...cfg.zones.slice(0, last), zone, cfg.zones[last]] });
              }}
            >
              + Добавить зону
            </button>
            <div className="zone-edit">
              <i className="zone-dot" style={{ background: OUTSIDE_COLOR }} />
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
                const color = !q.ok ? "#999" : q.outsideMkadKm > 0 ? OUTSIDE_COLOR : zoneColor(q.zone);
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
                          <b>{formatByn(q.priceKop)}</b> <span className="muted">{q.outsideMkadKm > 0 ? `за МКАД ${q.outsideMkadKm} км` : `зона ${q.zone}`}</span>
                        </>
                      ) : (
                        <span className="error" style={{ padding: 0 }}>слишком далеко</span>
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
          />
          <div className="map-legend">
            {cfg.zones.map((z, i) => (
              <span key={i}>
                <i className="zone-dot" style={{ background: zoneColor(i + 1) }} />
                {i === last ? "дальше, до МКАД" : `до ${z.maxKm} км`} · {formatByn(z.priceKop)}
              </span>
            ))}
            <span>
              <i className="zone-dot" style={{ background: OUTSIDE_COLOR }} />
              за МКАД · {formatByn(cfg.zones[last]?.priceKop ?? 0)} + {formatByn(cfg.outsideMkadPerKmKop)}/км
            </span>
            <span className="muted">пунктир — МКАД и предел «дальше не возим»</span>
          </div>
        </div>
      </div>
    </>
  );
}
