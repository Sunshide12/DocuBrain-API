# DocuBrain — Spec de proyecto y roadmap de aprendizaje

> Cómo usar este documento: pégalo completo como contexto inicial en Claude Code y pídele que construya la **Fase 0** primero (sección 6). Cada fase siguiente se le pasa como un pedido separado, apuntando a este mismo documento para que mantenga consistencia de arquitectura.

## 1. Visión y alcance

DocuBrain es un gestor de documentos con preguntas y respuestas (RAG). Un usuario sube PDFs, el sistema los procesa en background (extracción de texto → chunking → embeddings), y el usuario puede conversar sobre un documento específico o sobre toda su biblioteca.

**Prioridad del proyecto:** aprendizaje profundo, sin fecha límite fija. Cada fase se aplica directamente sobre este mismo repo — no hay ejercicios aislados que luego haya que integrar a la fuerza al final.

**Fuera de alcance para v1** (candidatos a "fase 9" si sobra tiempo/interés):
- Multi-tenant real con organizaciones/equipos — v1 es single-user con auth simple
- OCR para PDFs escaneados (solo texto extraíble nativamente)
- Streaming de respuesta token por token en el chat

## 2. Decisiones de arquitectura

| Decisión | Elección | Por qué |
|---|---|---|
| API | GraphQL vía Lighthouse, **fundacional desde Fase 0** | Evita reconstruir en fase 6 lo que ya existía como REST |
| Frontend | Next.js (React) + Shadcn/UI + TanStack Query | Se migró de Vue a Next.js (Fase 7) para mejor SSR, UI moderna y arquitectura híbrida de Auth (Sanctum HttpOnly). |
| Base de datos | PostgreSQL + pgvector | Un solo motor para datos relacionales y vectores |
| Cache/Colas | Redis | Jobs de procesamiento de PDF + Pub/Sub para progreso en vivo |
| Embeddings | **Supuesto: OpenRouter API (`text-embedding-3-small`)** (1536 dims) | Más documentación/tutoriales Laravel+pgvector que alternativas. Alternativa real: Voyage AI (partner de Anthropic) |
| LLM de respuesta | **Supuesto: OpenRouter API** (Haiku para costo, Sonnet si la calidad de respuesta lo justifica) | Ya estás en este ecosistema |
| Extracción de PDF | `spatie/pdf-to-text` | Más robusto que alternativas puras PHP; fácil de swap por OCR después |
| Infra | Docker Compose (app, postgres+pgvector, redis, nginx) | Todo reproducible en local desde el día 0 |
| Despliegue real (dominio + Certbot) | **Pendiente de decidir** — no es lo mismo aprender Nginx local que operar TLS en producción |

Tanto el cliente de embeddings como el de LLM van detrás de una interfaz (`EmbeddingProvider`, `AnswerGenerator` o similar) para poder cambiarlos sin tocar lógica de negocio — es en sí mismo un buen punto de aprendizaje de diseño.

## 3. Modelo de dominio

**User** — default de Laravel + Sanctum (`id, name, email, password`)

**Document**
```
id, user_id (FK), title, original_filename, storage_path,
status: enum(pending, processing, ready, failed),
page_count (nullable), error_message (nullable),
created_at, updated_at
```

**DocumentChunk**
```
id, document_id (FK), chunk_index, content (text),
token_count, page_number (nullable),
embedding: vector(1536),
created_at
```

**Conversation**
```
id, user_id (FK), document_id (FK, nullable → null = búsqueda en toda la biblioteca),
title (nullable, autogenerable del primer mensaje),
created_at, updated_at
```

**Message**
```
id, conversation_id (FK), role: enum(user, assistant), content (text),
source_chunk_ids (json, nullable — para citar fuentes),
created_at
```

## 4. Flujos principales (MVP)

1. Registro / login (Sanctum + mutations `register`/`login` en Lighthouse)
2. Subir PDF → job en cola (Redis) → extracción → chunking → embeddings → chunks guardados → `status: ready`
3. Progreso de procesamiento en vivo (GraphQL Subscription alimentada por Redis Pub/Sub)
4. Preguntar sobre un documento o sobre toda la biblioteca → similarity search en pgvector → contexto → llamada a LLM → respuesta con fuentes citadas
5. Ver historial de conversaciones

## 5. Estructura de repo

```
docubrain/
├── backend/                 # Laravel 13
│   ├── app/
│   │   ├── GraphQL/          # Resolvers, mutations
│   │   ├── Jobs/              # ProcessDocument, GenerateEmbeddings
│   │   ├── Services/          # EmbeddingProvider, AnswerGenerator (interfaces + impl)
│   │   └── Models/
│   ├── graphql/
│   │   └── schema.graphql
│   ├── tests/
│   │   ├── Feature/
│   │   └── Unit/
│   └── database/migrations/
├── frontend/                 # Next.js (React) + Shadcn/UI — implementado en Fase 7
├── docker/
│   ├── nginx/
│   └── docker-compose.yml
├── .github/workflows/
│   └── ci.yml
└── docs/
    └── decisions/             # ADRs opcionales, uno por decisión no trivial
```

## 6. Sistema de Agentes (Arquitectura y Flujo)

### 6.1 Arquitectura General

Cada mensaje del usuario pasa por este flujo **sin excepciones**:

```
SendMessage mutation
    └── IntentClassifier::classify()      ← clasifica intención + tópico
        └── Agent::handle(context)         ← agente ejecuta con ClassifiedIntent disponible
            └── AgentResponse              ← respuesta tipada
```

