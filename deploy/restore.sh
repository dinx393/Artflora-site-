#!/bin/sh
# Восстановить базу и фото из резервной копии.
# Запуск на сервере из папки /opt/artflora:
#   sh deploy/restore.sh 2026-11-01_0300
# (дата берётся из имени файла в папке backups). Текущие данные будут заменены!
set -eu
cd "$(dirname "$0")/.."
stamp="${1:?Укажите дату копии, например 2026-11-01_0300 (см. ls backups)}"
db="backups/db_$stamp.sql.gz"
photos="backups/photos_$stamp.tar.gz"
[ -f "$db" ] || { echo "Нет файла $db"; exit 1; }

printf 'Заменить текущую базу и фото копией от %s? Напишите да: ' "$stamp"
read -r answer
[ "$answer" = "да" ] || { echo "Отменено"; exit 1; }

docker compose stop site
docker compose exec -T db psql -U artflora -d postgres -c "DROP DATABASE IF EXISTS artflora WITH (FORCE);" -c "CREATE DATABASE artflora OWNER artflora;"
gunzip -c "$db" | docker compose exec -T db psql -q -U artflora -d artflora
if [ -f "$photos" ]; then
  docker compose run --rm -T --no-deps --entrypoint sh -v "$PWD/backups:/backups:ro" backup \
    -c "find /uploads -mindepth 1 -delete && tar -xzf /backups/photos_$stamp.tar.gz -C /uploads"
fi
docker compose start site
echo "Готово: восстановлено из копии $stamp"
