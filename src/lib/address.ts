// Nominatim плохо понимает сокращения: «пр. Независимости 180» он находит
// как центр Минска. Разворачиваем привычные сокращения в полные слова.
const ABBREVIATIONS: [RegExp, string][] = [
  [/(^|[\s,])(пр-т|просп\.?|пр\.?)(?=\s)/gi, "$1проспект"],
  [/(^|[\s,])ул\.?(?=\s)/gi, "$1улица"],
  [/(^|[\s,])пер\.?(?=\s)/gi, "$1переулок"],
  [/(^|[\s,])(б-р|бул\.)(?=\s)/gi, "$1бульвар"],
  [/(^|[\s,])пл\.?(?=\s)/gi, "$1площадь"],
  [/(^|[\s,])(пр-д|прз\.)(?=\s)/gi, "$1проезд"],
  [/(^|[\s,])(д\.|дом)\s*(?=\d)/gi, "$1"],
];

export function normalizeAddress(address: string): string {
  let s = address
    .trim()
    .replace(/\s+/g, " ")
    .replace(/(^|[\s,])(пр|просп|ул|пер|пл|бул)\.(?=[А-ЯЁ])/gi, "$1$2. ");
  for (const [re, to] of ABBREVIATIONS) s = s.replace(re, to);
  return s;
}

// «ул. Немига, 5, кв. 12, подъезд 2, этаж 4» — так адрес видит флорист и курьер.
export function fullAddress(o: { address: string; apartment?: string; entrance?: string; floor?: string }): string {
  return [
    o.address.trim(),
    o.apartment?.trim() && `кв. ${o.apartment.trim()}`,
    o.entrance?.trim() && `подъезд ${o.entrance.trim()}`,
    o.floor?.trim() && `этаж ${o.floor.trim()}`,
  ]
    .filter(Boolean)
    .join(", ");
}
