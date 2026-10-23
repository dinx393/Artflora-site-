#!/bin/sh
# Резервная копия раз в сутки (в 03:00 по Минску): база + фото букетов.
# Копии лежат на сервере в /opt/artflora/backups, хранятся 14 дней.
# Запускается сам в контейнере backup (docker-compose.prod.yml).
set -eu
umask 077  # в копиях телефоны и адреса покупателей: читать только владельцу
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
HOUR="${BACKUP_HOUR:-03}"
mkdir -p /backups

backup_now() {
  stamp=$(date +%Y-%m-%d_%H%M)
  echo "[backup] $stamp: начинаю"
  pg_dump -h db -U artflora -d artflora --no-owner | gzip > "/backups/db_$stamp.sql.gz.part"
  mv "/backups/db_$stamp.sql.gz.part" "/backups/db_$stamp.sql.gz"
  tar -czf "/backups/photos_$stamp.tar.gz.part" -C /uploads .
  mv "/backups/photos_$stamp.tar.gz.part" "/backups/photos_$stamp.tar.gz"
  find /backups -name '*.gz' -mtime +"$KEEP_DAYS" -delete
  echo "[backup] $stamp: готово"
}

if [ "${1:-}" = "now" ]; then
  backup_now
  exit 0
fi

last=""
while true; do
  today=$(date +%Y-%m-%d)
  if [ "$(date +%H)" = "$HOUR" ] && [ "$last" != "$today" ]; then
    backup_now || echo "[backup] ОШИБКА, попробую через час"
    last="$today"
  fi
  sleep 300
done
