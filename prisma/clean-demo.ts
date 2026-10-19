// Очистка перед запуском: убирает все букеты, коллекции, заказы и напоминания —
// всё, что заводили для примера. Справочник цветов, дополнения и настройки остаются.
// Запуск:  npm run db:clean-demo -- --yes   (в Docker: docker compose exec site node prisma/clean-demo.js --yes)
// С --flowers убирает и справочник цветов (тогда цветы заводятся заново в админке).
import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient } from "@prisma/client";

const db = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });

async function main() {
  if (!process.argv.includes("--yes")) {
    console.log("Это удалит все букеты, заказы и напоминания. Чтобы подтвердить, добавьте --yes");
    return;
  }
  const flowers = process.argv.includes("--flowers");
  const r = await db.$transaction(async (tx) => {
    const orders = await tx.order.deleteMany({}); // позиции заказов удаляются вместе с заказами
    const reminders = await tx.reminder.deleteMany({});
    const bouquets = await tx.bouquet.deleteMany({}); // размеры и составы удаляются вместе с букетами
    const collections = await tx.collection.deleteMany({});
    const fl = flowers ? await tx.flower.deleteMany({}) : { count: 0 };
    return { orders: orders.count, reminders: reminders.count, bouquets: bouquets.count, collections: collections.count, flowers: fl.count };
  });
  console.log(
    `Готово. Убрано: букетов ${r.bouquets}, коллекций ${r.collections}, заказов ${r.orders}, напоминаний ${r.reminders}` +
      (flowers ? `, цветов ${r.flowers}.` : ". Справочник цветов оставлен."),
  );
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
