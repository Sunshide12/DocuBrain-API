<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\QuizGenerationCompleted;
use App\Models\Document;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateAutoQuizJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(
        public readonly Document $document,
    ) {}

    public function handle(): void
    {
        $quiz = Quiz::create([
            'document_id' => $this->document->id,
            'user_id'     => $this->document->user_id,
            'title'       => 'Auto-generated Quiz: ' . ($this->document->title ?? $this->document->original_name),
            'status'      => 'generating',
        ]);

        try {
            $chunks = $this->document->chunks()->orderBy('page_number')->orderBy('chunk_index')->get();

            if ($chunks->isEmpty()) {
                $quiz->update(['status' => 'failed', 'error_message' => 'Document has no text chunks.']);
                $this->broadcastCompleted($quiz, 'failed');
                return;
            }

            $sections  = $this->groupChunksIntoSections($chunks->all(), 1500);
            $sortOrder = 0;
            $rows      = [];

            foreach (array_slice($sections, 0, 5) as $section) {
                $questions = $this->generateQuestionsForSection($section);
                foreach ($questions as $q) {
                    $rows[] = [
                        'quiz_id'        => $quiz->id,
                        'question'       => $q['question']       ?? '',
                        'type'           => $q['type']           ?? 'flashcard',
                        'options'        => isset($q['options']) ? json_encode($q['options']) : null,
                        'correct_answer' => $q['correct_answer'] ?? '',
                        'explanation'    => $q['explanation']    ?? null,
                        'page_number'    => $section['start_page'] ?? null,
                        'sort_order'     => $sortOrder++,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
            }

            if (!empty($rows)) {
                QuizQuestion::insert($rows);
            }

            $quiz->update(['status' => 'ready']);
            $this->broadcastCompleted($quiz, 'ready');

            Log::info('GenerateAutoQuizJob completed', [
                'document_id' => $this->document->id,
                'quiz_id'     => $quiz->id,
                'questions'   => count($rows),
            ]);
        } catch (\Throwable $e) {
            $quiz->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            $this->broadcastCompleted($quiz, 'failed');

            Log::error('GenerateAutoQuizJob failed', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function groupChunksIntoSections(array $chunks, int $targetWords): array
    {
        $sections    = [];
        $current     = ['chunks' => [], 'word_count' => 0, 'start_page' => null];

        foreach ($chunks as $chunk) {
            if ($current['start_page'] === null) {
                $current['start_page'] = $chunk->page_number;
            }

            $current['chunks'][]    = $chunk->content;
            $current['word_count'] += (int) $chunk->token_count;

            if ($current['word_count'] >= $targetWords) {
                $current['end_page'] = $chunk->page_number;
                $sections[]          = $current;
                $current             = ['chunks' => [], 'word_count' => 0, 'start_page' => null];
            }
        }

        if (!empty($current['chunks'])) {
            $sections[] = $current;
        }

        return $sections;
    }

    private function generateQuestionsForSection(array $section): array
    {
        $content    = implode("\n\n", $section['chunks']);
        $startPage  = $section['start_page'] ?? 'N/A';
        $endPage    = $section['end_page']   ?? $startPage;
        $n          = 2;

        $prompt = <<<EOT
You are an educational quiz generator. Analyze the following section of a
document and generate study questions to help a student prepare for an exam.

## Rules
- Generate exactly {$n} questions for this section.
- Mix question types: include at least one multiple-choice AND one flashcard-style
  (term/concept → definition/explanation).
- Each question MUST be answerable solely from the provided text.
- For multiple-choice: provide exactly 4 options (A–D), mark which is correct.
- Include a brief explanation for each correct answer.
- Output ONLY a valid JSON array, no surrounding text, no markdown fences.

## Document Section (pages {$startPage}–{$endPage})
{$content}

## Output Format
[
  {
    "type": "multiple_choice",
    "question": "What is...?",
    "options": ["A) ...", "B) ...", "C) ...", "D) ..."],
    "correct_answer": "A",
    "explanation": "Because the text states that..."
  },
  {
    "type": "flashcard",
    "question": "Define: [term]",
    "correct_answer": "[definition from text]",
    "explanation": "Found in this section..."
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

        // Strip markdown fences if the LLM added them anyway
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);

        $parsed = json_decode($raw, true);

        if (!is_array($parsed)) {
            throw new \Exception("LLM returned malformed JSON for quiz section: {$raw}");
        }

        return $parsed;
    }

    private function broadcastCompleted(Quiz $quiz, string $status): void
    {
        try {
            QuizGenerationCompleted::dispatch($quiz, $this->document->user_id, $status);
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast QuizGenerationCompleted: ' . $e->getMessage());
        }
    }
}
