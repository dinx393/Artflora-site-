// Деньги храним в копейках. 17250 → «172,50 BYN», 13000 → «130 BYN».
export function formatByn(kop: number): string {
  const sign = kop < 0 ? "−" : "";
  const abs = Math.abs(Math.round(kop));
  const rub = Math.floor(abs / 100);
  const k = abs % 100;
  const rubStr = rub.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
  return `${sign}${rubStr}${k ? "," + k.toString().padStart(2, "0") : ""} BYN`;
}

// «10,5» или «10.50» → 1050. Пустая строка или мусор → null.
export function parseByn(input: string): number | null {
  const s = input.trim().replace(/\s/g, "").replace(",", ".");
  if (!/^\d+(\.\d{1,2})?$/.test(s)) return null;
  return Math.round(parseFloat(s) * 100);
}

// Для полей ввода в админке: 1050 → «10,50»
export function kopToInput(kop: number): string {
  return (kop / 100).toFixed(2).replace(".", ",");
}

export function percentOff(kop: number, pct: number): number {
  return Math.round((kop * pct) / 100);
}
