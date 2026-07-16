# DocuBrain — Roadmap de Fases

> Las fases 0–4 están **completadas**. Las fases 5–9 incorporan todas las
> decisiones arquitectónicas confirmadas el 2026-07-11.

---

## Decisiones Arquitectónicas Confirmadas

| # | Decisión | Valor elegido |
|---|----------|---------------|
| 1 | Rate limiting | Middleware Laravel por token/IP |
| 2 | Tamaño máximo PDF | 50 MB |
| 3 | Storage de archivos | Cloudflare R2 (S3-compatible) |
| 4 | Queue worker producción | `QUEUE_CONNECTION=sync` (procesamiento síncrono, $0) |
| 5 | Estrategia de chunking | Por tokens — 500 tokens, 50 de overlap |
| 6 | Chat con historial | Sin contexto de conversación; cada pregunta independiente |
| 7 | Similarity search | Cosine distance, sin índice vectorial inicial (HNSW después) |
| 8 | Frontend hosting | Cloudflare Pages |
| 9 | Logging producción | Sentry Free (5 K eventos/mes) |
| 10 | Tipos de archivo | Solo PDF — validación estricta + anti-vulnerabilidades |
| 11 | Multi-tenant | Simple por `user_id` con global scope en modelos |
| 12 | Embeddings / LLM | OpenRouter API, API key configurable por `.env` |

### Stack de Despliegue 100% Gratuito

| Servicio | Proveedor | Costo |
|----------|-----------|-------|
| Backend Laravel (web service) | Render Free | $0 |
| PostgreSQL + pgvector | Neon Serverless (500 MB) | $0 |
| Redis (colas + caché) | Upstash Serverless (256 MB / 500 K cmds) | $0 |
| Storage PDFs | Cloudflare R2 (10 GB/mes) | $0 |
| Frontend SPA | Cloudflare Pages | $0 |
| DNS + SSL + CDN | Cloudflare (dominio propio) | $0 |
| Error tracking | Sentry Free | $0 |
| **Total mensual** | | **$0** |

---

## ? Fase 0 — Bootstrap (COMPLETADA)

Laravel 13 + Sanctum + Lighthouse con schema mínimo (`me` query, `register`/`login`/`logout` mutations).
Docker Compose con Postgres+pgvector y Redis levantados y conectados.

**Entregables completados:**
- `docker-compose.yml` — 3 servicios (app, postgres, redis) con healthchecks
- `docker/postgres/Dockerfile` — pgvector 0.8.4 compilado desde fuente sobre Postgres 18
- `docker/postgres/init.sql` — `CREATE EXTENSION IF NOT EXISTS vector`
- `docker/app/Dockerfile` — PHP 8.5-cli + pdo_pgsql, redis, pcntl, bcmath, zip
- `graphql/schema.graphql` — queries y mutations de autenticación
- `app/Models/User.php` — `HasApiTokens` + relación `documents()`
- `.env.example` — configuración completa para Docker

---

## ? Fase 1 — Testing (COMPLETADA)

Mutation `uploadDocument`, query `documents` paginada. Tests con PHPUnit + Lighthouse helpers.
Seeders y factories con Faker.

**Entregables completados:**
- `app/GraphQL/Mutations/UploadDocument.php` — multipart file upload
- `app/GraphQL/Queries/Documents.php` — paginación custom con `DocumentPaginator`
- `app/Models/Document.php` — `$fillable` + relación `user()`
- `database/factories/DocumentFactory.php` — Faker con status correcto (`ready`)
- `tests/Feature/GraphQL/AuthTest.php` — 4 tests (register, login, me, logout)
- `tests/Feature/GraphQL/DocumentTest.php` — 4 tests (upload, query, cache, invalidación)
- Migraciones `create_documents_table` + `add_error_message_to_documents_table`

---

## ? Fase 2 — GitHub Actions (COMPLETADA)

CI que corre PHPUnit en cada push/PR a `dev`, con cache de dependencias.

**Entregables completados:**
- `.github/workflows/phpunit.yml`
- PHP 8.5 con `shivammathur/setup-php`
- Cache de Composer + directorio vendor
- SQLite in-memory para tests en CI

---

## ? Fase 3 — SQL Avanzado (COMPLETADA — gaps menores pendientes)

Foreign keys, query scoping por `user_id`, relaciones Eloquent correctas.

