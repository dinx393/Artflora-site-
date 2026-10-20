// Цвета зон доставки на карте в админке: зона 1 — синяя, 2 — оранжевая и т.д. (как привычно по Flowwow).
export const ZONE_COLORS = ["#4f8ef7", "#f5892b", "#2fb36d", "#2cc6e8", "#ef5d6c", "#f2b705", "#8f6ed5", "#d45fb4", "#7f8c3a", "#5c6bc0"];
// За МКАД — отдельный цвет: цена там растёт с каждым км.
export const OUTSIDE_COLOR = "#a98be8";

// Цвет зоны: свой, если владелец выбрал, иначе из набора по номеру.
export function zoneColor(zone: number, own?: string): string {
  return own || ZONE_COLORS[(zone - 1) % ZONE_COLORS.length];
}

// Цвет для новой зоны или участка: первый из набора, которого ещё нет.
export function nextColor(used: string[]): string {
  const taken = new Set(used.map((c) => c.toLowerCase()));
  return [...ZONE_COLORS, "#e57373", "#26a69a", "#ffb74d", "#9575cd", "#4db6ac", "#a1887f"].find((c) => !taken.has(c)) ?? "#" + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, "0");
}

// Цвет точки на карте для расчёта: участок, за МКАД, зона; серый — не возим.
export function quoteColor(
  q: { ok: boolean; zone?: number; outsideMkadKm?: number; area?: { color: string } },
  zones: { color?: string }[],
  outsideColor?: string,
): string {
  if (q.area) return q.area.color;
  if (!q.ok) return "#999999";
  if (q.outsideMkadKm) return outsideColor || OUTSIDE_COLOR;
  return zoneColor(q.zone!, zones[q.zone! - 1]?.color);
}
