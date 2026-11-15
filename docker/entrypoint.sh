#!/bin/sh
set -e

cd /var/www/html

PORT="${PORT:-10000}"

# Apache escucha en el puerto que asigna la plataforma.
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_KEY}" ]; then
    echo "ERROR: APP_KEY no está definido. Genera uno con: php artisan key:generate --show" >&2
    exit 1
fi

if [ "${APP_ENV:-production}" = "production" ] && [ -z "${NODE_API_SECRET}" ]; then
    echo "AVISO: NODE_API_SECRET no está definido; la API del nodo responderá 503." >&2
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
fi

# Cachés de arranque (leen las variables de entorno de la plataforma).
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

php artisan prowl:ensure-admin

exec "$@"
