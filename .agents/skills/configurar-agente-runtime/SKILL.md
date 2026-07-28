---
name: configurar-agente-runtime
description: Where an agent's real configuration lives at runtime (DB prompt overrides, tools table, env) and how to change it so the change actually takes effect. Use before editing any prompt or tuning constant.
---

# Runtime Configuration of Agents

**Critical rule: editing a prompt in a PHP class often changes nothing.** The
runtime reads its configuration from the database first and only falls back to
the code constant. A change that looks deployed can be completely inert.

Before touching any prompt or constant, find out which layer actually wins.

## 1. Prompts: the DB row beats the constant

`OrchestratorRouter::DEFAULT_PROMPT` is a **fallback**. The live prompt is:

```sql
SELECT content FROM orchestrator_prompts WHERE key = 'router' AND is_active = true;
```

If that row exists, the constant is dead code. After editing `DEFAULT_PROMPT`
you MUST sync the row, or the deploy silently keeps the old behaviour:

```php
$ref = new ReflectionClass(\App\Services\OrchestratorRouter::class);
\App\Models\OrchestratorPrompt::where('key', 'router')->where('is_active', true)
    ->update(['content' => $ref->getConstant('DEFAULT_PROMPT')]);
```

Never hand-edit the row with text that does not exist in the class: the two
drift and the next reader debugs a prompt that is not running.

**How to tell you are hitting this**: you change the prompt, redeploy, and the
output is byte-identical — including for inputs you added as literal examples.
That is not the model ignoring you, that is your prompt not being loaded.

## 2. Tools: the DB row decides what the router can pick

A tool is registered in two places and needs both:

1. `AgentServiceProvider::boot()` — by class-string, resolved lazily.
2. The `tools` table — the source of truth `OrchestratorRouter` reads.

The router is given only `where('is_enabled', true)`, and a tool key it does not
recognise is discarded into the fallback. A tool present in the provider but
missing from the table is invisible; the reverse throws at resolve time.

The `description` column is not documentation — it is the text the router uses
to choose. Write it as a selection criterion ("Resolve and explain mathematical
problems step by step"), not as a summary of the class.

## 3. Verify against the running container, never the repo

Config caches, opcache and image rebuilds all lie in different ways. Check the
value the process actually holds:

```bash
docker exec <app> printenv | grep -i SIMILARITY
docker exec -i <app> php artisan tinker --no-ansi <<< 'echo config("services.openrouter.similarity_threshold");'
```

After copying files into a running container, `php artisan optimize:clear` is
not enough for long-lived PHP workers — **restart the container**. A prompt
change that "did not work" is very often an opcache still serving the old class.

## 4. Silent fallbacks hide configuration failures

`OrchestratorRouter::route()` returns `clarification` when the LLM call throws,
times out, or returns unparseable JSON. So a configuration or connectivity
problem shows up as a **behaviour** problem: users get "no estoy seguro de cómo
ayudarte" and nothing is logged as an error.

When a tool is over-selected, first prove it is a real decision and not a
fallback: call the router directly and print its output.

```php
$out = app(\App\Services\OrchestratorRouter::class)->route('tu mensaje', []);
```

---
Checkpoint: did you sync the DB prompt row, restart the container, and confirm
the new value from inside it?
