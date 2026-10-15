# Сборка сайта в контейнер. Запуск: docker compose up -d --build
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci || npm install

FROM node:22-alpine AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npx prisma generate && npx next build \
 && npx esbuild prisma/seed.ts --bundle --platform=node --format=cjs --external:@prisma/client --outfile=prisma/seed.js

# Prisma CLI для миграций при запуске: ставим отдельно со всеми его зависимостями
FROM node:22-alpine AS migrator
WORKDIR /cli
COPY package.json ./app-package.json
RUN npm init -y >/dev/null \
 && npm install --no-audit --no-fund "prisma@$(node -p "require('./app-package.json').devDependencies.prisma")"

FROM node:22-alpine AS run
WORKDIR /app
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0 UPLOAD_DIR=/data/uploads
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public
COPY --from=build /app/prisma ./prisma
COPY --from=build /app/node_modules/@prisma ./node_modules/@prisma
COPY --from=build /app/node_modules/.prisma ./node_modules/.prisma
COPY --from=migrator /cli/node_modules ./prisma-cli/node_modules
EXPOSE 3000
# При каждом запуске применяем новые изменения схемы базы, при SEED_DEMO=1 заполняем
# пустую базу примерами (непустую не трогаем), потом стартуем сайт
CMD ["sh", "-c", "node prisma-cli/node_modules/prisma/build/index.js migrate deploy --schema prisma/schema.prisma && if [ \"$SEED_DEMO\" = 1 ]; then node prisma/seed.js; fi && node server.js"]
