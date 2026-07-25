<?php

namespace App\Agents;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;
use App\Services\Contracts\AgentHandler;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class QuizGeneratorAgent implements AgentHandler
{
    // Similarity search runs at a lower threshold than Q&A: we only need to detect
    // whether the user mentioned a topic, not find a precise answer.
    private const TOPIC_SIMILARITY_THRESHOLD = 0.35;

    // Below this many relevant chunks, we treat the request as "quiz me on the whole doc"
    // rather than "quiz me on topic X", and fall back to uniform sampling.
    private const MIN_TOPIC_CHUNKS = 3;

    private const DEFAULT_QUESTION_COUNT = 5;
    private const MAX_QUESTION_COUNT     = 20;

    // Rough floor for how many tokens of context a single well-formed question needs.
    private const TOKENS_PER_QUESTION = 300;

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

    public function supportedIntents(): array
    {
        return ['generate_quiz'];
    }

    public function handle(AgentContext $context): AgentResponse
    {
        if ($context->intent?->isChat()) {
            return new AgentResponse(
                answer: '¡Hola! Soy el generador de quizzes. Puedes pedirme que genere preguntas o flashcards sobre este documento, o de algún tema en específico.',
                responseType: 'text',
            );
        }

        if ($context->intent?->isTopicMissing()) {
            $topic = $context->intent->topic;
            return new AgentResponse(
                answer: "No encontré información sobre \"{$topic}\" en este documento. Solo puedo generar preguntas basándome en el contenido del PDF. ¿Te gustaría que te haga preguntas sobre los temas que sí contiene? 😊",
                responseType: 'text',
            );
        }

        $question = $context->question;
        $document = $context->document;

        if (!$document) {
            return new AgentResponse(
                answer:       'Please select a document to generate a quiz from.',
                responseType: 'text',
            );
        }

        $requestedCount = min($this->extractQuestionCount($question), self::MAX_QUESTION_COUNT);

        $totalTokens = (int) $document->chunks()->sum('token_count');
        $capacity    = intdiv($totalTokens, self::TOKENS_PER_QUESTION);

        $effectiveCount = $requestedCount;
        if ($capacity > 0 && $requestedCount > $capacity) {
            $effectiveCount = max(1, min($requestedCount, $capacity));

            $context->conversation->messages()->create([
                'role'          => 'assistant',
                'content'       => "You asked for {$requestedCount} questions, but this document only has enough content for about {$effectiveCount}. I generated {$effectiveCount} questions instead.",
                'response_type' => 'text',
            ]);
        }

        $chunks = $this->selectChunks($context, $document, $question, $effectiveCount);

        if ($chunks->isEmpty()) {
            return new AgentResponse(
                answer: 'I could not find relevant content in your document to generate study material. Try rephrasing your request or asking about a specific topic in the document.',
                responseType: 'text',
            );
        }

        $contextChunks = implode("\n\n---\n\n", $chunks->map(function ($chunk) {
            return "Page " . ($chunk->page_number ?? 'N/A') . ":\n" . $chunk->content;
        })->all());

        $historySection = $this->buildHistorySection($context);

        $prompt = $this->buildPrompt($question, $contextChunks, $historySection, $effectiveCount);

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

        // Save the quiz to the database so it appears in the UI
        $quiz = Quiz::create([
            'document_id' => $document->id,
            'user_id'     => $context->userId,
            'title'       => 'Quiz: ' . substr($question, 0, 30) . (strlen($question) > 30 ? '...' : ''),
            'status'      => 'ready',
        ]);

        $sortOrder = 0;
        $rows      = [];
        foreach ($questions as $q) {
            $rows[] = [
                'quiz_id'        => $quiz->id,
                'question'       => $q['question']       ?? '',
                'type'           => $q['type']           ?? 'multiple_choice',
                'options'        => isset($q['options']) ? json_encode($q['options']) : null,
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation'    => $q['explanation']    ?? null,
                'sort_order'     => $sortOrder++,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        if (!empty($rows)) {
            QuizQuestion::insert($rows);
        }

        return new AgentResponse(
            answer:       $answer,
            responseType: 'quiz',
            metadata:     ['questions' => $questions, 'quiz_id' => $quiz->id],
        );
    }

    private function selectChunks(AgentContext $context, $document, string $question, int $effectiveCount): Collection
    {
        // If the user specified a valid topic, use it for similarity search.
        if ($context->intent?->topic) {
            $topicVector = $this->embeddingProvider->embed($context->intent->topic);

            return $this->similaritySearch->search(
                queryVector: $topicVector,
                userId:      $context->userId,
                documentId:  $document->id,
                threshold:   self::TOPIC_SIMILARITY_THRESHOLD,
                limit:       $effectiveCount * 2,
            );
        }

        // Otherwise (generic quiz request), sample uniformly from the whole document.
        return $this->sampleChunksUniformly($document, $effectiveCount);
    }

    private function sampleChunksUniformly($document, int $effectiveCount): Collection
    {
        $allChunks = $document->chunks()
            ->orderBy('page_number')
            ->orderBy('chunk_index')
            ->get();

        $total = $allChunks->count();
        if ($total === 0) {
            return $allChunks;
        }

        $needed = min($effectiveCount * 2, $total);
        $step   = max(1, (int) floor($total / $needed));

        $sampled = collect();
        for ($i = 0; $i < $total && $sampled->count() < $needed; $i += $step) {
            $sampled->push($allChunks[$i]);
        }

        return $sampled;
    }

    /**
     * Last 3 USER messages only (not assistant replies, which can contain a full
     * formatted quiz and would bloat the prompt). Excludes the current message.
     */
    private function buildHistorySection(AgentContext $context): string
    {
        $recentUserMessages = $context->conversation->messages()
            ->where('role', 'user')
            ->orderByDesc('id')
            ->take(3)
            ->pluck('content')
            ->reverse()
            ->values()
            ->all();

        if (count($recentUserMessages) <= 1) {
            return '';
        }

        $previous = array_slice($recentUserMessages, 0, -1);

        return "## Previous Requests\n" . implode("\n", array_map(fn($m) => "- {$m}", $previous)) . "\n";
    }

    private function extractQuestionCount(string $question): int
    {
        if (preg_match('/(\d+)\s*(preguntas?|questions?|quiz(?:zes)?)/iu', $question, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return self::DEFAULT_QUESTION_COUNT;
    }

    private function buildPrompt(string $question, string $contextChunks, string $historySection, int $effectiveCount): string
    {
        return <<<EOT
You are an expert educational quiz generator. Create study material based on the user's request and the document content below.

{$historySection}
## User's Request
{$question}

## Document Content
{$contextChunks}

## Rules
- Follow the user's instructions exactly: type of questions, language, and difficulty level.
- Generate exactly {$effectiveCount} questions, unless the document content makes that impossible.
- Default to a mix of multiple_choice and flashcard questions if the user does not specify a type.
- CRITICAL: For multiple_choice, randomize the correct option position across A/B/C/D. Do not always pick the same letter.
- Every question must be answerable ONLY from the provided document content.
- For open_ended questions, write a question that requires a paragraph-length answer. Put a model answer in "correct_answer" and an evaluation rubric in "explanation".

## Available Question Types
- multiple_choice: 4 options (A-D), one correct
- flashcard: term/concept → definition
- true_false: statement + "True" or "False" as the correct answer
- open_ended: descriptive question + model answer + evaluation rubric

## Output
Output ONLY a valid JSON array, no surrounding text, no markdown fences.
[
  {"type":"multiple_choice","question":"...","options":["A) ...","B) ...","C) ...","D) ..."],"correct_answer":"C","explanation":"..."},
  {"type":"flashcard","question":"Define: ...","correct_answer":"...","explanation":"..."},
  {"type":"true_false","question":"...","correct_answer":"True","explanation":"..."},
  {"type":"open_ended","question":"Explain...","correct_answer":"Model answer...","explanation":"Rubric: mention X, Y, Z"}
]
EOT;
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
