# DocuBrain — Instrucciones, Aprendizajes y Despliegue

> Guía de referencia personal: qué hacer en cada fase, qué aprendo, y cómo
> llegar al despliegue real 100% gratuito.

---

## Cómo usar este documento

Antes de empezar cada fase:
1. Lee la sección de esa fase aquí
2. Abre `fasesToDo.md` y marca los entregables conforme los completes
3. Cuando todos los entregables estén marcados, la fase está terminada

Regla de oro: **una fase termina cuando sus tests pasan, no cuando el código existe**.

---

## ?? Mapa Mental del Proyecto

```
PDF subido
   +-? ProcessDocumentJob (Redis queue / sync)
         +-? TextExtractor  ? texto plano
         +-? Chunker        ? N chunks de 500 tokens
         +-? EmbeddingProvider (OpenRouter) ? vector[1536] por chunk
         +-? DocumentChunk guardado en pgvector

Pregunta del usuario
   +-? sendMessage mutation
         +-? EmbeddingProvider ? vector[1536] de la pregunta
         +-? Similarity search ? top 5 chunks (cosine distance)
         +-? AnswerGenerator (OpenRouter LLM) ? respuesta + fuentes
         +-? Message guardado con source_chunk_ids
```

---

## ? Fase 0 — Bootstrap

### Qué aprendes
- **Docker Compose multi-servicio**: cómo orquestar app + base de datos + cache con
  un solo `docker compose up`
- **Healthchecks**: por qué son críticos — evitan que la app arranque antes de que
  Postgres esté listo
- **GraphQL desde el día 0**: la ventaja de API-first es que todo lo que construyes
  después ya tiene un contrato definido
- **pgvector**: cómo instalar una extensión de Postgres compilando desde fuente
  cuando no está disponible en el paquete oficial

### Recomendaciones
- Ejecuta `docker compose up --build` y verifica que los tres servicios pasen
  sus healthchecks antes de continuar
- Abre GraphiQL (`http://localhost:8000/graphiql`) y prueba manualmente
  `register` ? `login` ? `me`
- Lee el Dockerfile de Postgres línea por línea — entender cómo se compila
  pgvector te servirá cuando actualices versiones

---

## ? Fase 1 — Testing

### Qué aprendes
- **PHPUnit con Lighthouse**: cómo testear resolvers GraphQL con `$this->graphQL()`
  y `$this->multipartGraphQL()` para file uploads
- **Fakes de Laravel**: `Storage::fake()`, `Bus::fake()`, `Event::fake()` — por qué
  sustituir implementaciones reales en tests acelera la suite y la hace determinista
- **Factories con Faker**: generar datos realistas y variados sin fixtures estáticos
- **Patrón AAA**: Arrange (preparar datos) ? Act (ejecutar acción) ? Assert (verificar resultado)

### Recomendaciones
- Después de cada test que escribas, corre `vendor/bin/phpunit --filter NombreDelTest`
  para confirmar que pasa en aislamiento
- Usa `assertDatabaseHas()` y `assertDatabaseMissing()` para verificar estado de DB,
  no solo la respuesta HTTP
- Si un test requiere más de 3 mocks, puede ser señal de que el código tiene
  demasiadas responsabilidades (principio de responsabilidad única)

---

## ? Fase 2 — GitHub Actions

### Qué aprendes
- **CI/CD básico**: el pipeline corre en cada push — te avisa si rompiste algo
  antes de que llegue a producción
- **Cache de dependencias**: por qué cachear `vendor/` reduce el tiempo del pipeline
  de 3–4 min a 30–60 seg en ejecuciones subsiguientes
- **Entornos efímeros**: cada job de CI arranca en un servidor limpio — buena práctica
  para detectar dependencias implícitas de entorno

### Recomendaciones
- El workflow actual usa SQLite. En Fase 9 migrará a PostgreSQL porque `vector(1536)`
  no existe en SQLite — planifícalo desde ya para no sorprenderte
