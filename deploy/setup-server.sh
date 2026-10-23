#!/bin/bash
# Первая настройка нового сервера (Ubuntu 22.04/24.04) под сайт Артфлора.
# Запуск на сервере от root одной строкой:
#   curl -fsSL https://raw.githubusercontent.com/dinx393/artflora-site-/refs/heads/claude/project-thread-b45kuf/deploy/setup-server.sh | bash -s -- new.artflora.by
# Можно запускать повторно: уже сделанное пропускается, файл .env с паролями не перезаписывается.
set -euo pipefail

DOMAIN="${1:-artflora.by}"
DIR=/opt/artflora
REPO=https://github.com/dinx393/artflora-site-.git

say() { printf '\n\033[1;35m== %s\033[0m\n' "$*"; }

[ "$(id -u)" = 0 ] || { echo "Запустите от root (или через sudo)"; exit 1; }

say "Обновляю систему и ставлю нужные программы"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get upgrade -yq
apt-get install -yq git curl openssl ufw unattended-upgrades ca-certificates
# Обновления безопасности ставятся сами каждую ночь
dpkg-reconfigure -f noninteractive unattended-upgrades

say "Часовой пояс — Минск"
timedatectl set-timezone Europe/Minsk || true

if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
  say "Ставлю Docker"
  curl -fsSL https://get.docker.com | sh
fi
# Запасное зеркало образов: Docker Hub иногда ограничивает скачивание
if [ ! -f /etc/docker/daemon.json ]; then
  mkdir -p /etc/docker
  echo '{ "registry-mirrors": ["https://mirror.gcr.io"] }' > /etc/docker/daemon.json
  systemctl restart docker
fi
systemctl enable --now docker

# Сборке сайта нужно ~2 ГБ памяти: на маленьком сервере добавляем файл подкачки
if [ "$(swapon --show | wc -l)" = 0 ]; then
  say "Добавляю файл подкачки 2 ГБ"
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

say "Закрываю всё, кроме входа на сервер (SSH) и сайта (80, 443)"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

say "Скачиваю код сайта в $DIR"
if [ -d "$DIR/.git" ]; then
  git -C "$DIR" pull --ff-only
else
  git clone "$REPO" "$DIR"
fi
cd "$DIR"

if [ ! -f .env ]; then
  say "Создаю .env с новыми паролями"
  ADMIN_PASSWORD=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)
  cat > .env <<EOF
# Настройки сайта на сервере. Файл не попадает в GitHub. Храните пароли в надёжном месте.
COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml

# Адрес сайта. Для проверки до переезда — new.artflora.by, после — artflora.by
DOMAIN=$DOMAIN
SITE_URL=https://$DOMAIN

# Пароль входа в админку https://$DOMAIN/admin
ADMIN_PASSWORD=$ADMIN_PASSWORD
SESSION_SECRET=$(openssl rand -hex 32)
DB_PASSWORD=$(openssl rand -hex 16)

# Куда Let's Encrypt пишет, если с сертификатом https что-то не так
ACME_EMAIL=minskartflora@gmail.com

# Письма о заказах (пароль приложения Gmail, см. README)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=465
SMTP_USER=
SMTP_PASS=

# Уведомления в Telegram (необязательно)
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
EOF
  chmod 600 .env
  NEW_PASSWORD=1
fi
mkdir -p backups
chmod 700 backups

say "Собираю и запускаю сайт (первый раз 5–10 минут)"
docker compose up -d --build

IP=$(curl -fsS4 --max-time 5 https://ifconfig.me || hostname -I | awk '{print $1}')
say "Готово"
echo "Сайт:    https://$(grep ^DOMAIN= .env | cut -d= -f2)  (заработает, когда домен смотрит на $IP)"
echo "Админка: https://$(grep ^DOMAIN= .env | cut -d= -f2)/admin"
if [ "${NEW_PASSWORD:-}" = 1 ]; then
  echo
  echo "Пароль админки: $ADMIN_PASSWORD"
  echo "Сохраните его! Посмотреть снова: grep ADMIN_PASSWORD $DIR/.env"
fi
