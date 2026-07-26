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
2. **Sistema de Agentes (`App\Services\AgentHandler`)**:
   - Todo agente debe implementar `AgentHandler` y declarar `supportedIntents()`.
   - En el método `handle()`, verifica primero `$context->intent?->isChat()` e `$context->intent?->isTopicMissing()`.
3. **No Inspeccionar**: Ignorar carpetas `vendor/`, `storage/logs/`, `bootstrap/cache/`, `database/snapshots/`, `storage/framework/` y `storage/app/`. 
