// Ограничение частоты запросов (подбор пароля, ботовые заказы, нагрузка на геокодер).
// Хранится в памяти: сайт работает одним процессом. Два счётчика: по адресу посетителя
// и общий — он держит, даже если бот подставляет разные адреса в заголовки.

type Rule = { perIp: number; total: number; windowMs: number };

export const RULES = {
  login: { perIp: 5, total: 30, windowMs: 15 * 60_000 },
  order: { perIp: 10, total: 200, windowMs: 60 * 60_000 },
  geocode: { perIp: 30, total: 120, windowMs: 60_000 },
  remind: { perIp: 5, total: 100, windowMs: 60 * 60_000 },
} satisfies Record<string, Rule>;

const hits = new Map<string, number[]>();

function take(key: string, limit: number, windowMs: number, now: number): boolean {
  const list = (hits.get(key) ?? []).filter((t) => now - t < windowMs);
  if (list.length >= limit) {
    hits.set(key, list);
    return false;
  }
  list.push(now);
  hits.set(key, list);
  return true;
}

// true — можно, false — слишком часто.
export function allow(action: keyof typeof RULES, ip: string, now = Date.now()): boolean {
  const r = RULES[action];
  if (hits.size > 50_000) hits.clear(); // защита памяти от потока случайных адресов
  return take(`${action}:ip:${ip}`, r.perIp, r.windowMs, now) && take(`${action}:all`, r.total, r.windowMs, now);
}

// Удачный вход не должен считаться попыткой подбора: обнуляем счётчик этого адреса.
export function forget(action: keyof typeof RULES, ip: string) {
  hits.delete(`${action}:ip:${ip}`);
}

// Адрес посетителя из заголовков прокси (nginx/Caddy перед сайтом). Без прокси — общий ключ.
export function clientIp(h: Headers): string {
  return h.get("x-forwarded-for")?.split(",")[0]?.trim() || h.get("x-real-ip") || "unknown";
}

export function resetRateLimits() {
  hits.clear();
}
