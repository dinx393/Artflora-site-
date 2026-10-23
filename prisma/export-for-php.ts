// Выгрузка каталога для PHP-версии сайта (хостинг hoster.by): цветы, букеты с размерами и составом,
// коллекции, дополнения, настройки и сами фото — всё в один файл artflora-export.json.
// Заказы и напоминания не выгружаются.
// Запуск в Docker:  docker compose exec site node prisma/export-for-php.js
//                   docker compose cp site:/tmp/artflora-export.json artflora-export.json
// На хостинге:      php app/cli.php import ~/artflora-export.json
import fs from "node:fs";
import path from "node:path";
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient } from "@prisma/client";

const db = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });
const UPLOAD_DIR = path.resolve(process.env.UPLOAD_DIR ?? "uploads");
const OUT = process.argv[2] ?? "/tmp/artflora-export.json";

async function main() {
  const flowers = await db.flower.findMany({ include: { substitutes: { select: { id: true } } }, orderBy: { id: "asc" } });
  const collections = await db.collection.findMany({ orderBy: { id: "asc" } });
  const bouquets = await db.bouquet.findMany({ include: { sizes: { include: { items: true } } }, orderBy: { id: "asc" } });
  const addons = await db.addon.findMany({ orderBy: { id: "asc" } });
  const settings = await db.setting.findMany();

  // Фото из админки лежат в UPLOAD_DIR и в букетах записаны как /uploads/<файл>
  const photos: Record<string, string> = {};
  for (const b of bouquets) {
    for (const p of b.photos) {
      const name = p.startsWith("/uploads/") ? p.slice("/uploads/".length) : null;
      if (!name || photos[name] || !/^[a-z0-9-]+\.(jpg|png|webp)$/.test(name)) continue;
      const file = path.join(UPLOAD_DIR, name);
      if (fs.existsSync(file)) photos[name] = fs.readFileSync(file).toString("base64");
      else console.log(`Нет файла фото ${name} (букет «${b.name}»)`);
    }
  }

  const data = {
    version: 1,
    exportedAt: new Date().toISOString(),
    flowers: flowers.map(({ substitutes, createdAt, updatedAt, ...f }) => ({ ...f, substitutes: substitutes.map((s) => s.id) })),
    collections,
    bouquets: bouquets.map(({ sizes, createdAt, updatedAt, ...b }) => ({
      ...b,
      sizes: sizes.map((s) => ({ code: s.code, note: s.note, packagingKop: s.packagingKop, items: s.items.map((i) => ({ flowerId: i.flowerId, qty: i.qty })) })),
    })),
    addons,
    settings: Object.fromEntries(settings.map((s) => [s.key, s.value])),
    photos,
  };
  fs.writeFileSync(OUT, JSON.stringify(data));
  const mb = (fs.statSync(OUT).size / 1024 / 1024).toFixed(1);
  console.log(`Готово: цветов ${flowers.length}, букетов ${bouquets.length}, фото ${Object.keys(photos).length}. Файл ${OUT} (${mb} МБ)`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
