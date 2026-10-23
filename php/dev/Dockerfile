# Как на хостинге: Apache + PHP 8.1 с .htaccess. Только для проверки на своём компьютере.
FROM php:8.1-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite expires
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
