// «+375291112233» → «+375 29 111-22-33». Другие форматы показываем как есть.
export function formatPhone(raw: string): string {
  const d = raw.replace(/\D/g, "");
  const m = /^375(\d{2})(\d{3})(\d{2})(\d{2})$/.exec(d) ?? /^80(\d{2})(\d{3})(\d{2})(\d{2})$/.exec(d);
  return m ? `+375 ${m[1]} ${m[2]}-${m[3]}-${m[4]}` : raw;
}
