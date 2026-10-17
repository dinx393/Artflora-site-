// Проверка настроек при запуске. На настоящем сервере сайт не стартует
// с паролем и ключом по умолчанию: иначе любой, кто видел репозиторий, войдёт в админку.

const KNOWN_DEFAULTS = new Set([
  "artflora",
  "local-only-secret-change-me-on-server",
  "поменяйте-меня",
  "замените-на-длинную-случайную-строку",
]);

// Сайт открыт только на этом компьютере или в домашней сети.
export function isLocalUrl(url: string): boolean {
  try {
    const h = new URL(url).hostname;
    return h === "localhost" || h === "127.0.0.1" || h === "::1" || h.endsWith(".local") || /^(10|192\.168|172\.(1[6-9]|2\d|3[01]))\./.test(h);
  } catch {
    return false;
  }
}

export function configProblems(env: Record<string, string | undefined>): string[] {
  const site = env.SITE_URL ?? "";
  const local = isLocalUrl(site);
  const problems: string[] = [];
  const secret = env.SESSION_SECRET ?? "";
  const password = env.ADMIN_PASSWORD ?? "";
  if (!secret || secret.length < 16) problems.push("SESSION_SECRET не задан или короче 16 символов");
  if (local) return problems;
  // Ниже — только для сайта в интернете.
  if (!site.startsWith("https://")) problems.push(`SITE_URL должен начинаться с https:// (сейчас «${site || "пусто"}»)`);
  if (KNOWN_DEFAULTS.has(secret) || secret.length < 32) problems.push("SESSION_SECRET: нужна своя случайная строка от 32 символов");
  if (KNOWN_DEFAULTS.has(password) || password.length < 12) problems.push("ADMIN_PASSWORD: нужен свой пароль от 12 символов");
  return problems;
}
