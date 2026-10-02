FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY backend/composer.json backend/composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader || true

# Copy full backend source
COPY backend/ .

# Generate autoloader
RUN composer dump-autoload --optimize || true

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
