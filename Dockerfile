# Stage 1: Composer dependencies
FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Stage 2: Development
FROM php:8.4-fpm-alpine AS development

RUN apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && apk add --no-cache \
        supervisor \
        curl \
        openssl \
        libpq \
    && docker-php-ext-install pdo_pgsql pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/log/supervisor storage/logs storage/keys bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

CMD ["/entrypoint.sh"]

# Stage 3: Production
FROM development AS production

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data
EXPOSE 9000

CMD ["/entrypoint.sh"]
