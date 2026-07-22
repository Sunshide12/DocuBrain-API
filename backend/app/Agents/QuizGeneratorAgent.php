<?php

namespace App\Agents;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;
use App\Services\Contracts\AgentHandler;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Support\Facades\Http;

class QuizGeneratorAgent implements AgentHandler
{
    public function __construct(
        private readonly EmbeddingProvider        $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
    ) {}

    public function key(): string
    {
        return 'quiz_generator';
    }

    public function name(): string
    {
        return 'Study & Quiz Generator';
    }

    public function description(): string
    {
        return 'Generates study questions, flashcards, and quizzes from your documents to help you prepare for exams.';
    }

    public function handle(AgentContext $context): AgentResponse
    {
        $threshold      = (float) config('services.openrouter.similarity_threshold', 0.75);
        $questionVector = $this->embeddingProvider->embed($context->question);

        $chunks = $this->similaritySearch->search(
            queryVector: $questionVector,
            userId:      $context->userId,
            documentId:  $context->conversation->document_id,
            threshold:   $threshold,
        );

        if ($chunks->isEmpty()) {
            return new AgentResponse(
                answer: 'I could not find relevant content in your document to generate study material. Try rephrasing your request or asking about a specific topic in the document.',
                responseType: 'text',
            );
        }

        $contextChunks = implode("\n\n---\n\n", $chunks->map(function ($chunk) {
            return "Page " . ($chunk->page_number ?? 'N/A') . ":\n" . $chunk->content;
        })->all());

        $question = $context->question;

        $prompt = <<<EOT
You are a study assistant. The user wants help studying the document content below.
Based on their specific request, generate targeted study material.

## Capabilities
- Multiple-choice questions (4 options each)
- Flashcards (term/concept → definition/explanation)
- True/false questions
- Open-ended review questions

## Rules
- Generate study material ONLY from the provided document content.
- Match the user's request: if they ask for flashcards, generate flashcards;
  if they ask for a quiz, generate multiple-choice questions.
- If the user says "quiz me", generate 3–5 multiple-choice questions.
- Always include the correct answer and a brief explanation.
- Output ONLY a valid JSON array, no surrounding text, no markdown fences.

## Relevant Document Content
{$contextChunks}

## User's Request
{$question}

## Output Format
[
  {
    "type": "multiple_choice",
    "question": "What is...?",
    "options": ["A) ...", "B) ...", "C) ...", "D) ..."],
    "correct_answer": "A",
    "explanation": "According to the document..."
  }
]
EOT;

        $baseUrl = config('services.openrouter.base_url');
        $apiKey  = config('services.openrouter.api_key');
        $model   = config('services.openrouter.llm_model');

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        $raw = trim($response->json('choices.0.message.content') ?? '');

        // Strip markdown fences if the LLM added them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);

        $questions = json_decode($raw, true);

        if (!is_array($questions)) {
            // LLM returned non-JSON — return raw text as a fallback
            return new AgentResponse(
                answer:       $raw,
                responseType: 'text',
            );
        }

        $answer = $this->formatQuestionsAsText($questions);

        return new AgentResponse(
            answer:       $answer,
            responseType: 'quiz',
            metadata:     ['questions' => $questions],
        );
    }

    private function formatQuestionsAsText(array $questions): string
    {
        $lines = [];
        foreach ($questions as $i => $q) {
            $num  = $i + 1;
            $type = $q['type'] ?? 'question';
            $lines[] = "**Question {$num}** ({$type})";
            $lines[] = $q['question'] ?? '';

            if (!empty($q['options']) && is_array($q['options'])) {
                foreach ($q['options'] as $option) {
                    $lines[] = "  {$option}";
                }
            }

            if (!empty($q['correct_answer'])) {
                $lines[] = "**Answer:** " . $q['correct_answer'];
            }
            if (!empty($q['explanation'])) {
                $lines[] = "*" . $q['explanation'] . "*";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