- Añade un badge de CI al `README.md` para ver el estado a golpe de vista
- Crea la rama `main` (protegida) y trabaja siempre en `dev` o feature branches

---

## ? Fase 3 — SQL Avanzado

### Qué aprendes
- **EXPLAIN ANALYZE**: cómo interpretar el plan de ejecución de una query y detectar
  sequential scans que deberían ser index scans
- **Índices compuestos**: `(user_id, created_at)` es mucho más eficiente que dos
  índices simples para queries de paginación por usuario
- **N+1 en GraphQL**: cuando Lighthouse ejecuta una query de lista, puede disparar
  una query a la DB por cada elemento — los dataloaders agrupan en una sola query

### Recomendaciones
- Ejecuta `EXPLAIN ANALYZE SELECT * FROM documents WHERE user_id = 1 ORDER BY created_at DESC LIMIT 10`
  antes y después de agregar el índice compuesto — guarda ambas salidas en
  `docs/decisions/001-documents-query-performance.md`
- La diferencia típica: sin índice `Seq Scan cost=0..450`, con índice
  `Index Scan cost=0..8` — el número habla por sí solo
- Para el N+1: en Lighthouse, `@belongsTo` ya usa batching automático. Para
  relaciones más complejas en Fase 6, usa `BatchLoader` o `@with`

---

## ? Fase 4 — Redis

### Qué aprendes
- **Queue Jobs**: separar el trabajo pesado (extracción + embeddings) de la respuesta
  HTTP — el usuario no espera, el servidor trabaja en background
- **Pub/Sub vs Queues**: son dos cosas distintas en Redis:
  - La **queue** es una lista donde el worker consume trabajos (persistente)
  - El **Pub/Sub** es un canal de mensajes en tiempo real (no persistente)
- **Cache versioning**: invalidar cache sin `SCAN+DEL` — incrementar un contador
  cambia la clave y hace que el siguiente request genere datos frescos
- **Redis::spy()**: cómo testear llamadas a servicios externos sin una conexión real

### Recomendaciones
- Entiende bien la diferencia entre `Bus::fake()` y `Queue::fake()` — el primero
  intercepta el command bus (lo que usa `ProcessDocumentJob::dispatch()`),
  el segundo intercepta el driver de cola
- En producción con `QUEUE_CONNECTION=sync`, el job se ejecuta en el mismo request.
  Configura un timeout generoso en tu servidor para PDFs grandes
- El Pub/Sub de progreso (`docubrain.document.{id}`) queda listo para conectarse
  a la Subscription de GraphQL en Fase 6 — ya lo tienes hecho

---

## ?? Fase 5 — Nginx + Rate Limiting + Validación

### Qué aprendes
- **Reverse proxy con Nginx**: cómo Nginx recibe la request HTTP y la reenvía al
  proceso PHP sin exponerlo directamente a internet
- **Headers de seguridad**: por qué `X-Content-Type-Options: nosniff` previene
  ataques de MIME sniffing, qué hace `X-Frame-Options: DENY`, etc.
- **Rate limiting en Laravel**: cómo usar `RateLimiter::for()` para proteger
  endpoints con diferentes límites según el contexto (auth vs guest, upload vs query)
- **Validación de archivos a nivel binario**: la extensión `.pdf` es solo un nombre —
  los magic bytes `%PDF-` al inicio del archivo revelan el tipo real

### Recomendaciones

#### Nginx
```nginx
# docker/nginx/default.conf — configuración mínima pero correcta
server {
    listen 80;
    server_name _;
    client_max_body_size 50m;

    # Headers de seguridad
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        proxy_pass         http://app:8000;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

#### Validación de PDF (anti-vulnerabilidades)
```php
// Verificar magic bytes — los PDFs empiezan con %PDF-
$finfo = new finfo(FILEINFO_MIME_TYPE);
$realMime = $finfo->file($file->getPathname());
if ($realMime !== 'application/pdf') {
    throw new \Exception('File must be a valid PDF');
}

