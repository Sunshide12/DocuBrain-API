# DocuBrain API — Guía de Deploy a VPS

## Estructura

```
├── Dockerfile                    ← Multi-stage: deps cacheadas + imagen final slim
├── docker-compose.yml       ← 3 contenedores: app, postgres, redis
├── .env.production               ← Template de variables (copiar a .env)
├── .dockerignore                 ← Excluye basura del build context
├── deploy.sh                     ← Script automático de deploy
└── docker/
    ├── entrypoint.sh             ← Espera deps + migra + cachea
    ├── supervisord.conf          ← Corre app + reverb + queue en 1 contenedor
    ├── php.ini                   ← Config PHP producción
    └── postgres/
        ├── Dockerfile            ← PostgreSQL 16 + pgvector
        └── init.sql              ← Habilita extensión vector
```

## Qué cambió vs tu setup anterior

| Antes (5 contenedores)                | Ahora (3 contenedores)                  |
|---------------------------------------|------------------------------------------|
| app, reverb, queue = 3 builds iguales | 1 solo contenedor con Supervisor         |
| composer install en cada arranque     | Deps bakeadas en la imagen (multi-stage) |
| Sin cache de config/rutas             | config:cache + route:cache + view:cache  |
| Sin espera de deps (race conditions)  | Entrypoint espera Postgres + Redis       |
| Error YAML en queue service           | YAML limpio y validado                   |

## Pasos

### 1. En tu VPS — instalar Docker (solo la primera vez)

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
# Cierra sesión y vuelve a entrar
```

### 2. En tu máquina local — configurar SSH

```bash
# Generar clave si no tienes una
ssh-keygen -t ed25519

# Copiarla al VPS
ssh-copy-id usuario@tu-vps-ip

# Verificar que entras sin contraseña
ssh usuario@tu-vps-ip
```

### 3. Integrar estos archivos en tu repo

Copia todos los archivos de esta carpeta a la raíz de tu proyecto docubrain-api.

### 4. Configurar el .env en el VPS

```bash
ssh usuario@tu-vps-ip
cd /opt/docubrain
cp .env.production .env
nano .env   # ← Cambiar DB_PASSWORD, APP_URL, REVERB keys, etc.
```

### 5. Deploy

```bash
chmod +x deploy.sh
./deploy.sh usuario@tu-vps-ip /opt/docubrain
```

## Comandos útiles en el VPS

```bash
cd /opt/docubrain

# Ver estado
docker compose -f docker-compose.yml ps

# Ver logs en tiempo real
docker compose -f docker-compose.yml logs -f

# Solo logs del app
docker compose -f docker-compose.yml logs -f app

# Reiniciar
docker compose -f docker-compose.yml restart app

# Ejecutar artisan
docker compose -f docker-compose.yml exec app php artisan tinker

# Rollback de migración
docker compose -f docker-compose.yml exec app php artisan migrate:rollback
```
