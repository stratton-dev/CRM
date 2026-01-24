FROM php:8.4-fpm

# System deps + potrzebne do SOAP i ogólnie do typowego Laravela
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libpq-dev \
    zip unzip git curl \
    && docker-php-ext-install soap \
    && docker-php-ext-install pdo pdo_pgsql \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer w kontenerze (żeby wersja była spójna z PHP)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
