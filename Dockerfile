# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.2-fpm-alpine AS app

RUN apk add --no-cache \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p var/cache var/log public/uploads/products config/jwt \
    && APP_ENV=prod APP_DEBUG=0 \
        php bin/console importmap:install --no-interaction \
    && APP_ENV=prod APP_DEBUG=0 \
        php bin/console asset-map:compile --no-interaction \
    && chown -R www-data:www-data var public/uploads config/jwt

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["sh", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
