// Цвета зон доставки на карте в админке: зона 1 — синяя, 2 — оранжевая и т.д. (как привычно по Flowwow).
export const ZONE_COLORS = ["#4f8ef7", "#f5892b", "#2fb36d", "#2cc6e8", "#ef5d6c", "#f2b705", "#8f6ed5", "#d45fb4", "#7f8c3a", "#5c6bc0"];
// За МКАД — отдельный цвет: цена там растёт с каждым км.
export const OUTSIDE_COLOR = "#a98be8";

export function zoneColor(zone: number): string {
  return ZONE_COLORS[(zone - 1) % ZONE_COLORS.length];
}
