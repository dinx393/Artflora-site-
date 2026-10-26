# Как на хостинге: Apache + PHP 8.1 с .htaccess. Только для проверки на своём компьютере.
FROM php:8.1-apache
# GD с JPEG/PNG/WebP — для обработки загруженных фото (Uploads) и её тестов
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite expires
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
