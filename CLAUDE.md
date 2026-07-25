# DocuBrain — Guía para Agentes de IA

> Este archivo es la fuente de verdad para cualquier agente de IA que trabaje en este proyecto.
> Léelo completamente antes de escribir o modificar cualquier código.

---

## 1. Visión del Proyecto

DocuBrain es una API RAG (Retrieval-Augmented Generation) construida con **Laravel 13 + Lighthouse GraphQL** en el backend y **Next.js + Shadcn/UI** en el frontend. Los usuarios suben PDFs, el sistema los procesa y embebe, y luego pueden conversar con distintos **agentes de IA** especializados sobre el contenido de sus documentos.

Stack completo:
- **Backend**: Laravel 13, Lighthouse (GraphQL), PostgreSQL + pgvector, Redis, OpenRouter API
- **Frontend**: Next.js, TanStack Query, Shadcn/UI, TypeScript
- **Infra**: Docker Compose

---

## 2. Sistema de Agentes — La Parte Más Importante

### 2.1 Arquitectura General

Cada mensaje del usuario pasa por este flujo **sin excepciones**:

```
SendMessage mutation
    └── IntentClassifier::classify()      ← clasifica intención + tópico
        └── Agent::handle(context)         ← agente ejecuta con ClassifiedIntent disponible
            └── AgentResponse              ← respuesta tipada
```

### 2.2 Cómo Funciona el `IntentClassifier`

`App\Services\IntentClassifier` es el clasificador centralizado. Hace lo siguiente:

1. **Llama al LLM** con un prompt corto para clasificar la intención del usuario y extraer el tópico mencionado.
2. **Verifica el tópico** contra el documento via pgvector (similarity search) si se detectó un tópico específico.
3. Retorna un `ClassifiedIntent` DTO con: `intent`, `topic`, `topicInDocument`, `confidence`.

**No modifiques este servicio sin entender el impacto en todos los agentes.**

### 2.3 Cómo Crear un Nuevo Agente

Implementa la interface `App\Services\Contracts\AgentHandler`. Requiere **cuatro métodos**:

```php
class MyNewAgent implements AgentHandler
{
    public function key(): string
    {
        return 'my_agent'; // único, snake_case, sin espacios
    }

    public function name(): string
    {
        return 'My Agent Name'; // nombre legible para el usuario
    }

    public function description(): string
    {
        return 'Qué hace este agente en una frase.';
    }

    /**
     * OBLIGATORIO: declara qué intenciones maneja este agente.
     * 'chat' está implícitamente soportado en todos los agentes — no lo incluyas aquí.
     *
     * El IntentClassifier usará esta lista para clasificar el mensaje del usuario.
     * Usa verbos en inglés: generate_*, solve_*, explain_*, summarize_*, etc.
     */
    public function supportedIntents(): array
    {
        return ['my_primary_intent', 'my_secondary_intent'];
    }

    public function handle(AgentContext $context): AgentResponse
    {
        // SIEMPRE verifica el intent al inicio del handle():

        // 1. Usuario está conversando — no es una petición accionable
        if ($context->intent?->isChat()) {
            return new AgentResponse(
                answer: 'Respuesta conversacional amigable aquí.',
                responseType: 'text',
            );
        }

        // 2. Usuario pidió algo sobre un tópico que NO está en el documento
        if ($context->intent?->isTopicMissing()) {
            $topic = $context->intent->topic;
            return new AgentResponse(
                answer: "No encontré información sobre \"{$topic}\" en este documento. "
                      . "¿Te gustaría que te ayude con los temas que sí contiene? 😊",
                responseType: 'text',
            );
        }

        // 3. Tu lógica real del agente aquí...
    }
}
```

### 2.4 Registrar el Nuevo Agente

En `App\Providers\AgentServiceProvider::boot()`:

```php
$registry->register($this->app->make(MyNewAgent::class));
```

El `IntentClassifier` funciona automáticamente con el nuevo agente. Sin cambios adicionales.

### 2.5 Los Tres Tipos de `responseType`

