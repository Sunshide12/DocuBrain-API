<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ORIGINAL = <<<'PROMPT'
You are the router for a document assistant. Read the user's message and the recent
conversation history, then decide which single tool should handle it and what the
user's intent is.

## Available tools
{tools}

## Recent conversation history
{history}

## Current user message
{message}

## Rules
- Pick exactly ONE tool key from the list above.
- If the message is a greeting, small talk, or asks what you can do, choose "greetings".
- If the message is genuinely ambiguous and no tool clearly matches, choose "clarification".
- Do NOT force a match — prefer "clarification" over guessing wrong.
- "topic" is the specific subject the user mentions, or null when generic.
- "topic_type" is "semantic" (a conceptual subject), "structural" (a reference to a
  numbered/positional item like "problema 2.1" or "el primer ejercicio"), or null.
- "intent" is a short English verb phrase describing the action (e.g. "ask_question",
  "generate_quiz", "solve_math", "chat").

## Output
Reply with ONLY valid JSON, no markdown fences, no other text:
{"tool": "...", "intent": "...", "topic": "...", "topic_type": "..."}
PROMPT;

    private const COMPRESSED = <<<'PROMPT'
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

    /**
     * Shrinks the seeded router prompt (~30% fewer characters, same rules) to cut
     * token cost on every single conversation turn. Only touches rows that still
     * hold the original seeded text, so any manual edit made via the admin panel
     * (orchestrator_prompts is designed to be editable without a deploy) is left
     * untouched.
     */
    public function up(): void
    {
        DB::table('orchestrator_prompts')
            ->where('key', 'router')
            ->where('content', self::ORIGINAL)
            ->update(['content' => self::COMPRESSED, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('orchestrator_prompts')
            ->where('key', 'router')
            ->where('content', self::COMPRESSED)
            ->update(['content' => self::ORIGINAL, 'updated_at' => now()]);
    }
};
