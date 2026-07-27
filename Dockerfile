# ============================================================
# DocuBrain API — Production Dockerfile (multi-stage)
# ============================================================
# Stage 1: Composer deps (cached layer — rebuilds only if
#          composer.json or composer.lock change)
# Stage 2: Final image with deps baked in + supervisor
# ============================================================

# ── Stage 1: Install PHP deps ──────────────────────────────
FROM php:8.5-cli AS composer-deps

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /build
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Production image ──────────────────────────────
FROM php:8.5-cli

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip zip \
    libpq-dev libzip-dev libonig-dev libxml2-dev \
    poppler-utils \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions via mlocati installer (same as your original)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    zip \
    opcache \
    redis

# PHP production config
COPY docker/php.ini /usr/local/etc/php/conf.d/docubrain.ini

WORKDIR /var/www/html

# Copy vendor from build stage (already optimized, no dev deps)
COPY --from=composer-deps /build/vendor ./vendor

# Copy application code
COPY . .

# Regenerate optimized autoload with app classes present
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && rm /usr/bin/composer

# Recreate Laravel storage dirs (.dockerignore excludes them)
RUN mkdir -p bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage

# Entrypoint & supervisor
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
COPY docker/supervisord.conf /etc/supervisor/conf.d/docubrain.conf

EXPOSE 8000 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/docubrain.conf", "-n"]