| Valor | Cuándo usarlo | Cómo lo renderiza el frontend |
|-------|--------------|-------------------------------|
| `'text'` | Respuesta conversacional, rechazo amigable, explicación | Burbuja de chat normal del asistente |
| `'quiz'` | Quiz generado con preguntas | Accordion en la lista de quizzes + confirmación en chat |
| `'steps'` | Solución matemática paso a paso | Burbuja de chat con soporte LaTeX |

---

## 3. DTOs — No Inventes Nuevos Sin Necesidad

### `AgentContext` (`App\DTOs\AgentContext`)
Lo que recibe cada agente en `handle()`:

```php
$context->question      // string — mensaje original del usuario
$context->conversation  // Conversation model
$context->document      // ?Document model — puede ser null
$context->userId        // int
$context->intent        // ?ClassifiedIntent — SIEMPRE revisa esto primero
```

### `ClassifiedIntent` (`App\DTOs\ClassifiedIntent`)
```php
$intent->intent          // string — ej: "generate_quiz", "chat", "ask_question"
$intent->topic           // ?string — ej: "Elon Musk", null si es genérico
$intent->topicInDocument // bool — false si el tópico no está en el PDF
$intent->confidence      // float 0.0-1.0
$intent->isChat()        // helper: true si intent === 'chat'
$intent->isTopicMissing() // helper: true si hay tópico Y no está en el doc
```

### `AgentResponse` (`App\DTOs\AgentResponse`)
```php
new AgentResponse(
    answer:       'texto de la respuesta',
    sourceChunks: [],            // [['id' => ..., 'page_number' => ...]]
    responseType: 'text',        // 'text' | 'quiz' | 'steps'
    metadata:     [],            // datos extra (ej: ['quiz_id' => 1])
)
```

---

## 4. Convenciones del Proyecto

### Backend (Laravel)
- **Agentes**: `app/Agents/` — un archivo por agente, nombre descriptivo + `Agent.php`
- **DTOs**: `app/DTOs/` — clases `readonly`, sin lógica de negocio
- **Servicios**: `app/Services/` — lógica reutilizable, inyectable via constructor
- **Contratos**: `app/Services/Contracts/` — interfaces PHP
- **GraphQL Mutations**: `app/GraphQL/Mutations/` — un archivo por mutation
- **GraphQL Queries**: `app/GraphQL/Queries/` — un archivo por query
- **Schema**: `graphql/schema.graphql` — fuente de verdad del API GraphQL

### Frontend (Next.js)
- **Componentes**: `frontend/src/components/` — organizados por feature
- **Librería GraphQL**: usa `graphql-request` via `@/lib/graphql`
- **Estado del servidor**: TanStack Query (`useQuery`, `useMutation`)
- **Estilos**: Shadcn/UI + Tailwind CSS
- **Tipos**: TypeScript estricto, sin `any` salvo en respuestas de API externas

### General
- Idioma del código: **inglés** (variables, funciones, comentarios de código)
- Idioma de respuestas al usuario: **español** (los strings que ve el usuario final)
- Sin comentarios obvios — solo documenta el "por qué", no el "qué"

---

## 5. Reglas Estrictas — NO Hacer

### ❌ Backend
- **No llames al LLM directamente desde un agente** para clasificar intención — usa el `IntentClassifier`.
- **No ignores `$context->intent`** — siempre maneja `isChat()` e `isTopicMissing()` al inicio de `handle()`.
- **No hagas fallback silencioso** a contenido no relacionado. Si el tópico no está en el documento, di que no está.
- **No crees agentes sin registrarlos** en `AgentServiceProvider`.
- **No definas `supportedIntents()` retornando `['chat']`** — eso es el fallback implícito, no una intención real.
- **No hagas llamadas HTTP síncronas de larga duración** fuera de Jobs de Queue (PDFs, embeddings masivos).
- **No modifiques migraciones existentes** — crea nuevas migraciones.

