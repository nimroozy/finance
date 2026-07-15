FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    nginx \
    oniguruma-dev \
    postgresql-client \
    supervisor \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
        bcmath \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ .
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/backend/nginx-app.conf /etc/nginx/http.d/default.conf
COPY docker/backend/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN composer dump-autoload --optimize \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f /etc/nginx/http.d/default.conf.bak 2>/dev/null || true

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