### 6.2 Cómo Funciona el `IntentClassifier`

`App\Services\IntentClassifier` es el clasificador centralizado. Hace lo siguiente:

1. **Llama al LLM** con un prompt corto para clasificar la intención del usuario y extraer el tópico mencionado.
2. **Verifica el tópico** contra el documento via pgvector (similarity search) si se detectó un tópico específico.
3. Retorna un `ClassifiedIntent` DTO con: `intent`, `topic`, `topicInDocument`, `confidence`.

### 6.3 Cómo Crear un Nuevo Agente

Implementa la interface `App\Services\Contracts\AgentHandler`. Requiere **cuatro métodos**:

```php
class MyNewAgent implements AgentHandler
{
    public function key(): string { return 'my_agent'; }
    public function name(): string { return 'My Agent Name'; }
    public function description(): string { return 'Qué hace este agente en una frase.'; }

    public function supportedIntents(): array
    {
        return ['my_primary_intent', 'my_secondary_intent'];
    }

    public function handle(AgentContext $context): AgentResponse
    {
        // 1. Usuario está conversando
        if ($context->intent?->isChat()) {
            return new AgentResponse(answer: 'Respuesta conversacional', responseType: 'text');
        }

        // 2. Tópico NO está en el documento
        if ($context->intent?->isTopicMissing()) {
            return new AgentResponse(answer: "No encontré información sobre este tema.", responseType: 'text');
        }

        // 3. Lógica real
    }
}
```

En `App\Providers\AgentServiceProvider::boot()`:
`$registry->register($this->app->make(MyNewAgent::class));`

### 6.4 DTOs

- **AgentContext**: Recibe `$question`, `$conversation`, `$document`, `$userId`, `$intent`.
- **ClassifiedIntent**: `$intent`, `$topic`, `$topicInDocument`, `$confidence`, `isChat()`, `isTopicMissing()`.
- **AgentResponse**: `$answer`, `$sourceChunks`, `$responseType` ('text', 'quiz', 'steps'), `$metadata`.

## 7. Roadmap por fases — entregable real sobre DocuBrain en cada una

Sin semanas fijas. Cada fase termina cuando el entregable funciona y está testeado, no cuando se acaba un plazo.

**Fase 0 — Bootstrap** *(esto es lo primero que le pides a Claude Code)*
Laravel 13 + Sanctum + Lighthouse con schema mínimo (`me` query, `register`/`login` mutations). Docker Compose con Postgres+pgvector y Redis levantados y conectados. Sin lógica de negocio todavía — solo la base corriendo.

**Fase 1 — Testing**
Mutation `uploadDocument` (solo guarda archivo + metadata, sin procesar), query `documents`. Tests con PHPUnit + helpers de testing de Lighthouse (`$this->graphQL(...)`), seeders con Faker. Vitest: se practica de forma aislada/conceptual acá — su aplicación real llega en Fase 7 cuando exista una SPA que testear.

**Fase 2 — GitHub Actions**
Pipeline de CI que corre PHPUnit en cada push/PR sobre este mismo repo, con cache de dependencias de Composer.

**Fase 3 — SQL avanzado**
Con datos de prueba ya generados por Faker: `EXPLAIN ANALYZE` sobre queries reales de documents/chunks, índices, y resolución de N+1 real en los resolvers de Lighthouse (uso de batching/dataloaders).

**Fase 4 — Redis**
El job de procesamiento (extracción → chunking → embeddings) se implementa como Queue real. Cache de resultados de búsqueda. Pub/Sub para notificar progreso — deja el broadcast listo para conectarlo a una Subscription en la Fase 6.

**Fase 5 — Nginx**
Todo el stack detrás de Nginx como reverse proxy dentro de Docker Compose. SSL/Certbot solo si en ese punto ya decidiste desplegar en un dominio real (ver decisión pendiente en sección 2).

**Fase 6 — GraphQL avanzado**
Como la base de Lighthouse ya existe desde la Fase 0, acá se agrega lo avanzado: Subscription de progreso de procesamiento (conecta con el Pub/Sub de Fase 4), mutations de conversación/mensaje.

**Fase 7 — Next.js (React) + Shadcn/UI**
SPA completa consumiendo el API GraphQL (y REST para descargas/subidas) maduro y testeado: login seguro con SSR Middleware, upload con progreso en vivo, chat de preguntas (Split-Screen), historial. Interfaces animadas con Framer Motion.

**Fase 8 — pgvector / RAG**
La única fase que introduce lógica de negocio genuinamente nueva — el resto de la infraestructura ya funciona. Foco 100% en: estrategia de chunking, calidad de embeddings, similarity search (cosine vs L2, índice ivfflat/hnsw), prompt para la respuesta con citas de fuente.

**Fase 9 — Integración y despliegue** *(nueva, fuera de las 8 originales)*
Pulido de UX, manejo de errores, y despliegue real si se decidió hacerlo. No se solapa con aprender tecnología nueva — es la única fase pensada para consolidar, no para aprender.

## 8. Decisiones abiertas

- ¿Single-user simplificado o algo de multi-tenancy desde ya?
- ¿Despliegue en dominio real (VPS, DNS, Certbot) o todo se queda en Docker local?
- ¿OpenRouter API como definí arriba, o preferís otro proveedor de embeddings/LLM?
