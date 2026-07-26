---
name: crear-agente-tool
description: Skill for designing and creating an Agent Tool (executed by the OrchestratorAgent router) by interviewing the user.
---

# Agent Tool Creator (Orchestrator Sub-Tool)

**Critical rule: when this skill is invoked, STOP. Do not write any code immediately.** First, you must interview the user with the following 5 critical questions to define the scope of the new Tool.

## Architecture context
DocuBrain has a single top-level agent, `App\Agents\OrchestratorAgent` (`app/Agents/OrchestratorAgent.php`). It carries **no business logic** — it asks `App\Services\OrchestratorRouter` (one LLM call) which tool should handle the turn, then delegates to that tool via `App\Agents\ToolRegistry`. Every capability lives in a Tool implementing:

```php
interface App\Services\Contracts\AgentTool {
    public function key(): string;
    public function name(): string;
    public function description(): string;   // shown to the router's LLM call and used as the DB seed
    public function requiresDocument(): bool;
    public function execute(App\DTOs\ToolContext $context): App\DTOs\ToolResponse;
}
```

`ToolContext` carries `question`, `conversation`, `document`, `userId`, `intent` (`ClassifiedIntent`, built by the router's decision), and `history` (last 3 messages). `ToolResponse` carries `answer`, `agentKey` (must equal `key()` — persisted on the message for the frontend badge), `sourceChunks`, `responseType`, `metadata`.

## 1. The Interview
Ask the user these 5 questions clearly:
1. **Atomic Purpose**: What specific, isolated task does this tool accomplish?
2. **Input Parameters**: What does the tool need from `ToolContext` (document? intent->topic? conversation history?)?
3. **Output Structure**: What `responseType` should `ToolResponse` carry (`text` | `quiz` | `steps`, or a new one) and what goes in `metadata`?
4. **Error/Fallback Handling**: How should this tool fail safely if inputs are invalid or the LLM hallucinates? (Note: uncaught exceptions are already caught by `OrchestratorAgent`, which returns a friendly message — but domain-specific fallbacks, like "topic not in document", belong in the tool itself.)
5. **Dependencies**: Does this tool hit the vector database (`PgvectorSimilaritySearch`/`EmbeddingProvider`), the LLM (`App\Services\Contracts\OpenRouterClient`), or both?

## 2. Generate the Tool Class
Wait for the user to answer all 5 questions. Only after they reply, proceed to generate the code:
- Create the tool file at `app/Agents/Tools/[Name]Tool.php`.
- Implement `App\Services\Contracts\AgentTool`.
- Inject `App\Services\Contracts\OpenRouterClient` (not the `Http` facade directly) for any LLM call — keeps mocking trivial in tests and avoids duplicating request/error-handling boilerplate.
- Keep the logic entirely isolated and atomic, strictly following the user's answers.

## 3. Register the Tool
A tool is invisible to the router until it is wired up in two places:
1. `app/Providers/AgentServiceProvider.php::boot()` — add `$registry->register('your_key', YourTool::class);`. Registration is by **class-string**, not instance — `ToolRegistry` resolves it fresh from the container on every call so per-test container mocks are always honored (never eagerly `$this->app->make()` a tool in `boot()`).
2. The `tools` DB table — add a row (`key`, `name`, `description`, `is_enabled`, `sort_order`), e.g. via a migration mirroring `2026_07_26_000005_seed_tools_and_orchestrator_prompt.php`. `OrchestratorRouter` reads enabled tools from this table (not from `ToolRegistry`) to build the router prompt and validate the LLM's chosen key — a tool registered in code but absent/disabled in this table will never be routed to.

---
Checkpoint: Did you ask the 5 interview questions before generating any PHP code, and did you wire the tool into both `AgentServiceProvider` and the `tools` table?