### ❌ Frontend
- **No uses `isSystem: true` para respuestas de agentes** — las respuestas van como burbujas normales de chat.
- **No hardcodees texto en inglés** que el usuario final vea — el UI habla español.
- **No hagas fetch directo** — usa TanStack Query + el `graphqlClient` de `@/lib/graphql`.
- **No hagas múltiples `setLocalMessages`** en el mismo handler — agrupa en una sola actualización.

---

## 6. Flujo de Datos de un Mensaje (Referencia Rápida)

```
Usuario escribe mensaje
    → Frontend: sendMutation (SEND_MESSAGE_MUTATION GraphQL)
    → Backend: SendMessage::__invoke()
        → guarda mensaje del usuario en DB
        → resuelve el agente según conversation.agent_type
        → IntentClassifier::classify(message, agent.supportedIntents(), document, userId)
            → LLM clasifica intent + extrae topic
            → si topic → similarity search en pgvector para verificar si está en el doc
            → retorna ClassifiedIntent
        → agente.handle(AgentContext con ClassifiedIntent)
            → revisa isChat() / isTopicMissing()
            → ejecuta lógica específica del agente
            → retorna AgentResponse
        → guarda respuesta del asistente en DB con response_type y metadata
        → retorna Message
    → Frontend: onSuccess
        → si response_type === 'quiz' → refresca lista de quizzes + muestra burbuja de confirmación
        → si response_type === 'text' → muestra burbuja normal del asistente
        → si response_type === 'steps' → muestra burbuja normal del asistente
```

---

## 7. Variables de Entorno Importantes

```
OPENROUTER_API_KEY=          # API key para LLM y embeddings
OPENROUTER_BASE_URL=         # URL base de OpenRouter
OPENROUTER_LLM_MODEL=        # Modelo LLM (ej: anthropic/claude-haiku-4-5)
OPENROUTER_EMBEDDING_MODEL=  # Modelo de embeddings
SIMILARITY_THRESHOLD=        # Default 0.75 para Q&A
```

---

## 8. Estructura de Directorios Clave

```
backend/app/
├── Agents/
│   ├── AgentRegistry.php          # Registro de agentes
│   ├── DocumentQAAgent.php        # Agente de Q&A sobre documentos
│   ├── MathSolverAgent.php        # Agente de matemáticas
│   └── QuizGeneratorAgent.php     # Agente de generación de quizzes
├── DTOs/
│   ├── AgentContext.php           # Contexto que reciben los agentes
│   ├── AgentResponse.php          # Respuesta tipada de los agentes
│   └── ClassifiedIntent.php       # Resultado de la clasificación de intención
├── Services/
│   ├── Contracts/
│   │   └── AgentHandler.php       # Interface que todo agente debe implementar
│   ├── IntentClassifier.php       # Clasificador centralizado de intenciones
│   └── PgvectorSimilaritySearch.php
├── GraphQL/
│   ├── Mutations/
│   │   └── SendMessage.php        # Orquestador principal del flujo
│   └── Queries/
└── Providers/
    └── AgentServiceProvider.php   # Registro de agentes e IntentClassifier
```

---

## 9. Preguntas Frecuentes para Agentes de IA

**P: ¿Debo añadir el intent manualmente al `AgentContext`?**
R: No. `SendMessage` lo hace automáticamente antes de llamar a `handle()`.

**P: ¿Qué pasa si `$context->intent` es `null`?**
R: En teoría no debería ser null si el agente está bien registrado. Usa el operador nullsafe `?->` por seguridad.

**P: ¿Puedo hacer que un agente llame a otro agente?**
R: No directamente. Si necesitas lógica compartida, extráela a un Servicio en `app/Services/`.

**P: ¿Cómo agrego un nuevo `responseType`?**
R: Primero define cómo lo renderizará el frontend en `QuizPanel.tsx` o el componente de chat correspondiente. Luego úsalo en tu agente. Documenta el nuevo tipo en este archivo (sección 2.5).

**P: ¿El `IntentClassifier` hace una llamada al LLM por cada mensaje?**
R: Sí, una llamada rápida y barata (prompt corto, ~50 tokens de respuesta). El costo es mínimo comparado con la llamada principal del agente.
