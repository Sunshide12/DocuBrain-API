---
name: crear-agente-rag
description: Step-by-step instructions to create, implement, register, and test a new RAG agent in DocuBrain.
---

# Procedure: Create a RAG Agent

Follow these steps strictly whenever the user asks to create a new RAG (AI) agent in DocuBrain-API.

## 1. Create the Agent Class
Create the file at `app/Agents/[Name]Agent.php`.
The class **must** implement `App\Services\Contracts\AgentHandler`.

**Required class structure:**
```php
<?php

namespace App\Agents;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;
use App\Services\Contracts\AgentHandler;

readonly class [Name]Agent implements AgentHandler
{
    public function key(): string
    {
        return 'snake_case_name'; // Must be unique, no spaces
    }

    public function name(): string
    {
        return 'Human-Readable Agent Name';
    }

    public function description(): string
    {
        return 'Short, concise description of what it does.';
    }

    public function supportedIntents(): array
    {
        // Do NOT include 'chat' here, it's supported by default.
        // Use English verbs, e.g.: 'generate_quiz', 'summarize_doc'
        return ['primary_intent'];
    }

    public function handle(AgentContext $context): AgentResponse
    {
        // 1. STRICT RULE: base conversational handling
        if ($context->intent?->isChat()) {
            return new AgentResponse(
                answer: 'Respuesta conversacional amigable.',
                responseType: 'text' // 'text', 'quiz', or 'steps'
            );
        }

        // 2. STRICT RULE: topic not found in the document
        if ($context->intent?->isTopicMissing()) {
            return new AgentResponse(
                answer: "No encontré información sobre \"{$context->intent->topic}\" en este documento.",
                responseType: 'text'
            );
        }

        // 3. Main agent logic
        // Insert your logic here, calling the relevant services (LLM, Embeddings, etc.)

        return new AgentResponse(
            answer: 'Respuesta generada por el agente.',
            responseType: 'text',
            metadata: [] // Additional structured data (e.g. ['quiz_id' => 1])
        );
    }
}
```

## 2. Register the Agent
Open `app/Providers/AgentServiceProvider.php`.
In the `boot()` method, add the line to register your new agent:

```php
$registry->register($this->app->make(\App\Agents\[Name]Agent::class));
```
*Do not touch `IntentClassifier` — it updates automatically once it detects the new agent.*

## 3. Create the Test
Create the test at `tests/Feature/Agents/[Name]AgentTest.php`.
The test must cover:
1. Generic behavior (when the intent is `chat`).
2. Behavior when the topic is missing from the document (`isTopicMissing`).
3. The happy path, where the agent runs its real logic.

Verify with: `php artisan test --filter=[Name]AgentTest`

---
Checkpoint: is the agent registered in `AgentServiceProvider`, and does the test cover all 3 required cases?
