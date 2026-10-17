// Большой демо-каталог: 140 букетов с нынешнего сайта artflora.by, чтобы посмотреть,
// как витрина выглядит с настоящим объёмом. Фото берутся прямо с artflora.by.
// Состав упрощён: один основной цветок × кол-во + упаковка, итог ≈ цене с artflora.by.
// Загрузить:  npm run db:demo-catalog          (в Docker: docker compose exec site node prisma/demo-catalog.js)
// Убрать:     npm run db:demo-catalog -- --remove  (в Docker: ... node prisma/demo-catalog.js --remove)
// Повторный запуск ничего не дублирует. Удаляются только эти букеты (адрес начинается с af-).
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient, type BouquetFormat } from "@prisma/client";
import CATALOG from "./demo-catalog.json";

const db = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });
const PREFIX = "af-";

type Item = { slug: string; name: string; price: number; format: BouquetFormat; occasions: string[]; flower: string; qty: number | null; photos: string[] };

async function main() {
  if (process.argv.includes("--remove")) {
    const { count } = await db.bouquet.deleteMany({ where: { slug: { startsWith: PREFIX } } });
    console.log(`Убрано демо-букетов: ${count}.`);
    return;
  }

  const flowers = await db.flower.findMany({ where: { kind: "FLOWER" } });
  const fallback = flowers.find((f) => f.inStock) ?? flowers[0];
  if (!fallback) throw new Error("В справочнике нет ни одного цветка — сначала добавьте цветы.");
  const existing = new Set((await db.bouquet.findMany({ select: { slug: true } })).map((b) => b.slug));
  const start = ((await db.bouquet.aggregate({ _max: { sortOrder: true } }))._max.sortOrder ?? 0) + 1;

  let added = 0;
  for (const [i, b] of (CATALOG as Item[]).entries()) {
    if (existing.has(b.slug)) continue;
    const flower = flowers.find((f) => f.name === b.flower && f.inStock) ?? fallback;
    const priceKop = Math.round(b.price * 100);
    // Кол-во из названия («25 роз»), иначе так, чтобы цветы были ~60% цены.
    let qty = b.qty ?? Math.max(1, Math.floor((priceKop * 0.6) / flower.priceKop));
    while (qty > 1 && qty * flower.priceKop > priceKop) qty--;
    const packagingKop = Math.max(0, priceKop - qty * flower.priceKop);
    await db.bouquet.create({
      data: {
        slug: b.slug,
        name: b.name,
        format: b.format,
        packagingKop,
        photos: b.photos,
        occasions: b.occasions,
        sortOrder: start + i,
        sizes: { create: [{ code: "M", note: "Как на фото", items: { create: [{ flowerId: flower.id, qty }] } }] },
      },
    });
    added++;
  }
  console.log(`Добавлено демо-букетов: ${added} (уже были: ${CATALOG.length - added}).`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
