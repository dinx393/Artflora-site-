export const OCCASIONS = [
  { slug: "mom", label: "Маме", title: "маме" },
  { slug: "love", label: "Любимой", title: "любимой" },
  { slug: "birthday", label: "На день рождения", title: "на день рождения" },
  { slug: "colleague", label: "Коллеге", title: "коллеге" },
  { slug: "nothing", label: "Без повода", title: "без повода" },
  { slug: "wedding", label: "Свадьба", title: "на свадьбу" },
  { slug: "exotic", label: "Экзотика", title: "с экзотикой" },
] as const;

export type OccasionSlug = (typeof OCCASIONS)[number]["slug"];

export function occasionLabel(slug: string): string {
  return OCCASIONS.find((o) => o.slug === slug)?.label ?? slug;
}

export const FORMATS = {
  BOUQUET: { label: "Букет", inForm: "Букет" },
  BAG: { label: "Сумка с цветами", inForm: "В сумке" },
  HATBOX: { label: "Шляпная коробка", inForm: "Шляпная коробка" },
  BASKET: { label: "Корзина", inForm: "Корзина" },
} as const;

export type FormatCode = keyof typeof FORMATS;

export const PRICE_RANGES = [
  { slug: "to80", label: "До 80 BYN", min: 0, max: 8000 },
  { slug: "80-150", label: "80–150 BYN", min: 8000, max: 15000 },
  { slug: "from150", label: "От 150 BYN", min: 15000, max: Infinity },
] as const;

// Цвета для фильтра каталога. swatch — как кружок выглядит на сайте.
// Цвета букетов для фильтра в каталоге. Это список по умолчанию: владелец меняет его
// и добавляет свои цвета в админке → «Настройки» → «Цвета букетов».
export type ColorOption = { slug: string; label: string; swatch: string };
export const MIX_SWATCH = "conic-gradient(#f2c9d4, #b3122e, #f2c94c, #b9a3d6, #ffffff, #f2c9d4)";
export const DEFAULT_COLORS: ColorOption[] = [
  { slug: "white", label: "Белые", swatch: "#ffffff" },
  { slug: "cream", label: "Кремовые", swatch: "#f3e5c4" },
  { slug: "pink", label: "Нежно-розовые", swatch: "#f2c9d4" },
  { slug: "raspberry", label: "Малиновые", swatch: "#c2185b" },
  { slug: "red", label: "Красные", swatch: "#b3122e" },
  { slug: "burgundy", label: "Бордовые", swatch: "#7e1f3c" },
  { slug: "peach", label: "Персиковые", swatch: "#f7b996" },
  { slug: "orange", label: "Оранжевые", swatch: "#f08a24" },
  { slug: "yellow", label: "Жёлтые", swatch: "#f2c94c" },
  { slug: "lilac", label: "Сиреневые", swatch: "#b9a3d6" },
  { slug: "blue", label: "Синие", swatch: "#3f5fbf" },
  { slug: "green", label: "Зелёные", swatch: "#6b9a3f" },
  { slug: "mix", label: "Микс", swatch: MIX_SWATCH },
];

// В адресе каталога цвета идут через запятую: ?color=pink,red. Можно выбрать несколько.
export function parseColors(param: string | undefined, list: ColorOption[] = DEFAULT_COLORS): string[] {
  const known = new Set<string>(list.map((c) => c.slug));
  return [...new Set((param ?? "").split(",").filter((c) => known.has(c)))];
}

// Нажали на кружок: был выбран — убираем, не был — добавляем. Пустой список → undefined (фильтра нет).
export function toggleColor(selected: string[], slug: string, list: ColorOption[] = DEFAULT_COLORS): string | undefined {
  const next = selected.includes(slug) ? selected.filter((c) => c !== slug) : [...selected, slug];
  return next.length ? list.map((c) => c.slug).filter((c) => next.includes(c)).join(",") : undefined;
}
