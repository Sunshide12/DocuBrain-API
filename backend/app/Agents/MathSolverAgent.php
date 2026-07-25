<?php

namespace App\Agents;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;
use App\Jobs\ProcessMathExtractionJob;
use App\Services\Contracts\AgentHandler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class MathSolverAgent implements AgentHandler
{
    public function key(): string
    {
        return 'math_solver';
    }

    public function name(): string
    {
        return 'Math Solver';
    }

    public function description(): string
    {
        return 'Solves math problems from your documents step-by-step, including formulas and equations.';
    }

    public function supportedIntents(): array
    {
        return ['solve_problem', 'explain_step'];
    }

    public function handle(AgentContext $context): AgentResponse
    {
        if ($context->intent?->isChat()) {
            return new AgentResponse(
                answer: 'Soy el asistente de matemáticas. Puedo resolver problemas y explicar paso a paso los ejercicios de tu documento. ¿Qué necesitas?',
                responseType: 'text',
            );
        }

        if ($context->intent?->isTopicMissing()) {
            return new AgentResponse(
                answer: "No encontré problemas matemáticos relacionados con \"{$context->intent->topic}\" en este documento.",
                responseType: 'text',
            );
        }

        if (!$context->document) {
            return new AgentResponse(
                answer: 'The Math Solver requires a specific document. Please start a conversation with a document selected.',
                responseType: 'text',
            );
        }

        $document = $context->document->fresh();

        if (!$document->math_extracted_at) {
            try {
                ProcessMathExtractionJob::dispatchSync($document);
                $document->refresh();
            } catch (\Throwable $e) {
                return new AgentResponse(
                    answer: 'Could not extract mathematical content from your document: ' . $e->getMessage(),
                    responseType: 'text',
                );
            }
        }

        $mathPages = $document->mathPages()->orderBy('page_number')->get();

        if ($mathPages->isEmpty()) {
            return new AgentResponse(
                answer: 'No mathematical content was found in this document. The Math Solver works best with documents that contain formulas, equations, or structured math problems.',
                responseType: 'text',
            );
        }

        $relevantPages   = $this->selectRelevantPages($mathPages, $context->question);
        $extractedContent = $relevantPages->map(
            fn($p) => "Page {$p->page_number}:\n{$p->content}"
        )->implode("\n\n---\n\n");

        $question = $context->question;

        $prompt = <<<EOT
You are a mathematics tutor. The student has uploaded a document containing
math problems. Below is the relevant content extracted from their document.

## Your Task
1. Identify the mathematical problem(s) the student is asking about.
2. Solve each problem step-by-step, showing ALL intermediate work.
3. Express all mathematical notation using LaTeX in your response:
   - Inline math: \$...\$
   - Display math: \$\$...\$\$
4. After the solution, briefly explain the concept, theorem, or technique used.
5. If the document content doesn't contain a recognizable math problem
   related to the question, say so clearly.

## Important
- Do NOT skip steps. Students need to see every algebraic manipulation.
- If a problem can be solved multiple ways, show the most standard method first.
- If the student asks "explain step N", re-explain that specific step in more detail.

## Document Content
$extractedContent

## Student's Question
$question
EOT;

        $baseUrl = config('services.openrouter.base_url');
        $apiKey  = config('services.openrouter.api_key');
        $model   = config('services.openrouter.llm_model');

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        $answer = trim($response->json('choices.0.message.content') ?? '');

        return new AgentResponse(
            answer: $answer,
            responseType: 'steps',
        );
    }

    private function selectRelevantPages(Collection $mathPages, string $question): Collection
    {
        if ($mathPages->count() <= 10) {
            return $mathPages;
        }

        $words = array_values(array_filter(
            explode(' ', strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $question))),
            fn($w) => strlen($w) > 3
        ));

        if (empty($words)) {
            return $mathPages->take(5);
        }

        return $mathPages
            ->sortByDesc(fn($page) => array_sum(
                array_map(fn($w) => substr_count(strtolower($page->content), $w), $words)
            ))
            ->take(5)
            ->sortBy('page_number')
            ->values();
    }
}