// También verificar los primeros 5 bytes del binario
$handle = fopen($file->getPathname(), 'r');
$header = fread($handle, 5);
fclose($handle);
if ($header !== '%PDF-') {
    throw new \Exception('Invalid PDF header');
}
```

#### Rate Limiting
```php
// AppServiceProvider::boot()
RateLimiter::for('api', function (Request $request) {
    return $request->user()
        ? Limit::perMinute(60)->by($request->user()->id)
        : Limit::perMinute(20)->by($request->ip());
});

RateLimiter::for('uploads', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

---

## ?? Fase 6 — GraphQL Avanzado

### Qué aprendes
- **GraphQL Subscriptions**: comunicación en tiempo real sobre WebSockets — el
  cliente se suscribe a un canal y recibe mensajes cuando el servidor los publica
- **Tipos complejos en GraphQL**: tipos anidados, enums, JSON scalars, relaciones
  bidireccionales
- **Autorización a nivel de resolver**: cómo verificar que un usuario solo puede
  mutar/leer sus propios recursos — no basta con autenticarse

### Recomendaciones
- La Subscription de progreso conecta directamente con el `Redis::publish()` de
  Fase 4 — revisa la documentación de Lighthouse Subscriptions para la configuración
  del broadcasting driver
- Si las Subscriptions resultan complejas de configurar con `QUEUE_CONNECTION=sync`,
  implementa un endpoint de polling: `query documentStatus(id: ID!): Document`
  y el frontend consulta cada 2 segundos hasta que `status === 'ready'`
- Aplica el global scope `owned` en `Conversation` desde el principio para evitar
  bugs de seguridad en Fase 8 cuando el chat maneje datos sensibles

---

## ?? Fase 7 — Vue 3 + Vuetify (Frontend)

### Qué aprendes
- **Apollo Client**: gestión de estado GraphQL — caché automática, optimistic UI,
  refetch después de mutations
- **Composition API de Vue 3**: `ref()`, `computed()`, `watch()`, `onMounted()` —
  la forma moderna de estructurar componentes
- **Vitest**: testing de componentes Vue — qué renderiza el componente, qué emite,
  cómo responde a inputs del usuario
- **Cloudflare Pages**: deploy automático desde GitHub — cada push a `main`
  genera un nuevo deployment

### Recomendaciones
- Usa `useQuery` y `useMutation` de `@vue/apollo-composable` — son el equivalente
  de hooks de React para Vue + Apollo
- Para el polling de progreso de documentos, usa `useQuery` con la opción
  `pollInterval: 2000` mientras `status !== 'ready'`
- Configura Vuetify en modo dark por defecto — DocuBrain es una herramienta de
  trabajo, el dark mode reduce la fatiga visual
- Para SPA routing en Cloudflare Pages, crea `public/_redirects`:
  ```
  /* /index.html 200
  ```

---

## ?? Fase 8 — pgvector / RAG (El corazón del proyecto)

### Qué aprendes
- **Embeddings**: vectores de alta dimensión que capturan el "significado" semántico
  de un texto — textos similares tienen vectores similares
- **Chunking strategy**: cómo dividir un documento grande en fragmentos consultables
  sin perder contexto en los bordes (overlap de 50 tokens)
- **Similarity search**: buscar los chunks más relevantes para una pregunta usando
  distancia coseno en espacio de 1536 dimensiones
- **Prompt engineering básico**: cómo construir un prompt que le diga al LLM
  "responde SOLO con esta información, cita tus fuentes"
- **Interfaces + dependency injection**: el patrón que permite cambiar de
  OpenRouter a otro proveedor sin tocar la lógica de negocio

### Recomendaciones

#### Chunking por tokens (implementación simple)
```php
public function chunk(string $text, int $chunkSize = 500, int $overlap = 50): array
{
    // Aproximación: 1 token ˜ 0.75 palabras en inglés / español
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $tokenWords = (int) ceil(count($words) * 0.75); // estimación de tokens
    $wordsPerChunk = (int) ceil($chunkSize / 0.75);
    $overlapWords  = (int) ceil($overlap / 0.75);

    $chunks = [];
    $i = 0;
    while ($i < count($words)) {
        $chunk = array_slice($words, $i, $wordsPerChunk);
        $chunks[] = implode(' ', $chunk);
        $i += ($wordsPerChunk - $overlapWords);
    }
    return $chunks;
}
```

#### Prompt para el LLM (sin historial)
```
Eres un asistente que responde preguntas sobre documentos.
Responde ÚNICAMENTE usando la información de los fragmentos de contexto proporcionados.
Si la información no está en el contexto, di "No encuentro esta información en el documento".
Cita el número de fragmento (ej: [Fragmento 2]) cuando uses información de él.

CONTEXTO:
[Fragmento 1]: {chunk1_content}
[Fragmento 2]: {chunk2_content}
[Fragmento 3]: {chunk3_content}

PREGUNTA: {user_question}
```

#### Cómo agregar historial a futuro (documentar en docs/decisions/)
Para añadir contexto de conversación, modificar `AnswerGenerator::generate()`:
```php
// Firma actual (sin historial):
public function generate(string $question, array $contextChunks): AnswerResult;

// Firma futura (con historial):
public function generate(
    string $question,
    array $contextChunks,
    array $previousMessages = []  // [['role' => 'user'|'assistant', 'content' => '...']]
): AnswerResult;
```
**Impacto en costos:** Cada mensaje previo añade ~200-500 tokens al request.
Con 10 mensajes de historial: ~3.000-5.000 tokens extra por request.
A precios de Claude 3.5 Haiku (~$0.001/1K tokens): ~$0.003-0.005 extra por pregunta.

#### Cosine distance en pgvector
```php
// En el modelo DocumentChunk:
public static function findSimilar(
    array $embedding,
    int $limit = 5,
    ?int $documentId = null,
    int $userId
): Collection {
    $vector = '[' . implode(',', $embedding) . ']';

    return static::query()
        ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
        ->where('documents.user_id', $userId)
        ->when($documentId, fn($q) => $q->where('document_chunks.document_id', $documentId))
        ->orderByRaw("embedding <=> ?::vector", [$vector])
        ->limit($limit)
        ->select('document_chunks.*')
        ->get();
}
```

---

## ?? Fase 9 — Despliegue 100% Gratuito

### Qué aprendes
- **Infraestructura real**: conectar servicios de distintos proveedores con variables
  de entorno — no todo tiene que vivir en el mismo servidor
- **Seguridad en producción**: `APP_DEBUG=false`, CORS restrictivo, introspección
  GraphQL desactivada, secrets fuera del repositorio
- **Nginx + PHP-FPM**: la arquitectura correcta para producción (no `php artisan serve`)
- **Cloudflare como CDN**: cómo un proxy de edge reduce la latencia y protege
  el origen de ataques directos

### Paso a paso del despliegue gratuito

#### 1 — Neon (PostgreSQL con pgvector)
1. Crear cuenta en [neon.tech](https://neon.tech) (gratis, sin tarjeta)
2. Crear proyecto ? copiar `DATABASE_URL` (formato: `postgresql://user:pass@host/db?sslmode=require`)
3. En el primer deploy, ejecutar: `php artisan migrate --force`
4. Verificar que `vector` está activo: `SELECT extname FROM pg_extension WHERE extname = 'vector'`

#### 2 — Upstash (Redis serverless)
1. Crear cuenta en [upstash.com](https://upstash.com) (gratis)
2. Crear base de datos Redis ? activar TLS ? copiar `REDIS_URL`
3. En Laravel: `REDIS_URL=rediss://...` (con doble `s` para TLS)
4. Verificar con `php artisan tinker` ? `Redis::ping()`

#### 3 — Cloudflare R2 (Storage de PDFs)
1. En el dashboard de Cloudflare ? R2 ? Crear bucket `docubrain-pdfs`
2. Crear API token con permisos de R2
3. Variables de entorno en Laravel:
   ```env
   AWS_ACCESS_KEY_ID=tu_r2_key_id
   AWS_SECRET_ACCESS_KEY=tu_r2_secret
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=docubrain-pdfs
   AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=true
   FILESYSTEM_DISK=s3
   ```
4. Instalar SDK: `composer require league/flysystem-aws-s3-v3`

#### 4 — Render (Backend Laravel)
1. Crear cuenta en [render.com](https://render.com) (gratis)
2. New ? Web Service ? conectar repositorio GitHub
3. Runtime: Docker (usa el Dockerfile de producción de Fase 9)
4. Variables de entorno: pegar todas las de producción
5. Health check path: `/up`
6. Free plan: el servicio duerme tras 15 min de inactividad (cold start ~30-60s)

**Dockerfile de producción (multi-stage):**
```dockerfile
# Fase 1: Build
FROM composer:latest AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Fase 2: Runtime
FROM php:8.5-fpm-alpine

RUN apk add --no-cache nginx poppler-utils postgresql-dev \
 && docker-php-ext-install pdo_pgsql

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY backend/ /var/www/html/
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

RUN php artisan config:cache && php artisan route:cache

EXPOSE 80
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
```

#### 5 — Cloudflare Pages (Frontend Vue 3)
1. En Cloudflare Dashboard ? Pages ? Crear proyecto ? conectar GitHub
2. Framework preset: Vite
3. Build command: `cd frontend && npm ci && npm run build`
4. Build output: `frontend/dist`
5. Variable: `VITE_API_URL=https://api.tudominio.com`
6. Deploy automático en cada push a `main`

#### 6 — DNS en Cloudflare
```
api.tudominio.com   CNAME   tu-app.onrender.com   [Proxy: ON]
app.tudominio.com   CNAME   docubrain.pages.dev    [Proxy: ON]
```
- SSL/TLS mode: **Full (strict)**
- Cloudflare gestiona el certificado automáticamente

#### 7 — Sentry (Error tracking)
```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=https://tu_dsn@sentry.io/tu_project
```
Variable: `SENTRY_DSN=https://...`

### Variables de entorno finales de producción
```env
APP_NAME=DocuBrain
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.tudominio.com

LOG_CHANNEL=stderr

DB_CONNECTION=pgsql
DATABASE_URL=postgresql://user:pass@neon-host/docubrain?sslmode=require

QUEUE_CONNECTION=sync
CACHE_STORE=redis
REDIS_URL=rediss://...

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=docubrain-pdfs
AWS_ENDPOINT=https://<id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

SENTRY_DSN=https://...@sentry.io/...

OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
EMBEDDING_MODEL=openai/text-embedding-3-small
LLM_MODEL=anthropic/claude-3.5-haiku
```

### Checklist final antes de declarar "en producción"
- [ ] `APP_DEBUG=false` confirmado
- [ ] Introspección GraphQL desactivada en `config/lighthouse.php`
- [ ] `.env` en `.gitignore` (nunca commiteado)
- [ ] CORS solo permite `app.tudominio.com`
- [ ] Sentry recibe un evento de prueba
- [ ] R2 almacena y sirve un PDF de prueba
- [ ] Rate limiting activo (probar con curl en bucle)
- [ ] Smoke test completo:
  Register ? Login ? Upload PDF ? wait ? Ask question ? get answer with sources

---

## Resumen de Habilidades que Desarrolla DocuBrain

| Habilidad | Dónde se practica en DocuBrain |
|-----------|-------------------------------|
| Integración de IA / RAG | Fase 8 — pipeline completo |
| System Design | Todo el proyecto — decisiones de arquitectura |
| Containerización (Docker) | Fase 0 |
| CI/CD | Fase 2 |
| Cloud e Infraestructura | Fase 9 |
| SQL avanzado + Vectores | Fase 3 + Fase 8 |
| API Design (GraphQL) | Fase 0 + Fase 6 |
| Frontend moderno (Vue 3) | Fase 7 |
| Caching + Sistemas distribuidos | Fase 4 |
| Seguridad (auth, rate limiting, validación) | Fase 0 + Fase 5 |