**Entregables completados:**
- FK con `constrained()->cascadeOnDelete()` en documents
- Scoping por `user_id` en el resolver `Documents`
- `@belongsTo` en schema GraphQL

**Pendiente (completar al inicio de Fase 5):**
- [x] Migración con índice compuesto `(user_id, created_at)` en `documents`
- [x] Documento `docs/decisions/001-documents-query-performance.md` con EXPLAIN ANALYZE

---

## ? Fase 4 — Redis (COMPLETADA)

Job de procesamiento en cola real. Cache con versioning. Pub/Sub de progreso.

**Entregables completados:**
- `app/Jobs/ProcessDocumentJob.php` — pipeline stubbed con `Redis::publish()`
- `app/Events/DocumentProgressUpdated.php` — evento de progreso con `toArray()`
- `app/Events/DocumentUploaded.php` — evento post-upload
- `app/Listeners/InvalidateDocumentsCache.php` — cache versioning (no SCAN+DEL)
- `app/GraphQL/Queries/Documents.php` — `Cache::remember` con clave versionada
- `tests/Feature/GraphQL/ProcessDocumentJobTest.php` — 5 tests con `Redis::spy()`
- Event ? Listener binding en `AppServiceProvider`

---

## ✅ Fase 5 — Nginx + Rate Limiting + Validación + Multi-tenant (COMPLETADA)

### Objetivo
Todo el stack detrás de Nginx como reverse proxy. Rate limiting, validación estricta de
archivos PDF y global scope de multi-tenant.

### 5.1 — Cerrar gaps de Fase 3 (COMPLETADO)
- [x] Migración: índice compuesto `(user_id, created_at)` en `documents`
- [x] `docs/decisions/001-documents-query-performance.md` con EXPLAIN ANALYZE real

### 5.2 — Nginx Reverse Proxy
- [x] Agregar servicio `nginx` al `docker-compose.yml` (expone puerto `80`)
- [x] `docker/nginx/default.conf` — proxy a `app:8000`
- [x] `client_max_body_size 50m` para PDFs de hasta 50 MB
- [x] Servir archivos estáticos directamente desde Nginx
- [x] Headers de seguridad: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Strict-Transport-Security`
- [x] El puerto público pasa de `8000` (PHP) a `80` (Nginx)

### 5.3 — Rate Limiting en Laravel
- [x] Configurar `RateLimiter::for()` en `AppServiceProvider`:
  - `api` — 60 req/min por token autenticado
  - `uploads` — 10 req/min por IP para `uploadDocument`
  - `guest` — 20 req/min por IP para `register`/`login`
- [x] Headers de respuesta: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`

### 5.4 — Validación Estricta de PDF
- [x] Validar MIME type real del archivo (`application/pdf`)
- [x] Verificar magic bytes del binario (header `%PDF-`)
- [x] Rechazar archivos disfrazados (extensión `.pdf` pero contenido distinto)
- [x] Sanitizar nombre de archivo — prevenir path traversal
- [x] Limitar tamaño a 50 MB en la mutation y en `php.ini`
- [x] Actualizar `docker/app/php.ini`: `upload_max_filesize=50M`, `post_max_size=55M`

### 5.5 — Multi-tenant Global Scope
- [x] Global scope `owned` en `Document` que filtre por `auth()->id()` automáticamente
- [x] Mismo patrón aplicado a `Conversation` y `Message` (Fase 6)
- [x] Test: un usuario NO puede leer/modificar documentos de otro

### Tests
- [ ] Rate limiting: se rechaza la request N+1 con `429 Too Many Requests` (o error de GraphQL) (RateLimitTest pendiente para después)
- [x] PDF válido: se acepta correctamente
- [x] Archivo no-PDF (JPEG, PHP, etc.) disfrazado como `.pdf`: se rechaza
- [x] Multi-tenant isolation: query de un usuario no devuelve datos de otro

---

## ?? Fase 6 — GraphQL Avanzado

### Objetivo
Entidades de conversación y mensaje en el schema. Mutations de chat. Subscription de
progreso conectada al Pub/Sub de Fase 4.

### 6.1 — Modelos y Migraciones
- [x] Migración `create_conversations_table`:
  `id, user_id FK, document_id FK nullable, title nullable, timestamps`
- [x] Migración `create_messages_table`:
  `id, conversation_id FK, role enum(user,assistant), content text, source_chunk_ids json nullable, timestamps`
