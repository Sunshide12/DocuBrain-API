FROM php:8.5-cli

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    poppler-utils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions using mlocati/docker-php-extension-installer for robustness on PHP 8.5
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

# Copy custom php.ini
COPY docker/php.ini /usr/local/etc/php/conf.d/docubrain.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Entrypoint: autoinstall de dependencias + migrations (ver docker/entrypoint.sh)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
