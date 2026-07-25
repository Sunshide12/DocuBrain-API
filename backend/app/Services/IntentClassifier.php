<?php

namespace App\Services;

use App\DTOs\ClassifiedIntent;
use App\Models\Document;
use App\Services\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class IntentClassifier
{
    /**
     * Minimum similarity score for a topic to be considered "in the document".
     * Higher than the quiz threshold (0.35) — we need a confident match to say
     * the document actually covers the topic.
     */
    private const TOPIC_RELEVANCE_THRESHOLD = 0.50;

    /** If fewer than this many chunks match, the topic is treated as absent. */
    private const MIN_RELEVANT_CHUNKS = 2;

    public function __construct(
        private readonly EmbeddingProvider        $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
    ) {}

    /**
     * Classify the user's message against the set of intents the active agent supports.
     *
     * Steps:
     *  1. Ask the LLM to pick an intent from $supportedIntents (or "chat") and
     *     extract any specific topic the user mentioned.
     *  2. If a topic was extracted and a document is attached, verify the topic
     *     exists in the document via pgvector similarity search.
     *  3. Return a ClassifiedIntent with everything the agent needs.
     *
     * @param string      $message          Raw user message.
     * @param string[]    $supportedIntents Intents declared by the active agent.
     * @param Document|null $document       The conversation's document (may be null).
     * @param int         $userId
     */
    public function classify(
        string    $message,
        array     $supportedIntents,
        ?Document $document,
        int       $userId,
    ): ClassifiedIntent {
        $llmResult = $this->classifyViaLLM($message, $supportedIntents);

        $topicInDocument = true;
        if ($llmResult['topic'] !== null && $document !== null) {
            $topicInDocument = $this->isTopicInDocument(
                topic:      $llmResult['topic'],
                document:   $document,
                userId:     $userId,
            );
        }

        return new ClassifiedIntent(
            intent:          $llmResult['intent'],
            topic:           $llmResult['topic'],
            topicInDocument: $topicInDocument,
            confidence:      $llmResult['confidence'],
        );
    }

    // ──────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────

    /**
     * A single, cheap LLM call that returns intent + extracted topic.
     * Returns ['intent' => string, 'topic' => string|null, 'confidence' => float].
     */
    private function classifyViaLLM(string $message, array $supportedIntents): array
    {
        $intentList = implode(', ', $supportedIntents);

        $prompt = <<<EOT
Classify the user's message. Reply with ONLY valid JSON — no other text, no markdown fences.

Supported intents: {$intentList}
- "chat": The user is having a conversation, greeting, referencing previous messages,
  asking meta-questions, or saying something that is NOT a direct actionable request
  matching one of the supported intents.

User message: "{$message}"

Required JSON format:
{"intent": "...", "topic": "...", "confidence": 0.0}

Rules:
- "intent" MUST be one of the supported intents above, or exactly "chat".
- "topic" is the specific subject, entity, or person the user explicitly mentions
  (e.g. "Elon Musk", "photosynthesis", "World War II"). Set to null (JSON null, not
  the string "null") when the request is generic and contains no specific topic
  (e.g. "give me 5 questions", "make flashcards").
- "confidence" is a float between 0.0 and 1.0 indicating how sure you are.
EOT;

        $baseUrl = config('services.openrouter.base_url');
        $apiKey  = config('services.openrouter.api_key');
        $model   = config('services.openrouter.llm_model');

        $response = Http::withToken($apiKey)
            ->timeout(15)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                // Keep the response short and deterministic.
                'max_tokens'  => 80,
                'temperature' => 0.0,
            ]);

        if ($response->failed()) {
            // If classification fails, default to the first supported intent so the
            // agent can still attempt to handle the message normally.
            return [
                'intent'     => $supportedIntents[0] ?? 'chat',
                'topic'      => null,
                'confidence' => 0.0,
            ];
        }

        $raw = trim($response->json('choices.0.message.content') ?? '{}');

        // Strip markdown fences if the model ignores the instruction.
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [
                'intent'     => $supportedIntents[0] ?? 'chat',
                'topic'      => null,
                'confidence' => 0.0,
            ];
        }

        $intent     = $decoded['intent']     ?? 'chat';
        $topic      = $decoded['topic']      ?? null;
        $confidence = (float) ($decoded['confidence'] ?? 0.5);

        // Sanitize: ensure intent is in the allowed set.
        $allowed = array_merge($supportedIntents, ['chat']);
        if (!in_array($intent, $allowed, true)) {
            $intent = 'chat';
        }

        // Sanitize: null-ish strings become actual null.
        if ($topic !== null && (strtolower(trim((string) $topic)) === 'null' || trim((string) $topic) === '')) {
            $topic = null;
        }

        return [
            'intent'     => $intent,
            'topic'      => $topic,
            'confidence' => $confidence,
        ];
    }

    /**
     * Verify that the topic the user mentioned has a meaningful presence in the
     * document by running a pgvector similarity search on the topic string.
     */
    private function isTopicInDocument(string $topic, Document $document, int $userId): bool
    {
        try {
            $topicVector = $this->embeddingProvider->embed($topic);

            $matches = $this->similaritySearch->search(
                queryVector: $topicVector,
                userId:      $userId,
                documentId:  $document->id,
                threshold:   self::TOPIC_RELEVANCE_THRESHOLD,
                limit:       self::MIN_RELEVANT_CHUNKS,
            );

            return $matches->count() >= self::MIN_RELEVANT_CHUNKS;
        } catch (\Throwable) {
            // If the embedding/search fails, assume the topic is present so the
            // agent can handle the request rather than incorrectly rejecting it.
            return true;
        }
    }
}
