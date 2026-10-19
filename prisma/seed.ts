// Стартовые данные из макетов: цветы, букеты, коллекция «rose», дополнения.
// Цены демонстрационные — поменяйте их в админке → «Цветы».
// Запуск: npm run db:seed (повторный запуск ничего не дублирует).
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient, type BouquetFormat, type FlowerKind, type FlowerUnit } from "@prisma/client";

const db = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });

const FLOWERS: { name: string; kind: FlowerKind; unit: FlowerUnit; price: number; inStock?: boolean }[] = [
  { name: "Роза кустовая 60 см", kind: "FLOWER", unit: "STEM", price: 10 },
  { name: "Роза Кения 40 см", kind: "FLOWER", unit: "PIECE", price: 6.5 },
  { name: "Эустома", kind: "FLOWER", unit: "STEM", price: 13 },
  { name: "Гортензия", kind: "FLOWER", unit: "PIECE", price: 32 },
  { name: "Альстромерия", kind: "FLOWER", unit: "STEM", price: 7.5 },
  { name: "Хризантема кустовая", kind: "FLOWER", unit: "STEM", price: 9 },
  { name: "Пион", kind: "FLOWER", unit: "PIECE", price: 20, inStock: false },
  { name: "Эвкалипт", kind: "GREEN", unit: "STEM", price: 5 },
  { name: "Гипсофила", kind: "GREEN", unit: "STEM", price: 7 },
];

type Seed = {
  slug: string;
  name: string;
  format: BouquetFormat;
  packaging: number;
  photo: string;
  occasions: string[];
  description: string;
  composition: [string, number][]; // размер M
  enabled?: boolean;
  isHit?: boolean;
  isExclusive?: boolean;
  colors?: string[];
  collection?: string;
};

const BOUQUETS: Seed[] = [
  {
    slug: "sumka-rose",
    colors: ["pink"],
    name: "Сумка с цветами «rose»",
    format: "BAG",
    packaging: 15,
    photo: "/demo/rose-bag.jpg",
    occasions: ["mom", "love", "nothing"],
    description: "Кустовая роза, эустома, сезонная зелень, крафтовая сумка с водой внутри: букет не нужно сразу ставить в вазу.",
    composition: [["Роза кустовая 60 см", 5], ["Эустома", 3], ["Эвкалипт", 2]],
    isHit: true,
    collection: "rose",
  },
  {
    slug: "buket-rose",
    colors: ["pink"],
    name: "Букет «rose»",
    format: "BOUQUET",
    packaging: 8,
    photo: "/demo/rose-bag.jpg",
    occasions: ["mom", "love", "nothing"],
    description: "Тот же состав, что в сумке «rose», в классической упаковке.",
    composition: [["Роза кустовая 60 см", 5], ["Эустома", 3], ["Эвкалипт", 2]],
    collection: "rose",
  },
  {
    slug: "osenniy-vayb",
    colors: ["yellow", "mix"],
    isExclusive: true,
    name: "Осенний вайб",
    format: "BOUQUET",
    packaging: 8,
    photo: "/demo/autumn.jpg",
    occasions: ["birthday", "colleague", "nothing"],
    description: "Тёплый осенний букет с кустовой хризантемой и гортензией.",
    composition: [["Хризантема кустовая", 5], ["Гортензия", 1], ["Эвкалипт", 3]],
    isHit: true,
  },
  {
    slug: "25-keniyskih-roz",
    colors: ["red"],
    name: "25 кенийских роз",
    format: "BOUQUET",
    packaging: 10,
    photo: "/demo/kenya-roses.jpg",
    occasions: ["love", "birthday", "mom"],
    description: "Классика: 25 кенийских роз в лаконичной упаковке.",
    composition: [["Роза Кения 40 см", 25]],
    isHit: true,
  },
  {
    slug: "sumochka-eustoma-alstromeriya",
    colors: ["pink", "white"],
    isExclusive: true,
    name: "Сумочка с эустомой и альстромерией",
    format: "BAG",
    packaging: 15,
    photo: "/demo/eustoma-bag.jpg",
    occasions: ["mom", "colleague", "nothing"],
    description: "Нежная сумочка с эустомой и альстромерией, вода внутри.",
    composition: [["Эустома", 5], ["Альстромерия", 3]],
  },
  {
    slug: "alstromeriya-11",
    colors: ["pink", "mix"],
    name: "Альстромерия 11 веток",
    format: "BOUQUET",
    packaging: 8,
    photo: "/demo/alstroemeria.jpg",
    occasions: ["colleague", "nothing"],
    description: "Яркий и стойкий букет из 11 веток альстромерии.",
    composition: [["Альстромерия", 11]],
    enabled: false,
  },
  {
    slug: "ekzoticheskiy-akcent",
    colors: ["burgundy", "mix"],
    isExclusive: true,
    name: "Экзотический акцент",
    format: "BASKET",
    packaging: 30,
    photo: "/demo/exotic.jpg",
    occasions: ["exotic", "birthday", "love"],
    description: "Композиция в корзине с гортензией, эустомой и гипсофилой.",
    composition: [["Гортензия", 2], ["Эустома", 2], ["Гипсофила", 2]],
    isHit: true,
  },
  {
    slug: "solnechnyy-luchik",
    colors: ["yellow"],
    name: "Солнечный лучик",
    format: "BOUQUET",
    packaging: 8,
    photo: "/demo/sunny.jpg",
    occasions: ["birthday", "mom"],
    description: "Пионы и облако гипсофилы.",
    composition: [["Пион", 7], ["Гипсофила", 2]],
    isHit: true,
  },
];

