import { PrismaPg } from "@prisma/adapter-pg";
import { PrismaClient } from "@prisma/client";

// Подключение к базе через драйвер pg: Prisma не нужны отдельные бинарные
// файлы, которые качаются с binaries.prisma.sh (из Беларуси он доступен не всегда).
const g = globalThis as unknown as { prisma?: PrismaClient };

export const db = g.prisma ?? new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });

if (process.env.NODE_ENV !== "production") g.prisma = db;
