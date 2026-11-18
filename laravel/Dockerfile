# Zentrix HR — production image (PHP 8.2 + Apache). Works on Railway, Render, Fly.io or any Docker host.
# The app needs a MySQL 8 (or MariaDB 10.4+) database; see README "Hosting online".

# 1. PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs

# 2. CSS and JavaScript (Tailwind scans the Blade views, app/Support and the pagination views in vendor)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# 3. Runtime
FROM php:8.2-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf 'upload_max_filesize=20M\npost_max_size=22M\nmemory_limit=256M\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/zentrix.ini

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x docker/start.sh

EXPOSE 8080
CMD ["sh", "/var/www/html/docker/start.sh"]
