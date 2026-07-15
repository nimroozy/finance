#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public /run/nginx
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
  php artisan config:cache 2>/dev/null || true
  php artisan route:cache 2>/dev/null || true
  php artisan view:cache 2>/dev/null || true
  if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction || true
  fi
fi

exec "$@"
