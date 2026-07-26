# DocuBrain-API (Laravel + GraphQL RAG Backend)

## Contexto Esencial
- Stack: Laravel 13, Lighthouse GraphQL, PostgreSQL (pgvector), Redis, OpenRouter API.
- Documentación detallada: consulta [DOCUBRAIN_SPEC.md](file:///home/btwsunshide/Documents/Proyects/DocuBrain-API/DOCUBRAIN_SPEC.md) solo si es necesario.

## Comandos Rápidos
- Tests: `php artisan test` (o `php artisan test --filter=NombreTest`)
- GraphQL Schema: `php artisan lighthouse:print-schema`
- Code Style/Format: `vendor/bin/pint`
- En desarrollo aveces las cosas fallan sin razon aparente, puedes ejecutar `php artisan cache:clear`, `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear` y `php optimize clear` para limpiar la cache.

## Reglas Críticas del Workspace
1. **Laravel Auth**: No usar el helper `auth()->id()`. Usar siempre el Facade `Illuminate\Support\Facades\Auth::id()` para compatibilidad con Intelephense/PHPStan.
2. **Sistema de Agentes (Orchestrator + Tools)**:
   - Hay un único agente top-level, `App\Agents\OrchestratorAgent`, sin lógica de negocio propia: por turno hace 1 llamada LLM (`App\Services\OrchestratorRouter`) que decide tool + intent + topic, y delega a esa tool vía `App\Agents\ToolRegistry`.
   - Toda capacidad real vive en `app/Agents/Tools/*.php`, implementando `App\Services\Contracts\AgentTool` (`key()`, `name()`, `description()`, `requiresDocument()`, `execute(ToolContext): ToolResponse`).
   - En `execute()`, verifica primero `$context->intent->isTopicMissing()` cuando aplique (la tool ya sabe que fue elegida por el router; ya no existe `isChat()` — el chit-chat se rutea a `GreetingsTool`).
   - Una tool nueva se registra en 2 lugares: `AgentServiceProvider::boot()` (por class-string, resolución perezosa vía contenedor — nunca instanciar en `boot()`) y la tabla `tools` en DB (fuente de verdad que lee `OrchestratorRouter`). Ver skill `crear-agente-tool`.
   - Excepciones no capturadas dentro de una tool las atrapa `OrchestratorAgent` y las convierte en un `ToolResponse` amigable — nunca dejar que un stack trace llegue al usuario.
3. **No Inspeccionar**: Ignorar carpetas `vendor/`, `storage/logs/`, `bootstrap/cache/`, `database/snapshots/`, `storage/framework/` y `storage/app/`.

## Auto-Mejora de Skills (Feedback Loop)
Si aplicas una skill pero notas que no cubre completamente un requisito explícito del usuario, considera proponer una mejora a ese archivo `SKILL.md` para el futuro. Hazlo solo si el ajuste es realmente útil y aporta valor duradero (no lo hagas por caprichos únicos).