- [x] Modelo `Conversation` — global scope `owned`, relaciones `user`, `document`, `messages`
- [x] Modelo `Message` — relación `conversation`
- [x] Factories para ambos con Faker

### 6.2 — Schema GraphQL
- [x] Types `Conversation` y `Message`
- [x] `Query conversations` — paginada, autenticada
- [x] `Query conversation(id: ID!)` — con mensajes
- [x] `Mutation createConversation(document_id: ID, title: String): Conversation!`
- [x] `Mutation sendMessage(conversation_id: ID!, content: String!): Message!`
  - Guarda el mensaje del usuario
  - Devuelve un mensaje placeholder del assistant (RAG real en Fase 8)
- [x] `Mutation deleteConversation(id: ID!): Boolean!`
- [x] `Subscription documentProgress(document_id: ID!): DocumentProgress!`
  - Conecta con `Redis::publish()` de Fase 4
  - Alternativa: endpoint de polling si Subscriptions no encaja con sync processing

### Tests
- [x] CRUD de Conversation
- [x] CRUD de Message
- [x] `sendMessage` en conversación ajena ? error de autorización
- [x] Conversation sin `document_id` (búsqueda en toda la biblioteca) funciona

---

## ?? Fase 7 — Vue 3 + Vuetify (Frontend)

### Objetivo
SPA completa consumiendo la API GraphQL madura y testeada. Hospedada en Cloudflare Pages.

### 7.1 — Setup
- [ ] Crear `frontend/` con Vite + Vue 3 + Vuetify 3 + Apollo Client
- [ ] Variables de entorno: `VITE_API_URL`
- [ ] Proxy de desarrollo para evitar CORS en local

### 7.2 — Páginas y Componentes
- [ ] **Auth** — Login / Register con validación; token en `localStorage`
- [ ] **Dashboard** — Lista de documentos paginada con estado visual (pending/processing/ready/failed)
- [ ] **Upload** — Drag and drop de PDF con barra de progreso (polling de `status`)
- [ ] **Chat de documento** — Conversación sobre un PDF concreto:
  - Input + botón enviar
  - Mensajes tipo bubble (user / assistant)
  - Citas de fuentes (chunks) inline en respuestas del assistant
- [ ] **Biblioteca Chat** — Conversación sobre toda la biblioteca (`document_id: null`)
- [ ] **Historial** — Lista de conversaciones previas con preview del primer mensaje

### 7.3 — UX
- [ ] Tema oscuro (Vuetify dark mode)
- [ ] Responsive / mobile-first
- [ ] Loading states y manejo de errores en cada vista
- [ ] Toast de notificación cuando documento pasa a `ready`

### 7.4 — Deploy en Cloudflare Pages
- [ ] Build command: `cd frontend && npm ci && npm run build`
- [ ] Output directory: `frontend/dist`
- [ ] `_redirects` o `_routes.json` para SPA routing (404 ? `index.html`)
- [ ] Dominio custom: `app.tudominio.com`

### 7.5 — CORS en Backend
- [ ] `config/cors.php` — permitir solo el dominio de Cloudflare Pages
- [ ] Headers `Access-Control-Allow-Origin`, `Access-Control-Allow-Headers`

### Tests
- [ ] Vitest: componentes Login, DocumentList, ChatBubble
- [x] Test de integración: login -> upload -> documento aparece en lista

---

## ?? Fase 8 — pgvector / RAG

### Objetivo
Lógica de negocio core: extracción real de texto, chunking por tokens, embeddings,
similarity search con pgvector, y respuesta con citas usando OpenRouter.

### 8.1 — Modelo DocumentChunk
- [ ] Migración `create_document_chunks_table`:
  `id, document_id FK, chunk_index int, content text, token_count int, page_number int nullable, embedding vector(1536), created_at`
- [ ] Modelo `DocumentChunk` — relación `document()`
- [ ] Relación `chunks()` en `Document`
- [ ] Factory con Faker

### 8.2 — Interfaces de Servicio
- [ ] `App\Services\Contracts\EmbeddingProvider` — `embed(string): float[]`, `embedBatch(array): float[][]`
- [ ] `App\Services\Contracts\AnswerGenerator` — `generate(question, contextChunks): AnswerResult`
- [ ] `App\Services\Contracts\TextExtractor` — `extract(filePath): string`
- [ ] `AnswerResult` DTO (`answer: string`, `sourceChunkIds: int[]`)

