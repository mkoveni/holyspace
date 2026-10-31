FROM php:8.4-fpm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libpq-dev libsqlite3-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite mbstring pcntl bcmath gd exif opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM runtime AS test
COPY backend/ ./
RUN composer install --no-interaction --prefer-dist --no-scripts
CMD ["php", "vendor/bin/phpunit"]

FROM runtime AS production
ENV APP_ENV=prod APP_DEBUG=0
COPY backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --classmap-authoritative \
    && rm -rf tests phpunit.xml \
    && mkdir -p var/cache var/log /tmp/church-locks \
    && chown -R www-data:www-data var /tmp/church-locks
COPY docker/production/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/production/worker.sh /usr/local/bin/church-worker
RUN chmod 755 /usr/local/bin/church-worker
USER www-data
CMD ["php-fpm"]
