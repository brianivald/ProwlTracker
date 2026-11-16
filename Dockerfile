# syntax=docker/dockerfile:1

############################################
# 1) Dependencias PHP (sin paquetes de desarrollo)
############################################
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################################
# 2) Compilación de assets (Vite + Tailwind)
############################################
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --ignore-scripts --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
# Tailwind escanea las vistas de paginación del framework.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

############################################
# 3) Imagen final: PHP 8.3 + Apache
############################################
FROM php:8.3-apache AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-prowltracker.ini"
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/security.conf /etc/apache2/conf-available/security.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/prowltracker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/prowltracker-entrypoint \
    && chmod +x /usr/local/bin/prowltracker-entrypoint

# Render inyecta PORT (por defecto 10000).
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["prowltracker-entrypoint"]
CMD ["apache2-foreground"]
