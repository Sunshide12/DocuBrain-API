<?php

declare(strict_types=1);

namespace App\Services;

use App\Agents\ToolRegistry;
use App\Models\OrchestratorPrompt;
use App\Models\Tool;
use App\Services\Contracts\OpenRouterClient;
use Illuminate\Support\Collection;

/**
 * Decides, in a single LLM call, which tool should handle the user's message and
 * what their intent/topic is. Replaces the old two-step "classify intent, then try
 * to delegate to another agent" flow with one router decision per turn.
 */
class OrchestratorRouter
{
    private const DEFAULT_PROMPT = <<<'PROMPT'
Router for a document assistant. Given the message and recent history, pick ONE tool and classify intent/topic.

## Tools
{tools}

## History
{history}

## Message
{message}

## Rules
- Pick exactly one tool key from the list above.
- Greeting, small talk, or "what can you do" -> "greetings".
- Ambiguous, no tool clearly matches -> "clarification" (never force a wrong guess).
- topic: the specific subject mentioned, or null when generic.
- topic_type: "semantic" (conceptual subject), "structural" (numbered/positional ref,
  e.g. "problema 2.1" or "el primer ejercicio"), or null.
- intent: short English verb phrase (e.g. "ask_question", "generate_quiz", "solve_math", "chat").

## Output
ONLY valid JSON, no markdown fences, no other text:
{"tool": "...", "intent": "...", "topic": "...", "topic_type": "..."}
PROMPT;

    public function __construct(
        private readonly OpenRouterClient $openRouter,
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{tool: string, intent: string, topic: ?string, topic_type: ?string}
     */
    public function route(string $message, array $history): array
    {
        $enabledTools = Tool::query()->where('is_enabled', true)->orderBy('sort_order')->get();

        $fallback = [
            'tool' => 'clarification',
            'intent' => 'chat',
            'topic' => null,
            'topic_type' => null,
        ];

        if ($enabledTools->isEmpty()) {
            return $fallback;
        }

        $prompt = $this->buildPrompt($message, $history, $enabledTools);

        try {
            $raw = $this->openRouter->chat([
                ['role' => 'user', 'content' => $prompt],
            ], ['max_tokens' => 150, 'temperature' => 0.0, 'timeout' => 15]);
        } catch (\Throwable) {
            return $fallback;
        }

        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw ?? '');

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return $fallback;
        }

        $toolKey = $decoded['tool'] ?? null;
        $validKeys = $enabledTools->pluck('key')->all();

        if (! is_string($toolKey) || ! in_array($toolKey, $validKeys, true) || ! $this->registry->has($toolKey)) {
            return $fallback;
        }

        $topic = $decoded['topic'] ?? null;
        if ($topic !== null && (! is_string($topic) || trim($topic) === '' || strtolower(trim($topic)) === 'null')) {
            $topic = null;
        }

        $topicType = $decoded['topic_type'] ?? null;
        if (! in_array($topicType, ['semantic', 'structural'], true)) {
            $topicType = null;
        }

        return [
            'tool' => $toolKey,
            'intent' => is_string($decoded['intent'] ?? null) ? $decoded['intent'] : 'chat',
            'topic' => $topic,
            'topic_type' => $topicType,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  Collection<int, Tool>  $tools
     */
    private function buildPrompt(string $message, array $history, $tools): string
    {
        $template = OrchestratorPrompt::query()
            ->where('key', 'router')
            ->where('is_active', true)
            ->value('content') ?? self::DEFAULT_PROMPT;

        $toolsList = $tools->map(fn (Tool $tool) => "- \"{$tool->key}\": {$tool->description}")->implode("\n");

        $historyText = empty($history)
            ? '(no previous messages)'
            : collect($history)->map(fn (array $m) => "{$m['role']}: {$m['content']}")->implode("\n");

        return strtr($template, [
            '{tools}' => $toolsList,
            '{history}' => $historyText,
            '{message}' => $message,
        ]);
    }
}