// S — компактнее, L — пышнее. Количество масштабируем от M.
function scale(items: [string, number][], k: number): [string, number][] {
  return items.map(([n, q]) => [n, Math.max(1, Math.round(q * k))]);
}

const kop = (byn: number) => Math.round(byn * 100);

// Без чего сайт не работает: открытка и подкормка в подарок, остальные дополнения выключены.
// Добавляем только те, которых нет, — то, что поменяли в админке, не трогаем.
async function ensureAddons() {
  // Цены конфет, шара и вазы в макетах не указаны: заданы нулём и выключены.
  // Укажите цену в админке → «Настройки» → «Дополнения» и включите.
  const addons = [
    { code: "card", name: "Открытка", priceKop: 0, included: true, active: true },
    { code: "feed", name: "Подкормка, 2 шт", priceKop: 0, included: true, active: true },
    { code: "candy", name: "Конфеты", priceKop: 0, included: false, active: false },
    { code: "balloon", name: "Воздушный шар", priceKop: 0, included: false, active: false },
    { code: "vase", name: "Ваза", priceKop: 0, included: false, active: false },
  ];
  const have = new Set((await db.addon.findMany({ select: { code: true } })).map((a) => a.code));
  for (const [i, a] of addons.entries()) if (!have.has(a.code)) await db.addon.create({ data: { ...a, sortOrder: i } });
}

// Запуск: при каждом старте сайта (Dockerfile) — только нужное для работы.
// Цветы и букеты-примеры — только с SEED_DEMO=1 (или --demo) и только в пустой базе.
async function main() {
  await ensureAddons();
  if (process.env.SEED_DEMO !== "1" && !process.argv.includes("--demo")) return;
  if ((await db.flower.count()) > 0) {
    console.log("В базе уже есть цветы, примеры не добавляю.");
    return;
  }

  for (const [i, f] of FLOWERS.entries()) {
    await db.flower.create({ data: { name: f.name, kind: f.kind, unit: f.unit, priceKop: kop(f.price), inStock: f.inStock ?? true, sortOrder: i } });
  }
  const flowers = await db.flower.findMany();
  const fid = (name: string) => {
    const f = flowers.find((x) => x.name === name);
    if (!f) throw new Error(`Нет цветка ${name}`);
    return f.id;
  };

  const rose = await db.collection.create({ data: { slug: "rose", name: "rose" } });

  for (const [i, b] of BOUQUETS.entries()) {
    const sizes: { code: "S" | "M" | "L" | "XS" | "XL" | "XXL"; note: string; items: [string, number][] }[] = [
      { code: "S", note: "Компактнее", items: scale(b.composition, 0.65) },
      { code: "M", note: "Как на фото", items: b.composition },
      { code: "L", note: "Пышнее", items: scale(b.composition, 1.4) },
    ];
    await db.bouquet.create({
      data: {
        slug: b.slug,
        name: b.name,
        format: b.format,
        packagingKop: kop(b.packaging),
        photos: [b.photo],
        occasions: b.occasions,
        description: b.description,
        enabled: b.enabled ?? true,
        isHit: b.isHit ?? false,
        isExclusive: b.isExclusive ?? false,
        colors: b.colors ?? [],
        sortOrder: i,
        collectionId: b.collection === "rose" ? rose.id : null,
        sizes: {
          create: sizes.map((s) => {
            const count = s.items.filter(([n]) => !["Эвкалипт", "Гипсофила"].includes(n)).reduce((sum, [, q]) => sum + q, 0);
            return {
              code: s.code,
              note: `${s.note}, ${count} ${count % 10 === 1 && count % 100 !== 11 ? "цветок" : count % 10 >= 2 && count % 10 <= 4 && (count % 100 < 10 || count % 100 >= 20) ? "цветка" : "цветков"}`,
              items: { create: s.items.map(([n, q]) => ({ flowerId: fid(n), qty: q })) },
            };
          }),
        },
      },
    });
  }



  console.log(`Примеры добавлены: ${FLOWERS.length} цветов, ${BOUQUETS.length} букетов.`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
