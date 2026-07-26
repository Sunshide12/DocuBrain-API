---
name: test-agentes-rag
description: Structured strategy for testing, mocking, and evaluating the behavior and response quality of AI agents in Laravel.
---

# RAG Agent Testing & Evaluation Guide

Testing AI agents (LLMs) is very different from testing traditional code because their responses are **non-deterministic** (they can vary slightly). To stay sane and avoid burning thousands of tokens per test run, strictly apply this 3-tier methodology.

**Critical rule: automated tests must never call OpenRouter/OpenAI.** If they do, the suite becomes slow, expensive, and flaky.

## 1. Automated Testing (PHPUnit) — Zero Tokens Spent
Tests run via `php artisan test` must never connect to the real LLM provider.

- **Mock the LLM**: intercept the class that makes the real call and have it return a static, predefined response.
```php
// Example in a Laravel Feature Test
$this->mock(\App\Services\Contracts\AnswerGeneratorInterface::class, function ($mock) {
    $mock->shouldReceive('generate')
         ->once()
         ->andReturn('{"pregunta": "Mock de pregunta", "respuesta": "Mock de respuesta"}');
});
```
- **What to verify in the test**:
  1. The agent correctly processes the mocked response (e.g. extracting the JSON).
  2. It returns the correct `responseType` (e.g. `'quiz'`).
  3. It returns the data structure the Frontend expects.

## 2. The 3 Base Cases for Every Tool
Every tool test must include these 3 mandatory scenarios (call `$tool->execute($context)` directly with a hand-built `ToolContext` — no need to go through `OrchestratorAgent`/`OrchestratorRouter` unless the test is specifically about routing, see `OrchestratorAgentTest` for that):
1. `test_handles_missing_topic`: pass a `ToolContext` whose `intent->isTopicMissing()` is `true`. Verify it returns the predefined friendly error message.
2. `test_happy_path_executes_logic`: the real scenario (see section 1) using the mocked LLM.
3. `test_handles_llm_failure_gracefully` (when relevant to the tool): mock `OpenRouterClient::chat` to throw — this is normally NOT caught inside the tool, it propagates up to `OrchestratorAgent`, which is what turns it into a friendly message. Test that boundary in `OrchestratorAgentTest`, not inside every tool test.

Router-selection tests (which tool the LLM call picks) belong in `OrchestratorAgentTest`/`OrchestratorRouter` tests, not in individual tool tests — keep tool tests focused on "given this ToolContext, does execute() behave correctly."

## 3. Quality Evaluation (Live Prompt Testing)
To test the tool's actual **intelligence and quality** (is the prompt well-designed, does it respect the output format, does the embedding retrieve good data), don't use PHPUnit — use **Laravel Tinker** to bypass the frontend and iterate fast.

1. Open the console: `php artisan tinker`
2. Run a real scenario to evaluate the LLM's response:
```php
// 1. Get a real document and conversation from the DB
$doc = \App\Models\Document::first();
$conversation = \App\Models\Conversation::where('document_id', $doc->id)->first();

// 2. Build a fake context
$context = new \App\DTOs\ToolContext(
    question: "Genera un quiz de 3 preguntas nivel difícil",
    conversation: $conversation,
    document: $doc,
    userId: $doc->user_id,
    intent: new \App\DTOs\ClassifiedIntent('generate_quiz', 'general', true, 0.9),
);

// 3. Run the real tool
$tool = app(\App\Agents\Tools\QuizGeneratorTool::class);
$response = $tool->execute($context);

// 4. Visually inspect the quality
dump($response->answer);
```

To evaluate the **router's** tool-selection quality specifically (not a single tool's output), call `app(\App\Services\OrchestratorRouter::class)->route($message, $history)` instead and inspect the returned `['tool' => ..., 'intent' => ..., 'topic' => ..., 'topic_type' => ...]` array. The router prompt template lives in the `orchestrator_prompts` DB table (`key = 'router'`), editable without a deploy.

## 4. Feedback Loop (Continuous Improvement)
If step 3 reveals the agent responding poorly (e.g. returns text when JSON was expected, or is imprecise):
- **Do NOT change the PHP logic (ifs/elses)**.
- **DO change the System Prompt** inside the agent's logic (or its `AnswerGenerator`). With LLM agents, "programming" means shaping natural-language instructions. Re-run the Tinker scenario until the response is 100% robust.

---
Checkpoint: did you mock the LLM provider, and does the test cover all 3 base cases?
