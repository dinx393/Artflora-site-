# Сборка сайта в контейнер. Запуск: docker compose up -d --build
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci || npm install

FROM node:22-alpine AS build
# Prisma здесь работает без своих бинарных файлов (engineType = "client" + драйвер pg),
# но CLI всё равно пытается скачать их с binaries.prisma.sh, который из Беларуси
# часто недоступен. Указываем заглушки, чтобы сборка не ходила туда вовсе.
ENV PRISMA_SCHEMA_ENGINE_BINARY=/bin/true PRISMA_QUERY_ENGINE_LIBRARY=/bin/true
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
# Миграции и стартовые данные собираем в обычные .js, чтобы в контейнере не нужны были tsx и Prisma CLI
RUN npx prisma generate && npx next build \
 && npx esbuild scripts/migrate.ts --bundle --platform=node --format=cjs --external:pg-native --outfile=prisma/migrate.js \
 && npx esbuild prisma/seed.ts --bundle --platform=node --format=cjs --external:@prisma/client --external:pg-native --outfile=prisma/seed.js \
 && npx esbuild prisma/demo-catalog.ts --bundle --platform=node --format=cjs --external:@prisma/client --external:pg-native --outfile=prisma/demo-catalog.js \
 && npx esbuild prisma/clean-demo.ts --bundle --platform=node --format=cjs --external:@prisma/client --external:pg-native --outfile=prisma/clean-demo.js \
 && npx esbuild prisma/export-for-php.ts --bundle --platform=node --format=cjs --external:@prisma/client --external:pg-native --outfile=prisma/export-for-php.js

FROM node:22-alpine AS run
WORKDIR /app
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0 UPLOAD_DIR=/data/uploads
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public
COPY --from=build /app/prisma ./prisma
COPY --from=build /app/node_modules/@prisma ./node_modules/@prisma
COPY --from=build /app/node_modules/.prisma ./node_modules/.prisma
RUN mkdir -p /data/uploads && chown -R node:node /app/.next
EXPOSE 3000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD wget -qO- http://127.0.0.1:3000/ >/dev/null || exit 1
# При каждом запуске применяем новые изменения схемы базы, добавляем недостающие дополнения
# (открытка, подкормка), при SEED_DEMO=1 заполняем пустую базу примерами, потом стартуем сайт
# Папку с фото отдаём пользователю node (у старых установок она принадлежит root),
# и сам сайт работает уже от node, а не от администратора контейнера
CMD ["sh", "-c", "chown -R node:node /data/uploads && exec su node -s /bin/sh -c 'node prisma/migrate.js && node prisma/seed.js && exec node server.js'"]
