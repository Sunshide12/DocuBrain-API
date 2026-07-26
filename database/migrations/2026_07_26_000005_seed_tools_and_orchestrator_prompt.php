<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Static (key, name, description) mirrors the AgentTool classes at the time
        // this migration was written. The `tools` table is the editable source of
        // truth after this point — see OrchestratorRouter and the admin panel.
        $tools = [
            ['key' => 'document_qa', 'name' => 'Document Q&A', 'description' => 'Answer questions using the content of the selected document.', 'sort_order' => 0],
            ['key' => 'math_solver', 'name' => 'Math Solver', 'description' => 'Resolve and explain mathematical problems, equations, formulas, integrals, derivatives, algebra, and calculations step by step.', 'sort_order' => 1],
            ['key' => 'quiz_generator', 'name' => 'Study & Quiz Generator', 'description' => 'Generates study questions, flashcards, and quizzes from your documents to help you prepare for exams.', 'sort_order' => 2],
            ['key' => 'greetings', 'name' => 'Greetings', 'description' => 'Responds to greetings, small talk, and questions about what the assistant can do.', 'sort_order' => 3],
            ['key' => 'clarification', 'name' => 'Clarification', 'description' => 'Asks the user to clarify their request when it is too ambiguous to route to a specific tool.', 'sort_order' => 4],
        ];

        foreach ($tools as $tool) {
            DB::table('tools')->insertOrIgnore([
                ...$tool,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('orchestrator_prompts')->insertOrIgnore([
            'key' => 'router',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'content' => <<<'PROMPT'
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
PROMPT,
        ]);
    }

    public function down(): void
    {
        DB::table('tools')->whereIn('key', ['document_qa', 'math_solver', 'quiz_generator', 'greetings', 'clarification'])->delete();
        DB::table('orchestrator_prompts')->where('key', 'router')->delete();
    }
};
