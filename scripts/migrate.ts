// Применяет миграции из prisma/migrations при запуске контейнера.
// Делает то же, что `prisma migrate deploy`, и ведёт ту же таблицу _prisma_migrations,
// но без Prisma CLI: тому нужны бинарные файлы с binaries.prisma.sh,
// а из Беларуси этот сайт открывается не всегда.
import { createHash, randomUUID } from "node:crypto";
import { readdirSync, readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { Client } from "pg";

const dir = path.resolve(process.argv[2] ?? "prisma/migrations");

async function main() {
  const db = new Client({ connectionString: process.env.DATABASE_URL });
  await db.connect();
  try {
    // Чтобы два одновременных запуска не применяли одно и то же
    await db.query("SELECT pg_advisory_lock(72707369)");
    await db.query(`CREATE TABLE IF NOT EXISTS "_prisma_migrations" (
      "id" VARCHAR(36) PRIMARY KEY NOT NULL,
      "checksum" VARCHAR(64) NOT NULL,
      "finished_at" TIMESTAMPTZ,
      "migration_name" VARCHAR(255) NOT NULL,
      "logs" TEXT,
      "rolled_back_at" TIMESTAMPTZ,
      "started_at" TIMESTAMPTZ NOT NULL DEFAULT now(),
      "applied_steps_count" INTEGER NOT NULL DEFAULT 0
    )`);
    const { rows } = await db.query<{ migration_name: string; finished_at: Date | null; rolled_back_at: Date | null }>(
      `SELECT migration_name, finished_at, rolled_back_at FROM "_prisma_migrations"`,
    );
    const failed = rows.find((r) => !r.finished_at && !r.rolled_back_at);
    if (failed) throw new Error(`Миграция ${failed.migration_name} когда-то не дошла до конца. Нужна ручная проверка базы.`);
    const done = new Set(rows.filter((r) => r.finished_at && !r.rolled_back_at).map((r) => r.migration_name));

    const names = readdirSync(dir, { withFileTypes: true })
      .filter((d) => d.isDirectory() && existsSync(path.join(dir, d.name, "migration.sql")))
      .map((d) => d.name)
      .sort();
    let applied = 0;
    for (const name of names) {
      if (done.has(name)) continue;
      const sql = readFileSync(path.join(dir, name, "migration.sql"), "utf8");
      const checksum = createHash("sha256").update(sql).digest("hex");
      await db.query("BEGIN");
      try {
        await db.query(sql);
        await db.query(
          `INSERT INTO "_prisma_migrations" (id, checksum, finished_at, migration_name, applied_steps_count) VALUES ($1, $2, now(), $3, 1)`,
          [randomUUID(), checksum, name],
        );
        await db.query("COMMIT");
      } catch (e) {
        await db.query("ROLLBACK");
        throw new Error(`Миграция ${name} не применилась: ${(e as Error).message}`);
      }
      applied++;
      console.log(`Применена миграция ${name}`);
    }
    console.log(applied ? `Миграций применено: ${applied}` : "База в актуальном состоянии.");
  } finally {
    await db.end();
  }
}

main().catch((e) => {
  console.error(e instanceof Error ? e.message : e);
  process.exit(1);
});
