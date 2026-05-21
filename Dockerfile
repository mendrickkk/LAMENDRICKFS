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
        nginx \
        gettext \
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
    && apk del .build-deps \
    && mkdir -p /var/log/nginx /run/nginx /etc/nginx/http.d

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor

# Symfony bootstraps via .env; Railway/CI have no local .env (gitignored)
COPY .env.docker.example .env

# Override with production build defaults (runtime uses Railway variables)
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=build-time-secret-set-in-railway-variables \
    DATABASE_URL="mysql://build:build@127.0.0.1:3306/build?serverVersion=8.0&charset=utf8mb4" \
    DEFAULT_URI=http://localhost/ \
    MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0 \
    MAILER_DSN=null://null \
    MAILER_FROM_ADDRESS=noreply@example.com \
    MAILER_FROM_NAME="Flower Shop" \
    CORS_ALLOW_ORIGIN='^https?://.*$' \
    GOOGLE_CLIENT_ID=build \
    GOOGLE_CLIENT_SECRET=build \
    JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem \
    JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem \
    JWT_PASSPHRASE=build_jwt_passphrase

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p var/cache var/log public/uploads/products config/jwt \
    && php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction \
    && php bin/console importmap:install --no-interaction \
    && php bin/console asset-map:compile --no-interaction \
    && chown -R www-data:www-data var public/uploads config/jwt

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/start-web.sh /usr/local/bin/start-web.sh
COPY docker/nginx-railway.conf.template /etc/nginx/http.d/default.conf.template

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh /usr/local/bin/start-web.sh \
    && chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start-web.sh

# Railway: HTTP on $PORT via start-web.sh | Docker Compose: override with command: php-fpm
ENTRYPOINT ["sh", "/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/start-web.sh"]
