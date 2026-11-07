FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl zip pcntl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
