"use client";

import "leaflet/dist/leaflet.css";
import type * as Leaflet from "leaflet";
import { useEffect, useRef } from "react";
import type { DistrictPreset } from "@/lib/defaults";
import { quoteDelivery, type DeliveryConfig, type LatLng } from "@/lib/delivery";
import { quoteColor } from "@/lib/zoneColors";

type Props = {
  cfg: DeliveryConfig;
  districts: DistrictPreset[];
  selected: number | null;
  onSelect: (i: number) => void;
  onMoveDistrict: (i: number, p: LatLng) => void;
  onMoveShop: (i: number, p: LatLng) => void;
  // свои участки: выбранный можно править (тянуть точки), а в режиме рисования клик по карте ставит точку
  selectedArea: number | null;
  onSelectArea: (i: number) => void;
  onAreaPoints: (i: number, points: LatLng[]) => void;
  draft: LatLng[] | null;
  onDraftPoint: (p: LatLng) => void;
  // отдаём наружу «где сейчас центр карты» — туда ставим новый район
  centerRef: React.MutableRefObject<(() => LatLng) | null>;
};

function rgba(hex: string, a: number): string {
  const n = parseInt(hex.slice(1), 16);
  return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${a})`;
}

// Карта зон в админке. Зоны рисуем тем же расчётом, что и цену в корзине (quoteDelivery),
// поэтому цвет на карте всегда совпадает с ценой, которую увидит покупатель.
export function DeliveryMap(props: Props) {
  const box = useRef<HTMLDivElement>(null);
  const L = useRef<typeof Leaflet | null>(null);
  const map = useRef<Leaflet.Map | null>(null);
  const zones = useRef<Leaflet.GridLayer | null>(null);
  const marks = useRef<Leaflet.LayerGroup | null>(null);
  const latest = useRef(props);
  latest.current = props;

  // Карта создаётся один раз
  useEffect(() => {
    let dead = false;
    import("leaflet").then((mod) => {
      if (dead || !box.current) return;
      const lf = (mod.default ?? mod) as typeof Leaflet;
      L.current = lf;
      const m = lf.map(box.current, { zoomControl: true, attributionControl: true }).setView([53.905, 27.56], 11);
      lf.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      }).addTo(m);
      marks.current = lf.layerGroup().addTo(m);
      m.on("click", (e: Leaflet.LeafletMouseEvent) => {
        if (latest.current.draft) latest.current.onDraftPoint(round(e.latlng));
      });
      latest.current.centerRef.current = () => {
        const c = m.getCenter();
        return { lat: Math.round(c.lat * 10000) / 10000, lng: Math.round(c.lng * 10000) / 10000 };
      };
      map.current = m;
      draw();
    });
    return () => {
      dead = true;
      map.current?.remove();
      map.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Перерисовываем зоны и метки при любом изменении
  useEffect(() => {
    draw();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.cfg, props.districts, props.selected, props.selectedArea, props.draft]);

  // В режиме рисования — курсор-крестик, двойной клик не приближает карту
  useEffect(() => {
    const m = map.current;
    box.current?.classList.toggle("drawing", !!props.draft);
    if (!m) return;
    if (props.draft) m.doubleClickZoom.disable();
    else m.doubleClickZoom.enable();
  }, [props.draft]);

  // Выбранный участок — показываем целиком
  useEffect(() => {
    const a = props.selectedArea === null ? null : props.cfg.areas?.[props.selectedArea];
    if (a && a.points.length >= 3 && map.current && L.current) {
      const b = L.current.latLngBounds(a.points.map((p) => [p.lat, p.lng] as [number, number]));
      if (!map.current.getBounds().contains(b)) map.current.fitBounds(b.pad(0.4));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.selectedArea]);

  // Выбранный район — показываем на карте
  useEffect(() => {
    const d = props.selected === null ? null : props.districts[props.selected];
    if (d && map.current && !map.current.getBounds().pad(-0.1).contains([d.lat, d.lng])) map.current.panTo([d.lat, d.lng]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.selected]);

  function draw() {
    const lf = L.current;
    const m = map.current;
    if (!lf || !m || !marks.current) return;
    const { cfg, districts, selected, selectedArea, draft } = latest.current;

    // Цветные зоны: каждую клеточку 4×4 px красим цветом зоны, в которую попадает эта точка
    zones.current?.remove();
    const layer = new lf.GridLayer({ opacity: 1, zIndex: 2 });
    (layer as unknown as { createTile: (c: Leaflet.Coords) => HTMLElement }).createTile = (coords) => {
      const tile = document.createElement("canvas");
      const size = layer.getTileSize();
      tile.width = size.x;
      tile.height = size.y;
      const ctx = tile.getContext("2d")!;
      const step = 4;
      for (let y = 0; y < size.y; y += step) {
        for (let x = 0; x < size.x; x += step) {
          const ll = m.unproject([coords.x * size.x + x + step / 2, coords.y * size.y + y + step / 2], coords.z);
          const q = quoteDelivery({ lat: ll.lat, lng: ll.lng }, 0, cfg);
          if (!q.ok && !q.area) continue;
          ctx.fillStyle = rgba(quoteColor(q, cfg.zones, cfg.outsideColor), q.ok && q.outsideMkadKm > 0 ? 0.28 : 0.38);
          ctx.fillRect(x, y, step, step);
        }
      }
      return tile;
    };
    layer.addTo(m);
    zones.current = layer;

    const g = marks.current;
    g.clearLayers();
    // МКАД и предел «дальше не возим» — пунктиром
    lf.circle([cfg.mkadCenter.lat, cfg.mkadCenter.lng], { radius: cfg.mkadRadiusKm * 1000, color: "#7E1F3C", weight: 1.5, dashArray: "6 6", fill: false, interactive: false }).addTo(g);
    lf.circle([cfg.mkadCenter.lat, cfg.mkadCenter.lng], {
      radius: (cfg.mkadRadiusKm + cfg.maxOutsideMkadKm) * 1000,
      color: "#7E1F3C",
      weight: 1,
      opacity: 0.5,
      dashArray: "2 6",
      fill: false,
      interactive: false,
    }).addTo(g);

    // Свои участки: контур, подпись; у выбранного — точки, которые можно тянуть
    (cfg.areas ?? []).forEach((a, i) => {
      if (a.points.length < 3) return;
      const ll = a.points.map((p) => [p.lat, p.lng] as [number, number]);
      const on = selectedArea === i;
      const poly = lf.polygon(ll, { color: a.color, weight: on ? 3 : 2, dashArray: a.noDelivery ? "6 4" : undefined, fillOpacity: 0, bubblingMouseEvents: false }).addTo(g);
      poly.bindTooltip(`${esc(a.name)} · ${a.noDelivery ? "не возим" : (a.priceKop / 100).toLocaleString("ru-RU") + " BYN"}`, { permanent: true, direction: "center", className: "map-area-label" });
      poly.on("click", (e: Leaflet.LeafletMouseEvent) => {
        if (latest.current.draft) return latest.current.onDraftPoint(round(e.latlng));
        latest.current.onSelectArea(i);
      });
      if (!on || draft) return;
      const pts = [...a.points];
      const commit = (next: LatLng[]) => latest.current.onAreaPoints(i, next);
      pts.forEach((p, k) => {
        const v = lf.marker([p.lat, p.lng], { icon: lf.divIcon({ className: "map-vertex", iconSize: [14, 14], iconAnchor: [7, 7] }), draggable: true, zIndexOffset: 1200, title: "Тяните точку. Двойной клик — убрать" }).addTo(g);
        v.on("drag", () => {
          pts[k] = round(v.getLatLng());
          poly.setLatLngs(pts.map((q) => [q.lat, q.lng]));
        });
        v.on("dragend", () => commit(pts));
        v.on("dblclick", () => {
          if (pts.length > 3) commit(pts.filter((_, j) => j !== k));
        });
      });
      // полупрозрачные точки посередине сторон: потянули — появилась новая вершина
      pts.forEach((p, k) => {
        const n = pts[(k + 1) % pts.length];
        const mid = lf.marker([(p.lat + n.lat) / 2, (p.lng + n.lng) / 2], { icon: lf.divIcon({ className: "map-vertex mid", iconSize: [12, 12], iconAnchor: [6, 6] }), draggable: true, zIndexOffset: 1100, title: "Потяните, чтобы добавить точку" }).addTo(g);
        mid.on("dragend", () => commit([...pts.slice(0, k + 1), round(mid.getLatLng()), ...pts.slice(k + 1)]));
      });
    });

    // Участок, который сейчас рисуют
    if (draft) {
      if (draft.length > 1) lf.polyline(draft.map((p) => [p.lat, p.lng] as [number, number]), { color: "#7E1F3C", weight: 2, dashArray: "4 4", interactive: false }).addTo(g);
      if (draft.length > 2) lf.polygon(draft.map((p) => [p.lat, p.lng] as [number, number]), { color: "#7E1F3C", weight: 0, fillOpacity: 0.15, interactive: false }).addTo(g);
      draft.forEach((p) => lf.marker([p.lat, p.lng], { icon: lf.divIcon({ className: "map-vertex", iconSize: [14, 14], iconAnchor: [7, 7] }), interactive: false }).addTo(g));
    }

    cfg.shops.forEach((s, i) => {
      const icon = lf.divIcon({ className: "map-shop", html: `<span>⌂</span><b>${esc(s.name)}</b>`, iconSize: [34, 34], iconAnchor: [17, 17] });
      const mk = lf.marker([s.location.lat, s.location.lng], { icon, draggable: true, zIndexOffset: 1000, title: `${s.name}: перетащите, если точка не та` }).addTo(g);
      mk.on("dragend", () => {
        const p = mk.getLatLng();
        latest.current.onMoveShop(i, round(p));
      });
    });

    districts.forEach((d, i) => {
      const q = quoteDelivery(d, 0, cfg);
      const color = quoteColor(q, cfg.zones, cfg.outsideColor);
      const icon = lf.divIcon({
        className: `map-district ${selected === i ? "on" : ""} ${q.ok ? "" : "far"}`,
        html: `<i style="background:${color}"></i><span>${esc(d.name)}</span>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
      });
      const mk = lf.marker([d.lat, d.lng], { icon, draggable: true, zIndexOffset: selected === i ? 900 : 0 }).addTo(g);
      mk.on("click", () => latest.current.onSelect(i));
      mk.on("dragend", () => {
        latest.current.onMoveDistrict(i, round(mk.getLatLng()));
      });
    });
  }

  return <div ref={box} className="delivery-map" />;
}

function round(p: { lat: number; lng: number }): LatLng {
  return { lat: Math.round(p.lat * 10000) / 10000, lng: Math.round(p.lng * 10000) / 10000 };
}

function esc(s: string): string {
  return s.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
}
