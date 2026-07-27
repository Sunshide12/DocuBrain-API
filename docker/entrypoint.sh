#!/bin/sh
set -e

echo "╔══════════════════════════════════════╗"
echo "║   DocuBrain API — Starting...        ║"
echo "╚══════════════════════════════════════╝"

# ── 1. Wait for PostgreSQL ──────────────────────────────────
echo "==> Waiting for PostgreSQL (${DB_HOST}:${DB_PORT:-5432})..."
MAX_TRIES=30
TRIES=0
until php -r "
    \$c = @pg_connect('host=' . getenv('DB_HOST') . ' port=' . (getenv('DB_PORT') ?: '5432') . ' dbname=' . getenv('DB_DATABASE') . ' user=' . getenv('DB_USERNAME') . ' password=' . getenv('DB_PASSWORD'));
    if (!\$c) { exit(1); }
    pg_close(\$c);
" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "    [ERROR] PostgreSQL not reachable after ${MAX_TRIES} attempts. Aborting."
        exit 1
    fi
    echo "    Not ready yet (attempt ${TRIES}/${MAX_TRIES}), retrying in 2s..."
    sleep 2
done
echo "    PostgreSQL is up!"

# ── 2. Wait for Redis ──────────────────────────────────────
echo "==> Waiting for Redis (${REDIS_HOST:-redis}:${REDIS_PORT:-6379})..."
TRIES=0
until php -r "
    \$r = @fsockopen(getenv('REDIS_HOST') ?: 'redis', getenv('REDIS_PORT') ?: 6379, \$errno, \$errstr, 3);
    if (!\$r) { exit(1); }
    fclose(\$r);
" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "    [ERROR] Redis not reachable after ${MAX_TRIES} attempts. Aborting."
        exit 1
    fi
    echo "    Not ready yet (attempt ${TRIES}/${MAX_TRIES}), retrying in 2s..."
    sleep 2
done
echo "    Redis is up!"

# ── 3. Generate APP_KEY if not set ─────────────────────────
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force
fi

# ── 4. Run migrations ──────────────────────────────────────
echo "==> Running migrations..."
if ! php artisan migrate --force --no-interaction; then
    echo "    [ERROR] Migrations failed. Check database connection and migration files."
    exit 1
fi

# ── 5. Cache config/routes/views ──────────────────────────
echo "==> Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 6. Storage link ────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

echo ""
echo "==> All checks passed. Launching services..."
echo ""

exec "$@"
