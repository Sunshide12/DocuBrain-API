<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Services\Contracts\EmbeddingProvider;

/**
 * Verifies whether a topic extracted by the OrchestratorRouter actually has a
 * meaningful presence in the conversation's document. The router only sees the
 * raw message text, so it cannot tell whether a mentioned topic is covered by
 * the document — that requires an embedding + pgvector similarity search.
 */
class IntentClassifier
{
    /**
     * Ceiling for the relevance threshold. The effective value is the configured
     * retrieval threshold (services.openrouter.similarity_threshold): this guard
     * must never be stricter than the retrieval it protects, otherwise it rejects
     * topics that DocumentQATool would have answered perfectly well.
     */
    private const MAX_TOPIC_RELEVANCE_THRESHOLD = 0.50;

    /** If fewer than this many chunks match, the topic is treated as absent. */
    private const MIN_RELEVANT_CHUNKS = 2;

    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
    ) {}

    /**
     * @param  string|null  $topic  Topic extracted by the router, or null when the request was generic.
     * @param  bool  $isStructural  Structural references (e.g. "problema 2.1") are resolved by
     *                              document order, not similarity — always considered present.
     */
    public function topicExistsInDocument(
        ?string $topic,
        bool $isStructural,
        ?Document $document,
        int $userId,
    ): bool {
        if ($topic === null || $document === null || $isStructural) {
            return true;
        }

        try {
            $topicVector = $this->embeddingProvider->embed($topic);

            $threshold = min(
                (float) config('services.openrouter.similarity_threshold', self::MAX_TOPIC_RELEVANCE_THRESHOLD),
                self::MAX_TOPIC_RELEVANCE_THRESHOLD,
            );

            // A short document may hold all of its content in a single chunk, so
            // demanding two matches would make its topics permanently "missing".
            $required = min(self::MIN_RELEVANT_CHUNKS, max(1, $document->chunks()->count()));

            $matches = $this->similaritySearch->search(
                queryVector: $topicVector,
                userId: $userId,
                documentId: $document->id,
                threshold: $threshold,
                limit: $required,
            );

            return $matches->count() >= $required;
        } catch (\Throwable) {
            // If the embedding/search fails, assume the topic is present so the
            // tool can handle the request rather than incorrectly rejecting it.
            return true;
        }
    }
}
