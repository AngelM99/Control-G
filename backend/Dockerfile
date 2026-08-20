FROM php:8.3-fpm

# ── Dependencias del sistema ───────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libicu-dev \
        libxml2-dev \
        unzip \
        zip \
        ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        gd \
        intl \
        pcntl \
        exif \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ───────────────────────────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Configuración PHP ──────────────────────────────────────────────────────────
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# ── Copiar el código del proyecto al contenedor ───────────────────────────────
COPY . /var/www/html

# ── Instalar dependencias PHP de producción ────────────────────────────────────
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ── Permisos storage y cache ──────────────────────────────────────────────────
RUN mkdir -p storage/framework/{sessions,views,cache} \
             storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

# ── Servidor HTTP integrado para Render ────────────────────────────────────────
CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]