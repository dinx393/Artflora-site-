#!/bin/sh
# Архив сайта для загрузки на хостинг через «Менеджер файлов»: sh php/dev/build-zip.sh [куда]
# Без паролей (app/config.php), журналов, загруженных фото, тестов и этой папки — их на хостинге не трогаем.
set -eu
cd "$(dirname "$0")/.."
out="${1:-$PWD/../artflora-php-$(date +%Y%m%d-%H%M).zip}"
rm -f "$out"
zip -qr "$out" . -x 'dev/*' 'tests/*' 'README.md' '.gitignore' 'app/config.php' 'app/storage/*' 'uploads/*' 
zip -q "$out" uploads/.htaccess uploads/.gitkeep
echo "$out"
