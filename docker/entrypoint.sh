#!/bin/sh
set -e

# Instalar dependencias PHP si no existe vendor/ (clone fresco en VPS)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Solo el servicio principal (app) corre migrations y genera la key
if [ "$RUN_SETUP" = "true" ]; then
    # Generar APP_KEY si está vacía
    if [ -z "$APP_KEY" ]; then
        echo "==> Generating APP_KEY..."
        php artisan key:generate --force
    fi

    echo "==> Running migrations..."
    php artisan migrate --force --no-interaction

    php artisan storage:link --force 2>/dev/null || true
fi

exec "$@"
