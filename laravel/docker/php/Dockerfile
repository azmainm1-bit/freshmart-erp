# PHP CLI tooling image for local development and CI: composer, artisan,
# tests. Not the production runtime image (see docs/OPERATIONS.md for the
# production php-fpm/nginx setup).
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libonig-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions pdo_pgsql pgsql bcmath zip mbstring intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