### 8.3 — Implementaciones con OpenRouter
- [ ] `OpenRouterEmbeddingProvider` — modelo `openai/text-embedding-3-small`, 1536 dims
- [ ] `OpenRouterAnswerGenerator`:
  - Prompt con contexto de chunks + pregunta del usuario
  - Sin historial — cada pregunta es independiente
  - Devuelve respuesta + IDs de chunks usados como fuentes
  - Documentar en `docs/decisions/` cómo agregar historial a futuro y su impacto en costos
- [ ] `PdfTextExtractor` — usa `spatie/pdf-to-text`
- [ ] Binding de interfaces en `AppServiceProvider`
- [ ] Variables: `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`, `EMBEDDING_MODEL`, `LLM_MODEL`

### 8.4 — Pipeline Real en ProcessDocumentJob
- [ ] Step 1 — Extracting: `TextExtractor::extract(filePath)` ? string de texto
- [ ] Step 2 — Chunking: 500 tokens con 50 de overlap, guardar chunks
- [ ] Step 3 — Embedding: `EmbeddingProvider::embedBatch($chunkTexts)` ? guardar vectores
- [ ] Step 4 — Ready: marcar documento como `ready`
- [ ] Manejo de fallos de API (retry, guardar `error_message`)

### 8.5 — Similarity Search
- [ ] Cosine distance: `ORDER BY embedding <=> $queryVector LIMIT 5`
- [ ] Filtro por `document_id` (chat de documento específico)
- [ ] JOIN con `documents` para filtro por `user_id` (multi-tenant)
- [ ] Sin índice vectorial por ahora (fuerza bruta, suficiente para < 10 K chunks)

### 8.6 — RAG en sendMessage
- [ ] Generar embedding de la pregunta
- [ ] Buscar top 5 chunks más similares
- [ ] Llamar a `AnswerGenerator::generate()` con contexto
- [ ] Guardar mensaje user + mensaje assistant con `source_chunk_ids`
- [ ] Devolver mensaje assistant con citas incluidas

### Tests
- [ ] Chunking: texto conocido ? N chunks con overlap correcto
- [ ] Similarity search: embeddings mock ? chunk correcto retornado
- [ ] Pipeline completo con mocks de OpenRouter
- [ ] `sendMessage` end-to-end con mocks

---

## ?? Fase 9 — Integración y Despliegue

### Objetivo
Despliegue real, 100% gratuito. Pulido de UX y hardening de seguridad.

### 9.1 — Configurar Servicios Externos
- [ ] **Neon** — crear proyecto, habilitar `vector`, obtener `DATABASE_URL`
- [ ] **Upstash** — crear instancia Redis, obtener URL TLS
- [ ] **Cloudflare R2** — crear bucket, credenciales, configurar `FILESYSTEM_DISK=s3`
- [ ] **Sentry** — instalar `sentry/sentry-laravel`, configurar `SENTRY_DSN`

### 9.2 — Dockerfile de Producción
- [ ] Multi-stage build: PHP 8.5-fpm + Nginx en un solo container
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan config:cache && route:cache && view:cache`
- [ ] `poppler-utils` instalado (pdftotext)
- [ ] `php artisan migrate --force` en build step de Render

### 9.3 — DNS y Routing en Cloudflare
- [ ] `api.tudominio.com` ? CNAME a `tu-app.onrender.com` (proxy naranja)
- [ ] `app.tudominio.com` ? Cloudflare Pages (automático)
- [ ] SSL mode: Full (strict)
- [ ] Firewall rule básica contra bots

### 9.4 — Hardening
- [ ] CORS: solo `app.tudominio.com`
- [ ] Deshabilitar introspección GraphQL en producción
- [ ] Endpoint `/up` (health check para Render)
- [ ] `LOG_CHANNEL=stderr` para que Render capture logs

### 9.5 — CI/CD Actualizado para pgvector
- [ ] Agregar servicio PostgreSQL con extensión `vector` al workflow de CI
  (SQLite no soporta `vector(1536)` de Fase 8)
- [ ] Opcional: auto-deploy a Render en merge a `main`

### Smoke test final en producción
- [ ] Register ? Login ? Upload PDF ? esperar `ready` ? Ask question ? recibir respuesta con fuentes
- [ ] Verificar rate limiting, Sentry, R2, cold start de Render
