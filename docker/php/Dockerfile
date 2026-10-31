FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev libjpeg62-turbo-dev libwebp-dev \
    libonig-dev \
    libpq-dev libsqlite3-dev libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

RUN printf "memory_limit=256M\nupload_max_filesize=5M\npost_max_size=6M\n" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY backend/composer.json backend/composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader

# Copy full backend source
COPY backend/ .

# Generate autoloader
RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
