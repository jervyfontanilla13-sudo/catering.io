FROM node:22-alpine AS frontend

WORKDIR /app
COPY . .
RUN npm ci && npm run build

FROM php:8.3-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libcurl4-openssl-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd intl mbstring opcache pdo_mysql zip \
    && rm -f \
        /etc/apache2/mods-enabled/mpm_event.load \
        /etc/apache2/mods-enabled/mpm_event.conf \
        /etc/apache2/mods-enabled/mpm_worker.load \
        /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite \
    && apache2ctl -t \
    && rm -rf /var/lib/apt/lists/*

RUN sed -ri \
    -e 's!/var/www/html!/var/www/html/public!g' \
    -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/sites-available/000-default.conf

FROM php-base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan storage:link

FROM php-base AS runtime

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint
RUN chmod +x /usr/local/bin/railway-entrypoint

ENTRYPOINT ["railway-entrypoint"]
