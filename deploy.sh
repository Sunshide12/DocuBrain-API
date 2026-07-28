#!/bin/bash
# ============================================================
# DocuBrain API — Deploy to VPS
# ============================================================
# Usage:
#   ./deploy.sh                    (uses defaults)
#   ./deploy.sh user@ip /opt/app   (custom host & path)
#
# Prerequisites on VPS:
#   - Docker + Docker Compose v2
#   - SSH key configured (no password prompts)
# ============================================================

set -euo pipefail

# ── Config ─────────────────────────────────────────────────
VPS_HOST="${1:-usuario@tu-vps-ip}"
REMOTE_DIR="${2:-/opt/docubrain}"
COMPOSE_FILE="docker-compose.yml"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
fail()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# ── Pre-flight checks ─────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════╗"
echo "║   DocuBrain — Deploy to Production   ║"
echo "╚══════════════════════════════════════╝"
echo ""

if [ "$VPS_HOST" = "usuario@tu-vps-ip" ]; then
    fail "Configura VPS_HOST: ./deploy.sh user@ip"
fi

# Test SSH connection
log "Testing SSH connection to ${VPS_HOST}..."
ssh -o ConnectTimeout=10 -o BatchMode=yes "$VPS_HOST" "echo ok" > /dev/null 2>&1 \
    || fail "No se puede conectar por SSH a ${VPS_HOST}. ¿Tienes tu clave SSH configurada?"

# Verify Docker on VPS
log "Checking Docker on VPS..."
ssh "$VPS_HOST" "docker compose version" > /dev/null 2>&1 \
    || fail "Docker Compose v2 no está instalado en el VPS."

# ── Step 1: Sync files ────────────────────────────────────
log "Syncing project to ${VPS_HOST}:${REMOTE_DIR}..."
ssh "$VPS_HOST" "mkdir -p ${REMOTE_DIR}"

rsync -avz --delete \
    --exclude '.git' \
    --exclude 'node_modules' \
    --exclude 'vendor' \
    --exclude '.env' \
    --exclude 'storage/logs/*' \
    --exclude 'storage/framework/cache/*' \
    --exclude 'storage/framework/sessions/*' \
    --exclude 'storage/framework/views/*' \
    ./ "${VPS_HOST}:${REMOTE_DIR}/"

log "Files synced."

# ── Step 2: Ensure .env exists on VPS ─────────────────────
ssh "$VPS_HOST" "
    if [ ! -f ${REMOTE_DIR}/.env ]; then
        echo '${YELLOW}[!] No .env found — copying template...${NC}'
        cp ${REMOTE_DIR}/.env.production ${REMOTE_DIR}/.env
        echo '${RED}[!] IMPORTANT: Edit ${REMOTE_DIR}/.env on the VPS before continuing!${NC}'
        exit 1
    fi
"
if [ $? -ne 0 ]; then
    warn "Se copió .env.production → .env en el VPS."
    warn "Edita el archivo y vuelve a correr el deploy:"
    echo "    ssh ${VPS_HOST} nano ${REMOTE_DIR}/.env"
    echo "    ./deploy.sh ${VPS_HOST} ${REMOTE_DIR}"
    exit 0
fi

# ── Step 3: Build & Deploy ────────────────────────────────
log "Building and deploying on VPS..."
ssh "$VPS_HOST" "
    set -e
    cd ${REMOTE_DIR}

    echo '==> Pulling base images...'
    docker compose -f ${COMPOSE_FILE} pull redis 2>/dev/null || true

    echo '==> Building app image...'
    docker compose -f ${COMPOSE_FILE} build --no-cache app

    echo '==> Starting services...'
    docker compose -f ${COMPOSE_FILE} up -d

    echo '==> Waiting for health check (max 120s)...'
    SECONDS=0
    until docker compose -f ${COMPOSE_FILE} ps app | grep -q 'healthy'; do
        if [ \$SECONDS -ge 120 ]; then
            echo 'Health check timed out. Logs:'
            docker compose -f ${COMPOSE_FILE} logs --tail=50 app
            exit 1
        fi
        sleep 5
    done

    echo '==> Cleaning old images...'
    docker image prune -f
"

# ── Done ──────────────────────────────────────────────────
echo ""
log "Deploy complete! 🚀"
echo ""
echo "    App:        http://${VPS_HOST%%@*}...:${APP_PORT:-8001}"
echo "    WebSocket:  ws://${VPS_HOST%%@*}...:${REVERB_SERVER_PORT:-8081}"
echo ""
echo "    Logs:       ssh ${VPS_HOST} 'cd ${REMOTE_DIR} && docker compose -f ${COMPOSE_FILE} logs -f'"
echo "    Status:     ssh ${VPS_HOST} 'cd ${REMOTE_DIR} && docker compose -f ${COMPOSE_FILE} ps'"
echo ""
