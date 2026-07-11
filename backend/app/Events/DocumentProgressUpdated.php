<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Represents a progress update for a Document being processed.
 * The Job publishes this as JSON to the Redis channel
 * `docubrain.document.{id}` via Redis::publish.
 *
 * In Fase 6 this will become a real GraphQL Subscription payload.
 */
final class DocumentProgressUpdated
{
    public function __construct(
        public readonly int $documentId,
        /** One of: extracting | chunking | embedding | ready | failed */
        public readonly string $status,
        /** Human-readable message, e.g. "Extracting text from PDF…" */
        public readonly string $message,
        /** 0–100 */
        public readonly int $progress,
    ) {}

    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'status'      => $this->status,
            'message'     => $this->message,
            'progress'    => $this->progress,
        ];
    }
}
