FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd soap pdo pdo_pgsql pcntl opcache zip \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP-FPM pool config with increased worker limit
COPY php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
