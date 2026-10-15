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
