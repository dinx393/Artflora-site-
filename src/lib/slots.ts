// Интервалы доставки и самовывоза. Время — минское (UTC+3, без перехода на летнее).

export const SLOTS = ["10–12", "12–14", "14–16", "16–18", "18–21"] as const;
export type Slot = (typeof SLOTS)[number];

const MINSK_OFFSET_MIN = 3 * 60;

export function minskNow(now = new Date()): { date: string; hour: number; minute: number } {
  const t = new Date(now.getTime() + MINSK_OFFSET_MIN * 60_000);
  return {
    date: t.toISOString().slice(0, 10),
    hour: t.getUTCHours(),
    minute: t.getUTCMinutes(),
  };
}

export function slotStartHour(slot: string): number {
  return parseInt(slot.split("–")[0], 10);
}

export function addDays(isoDate: string, days: number): string {
  const d = new Date(isoDate + "T00:00:00Z");
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

// Какие интервалы ещё можно выбрать на дату. Сегодня — только те,
// что начинаются не раньше чем через leadHours (время собрать букет и довезти).
export function availableSlots(isoDate: string, leadHours: number, now = new Date()): Slot[] {
  const m = minskNow(now);
  if (isoDate < m.date) return [];
  if (isoDate > m.date) return [...SLOTS];
  const ready = m.hour + m.minute / 60 + leadHours;
  return SLOTS.filter((s) => slotStartHour(s) >= ready);
}

export function isSlotAllowed(isoDate: string, slot: string, leadHours: number, now = new Date()): boolean {
  return (availableSlots(isoDate, leadHours, now) as string[]).includes(slot);
}
