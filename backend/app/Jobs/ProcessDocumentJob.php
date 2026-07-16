<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\DocumentProgressUpdated;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Nuwave\Lighthouse\Execution\Utils\Subscription;

/**
 * Processes a document through the pipeline:
 *   extracting → chunking → embedding → ready
 *
 * In Fase 4 the heavy work is STUBBED — each step sleeps briefly
 * and publishes a progress event to the Redis channel
 * `docubrain.document.{id}` (Pub/Sub).
 *
 * Real PDF extraction + embeddings will be wired in Fase 8.
 *
 * What Redis actually does here:
 *  - Queue driver:   the job payload itself lives in Redis (a list)
 *  - Pub/Sub:        progress events are published via Redis::publish()
 */
final class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Number of times the job may be attempted. */
    public int $tries = 3;

    /** Number of seconds to wait before retrying after a failure. */
    public int $backoff = 10;

    public function __construct(
        public readonly Document $document,
    ) {}

    public function handle(): void
    {
        try {
            // Step 1 — extracting text
            $this->updateProgress('extracting', 'Extracting text from PDF…', 10);
            $this->simulateWork();

            // Step 2 — chunking
            $this->updateProgress('chunking', 'Splitting text into chunks…', 40);
            $this->simulateWork();

            // Step 3 — generating embeddings (stub — real work in Fase 8)
            $this->updateProgress('embedding', 'Generating embeddings (stub)…', 75);
            $this->simulateWork();

            // Step 4 — done
            $this->document->update(['status' => 'ready']);
            \App\Events\DocumentProcessed::dispatch($this->document);
            $this->updateProgress('ready', 'Document is ready.', 100);

            Log::info('ProcessDocumentJob completed', ['document_id' => $this->document->id]);
        } catch (\Throwable $e) {
            $this->document->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            \App\Events\DocumentProcessed::dispatch($this->document);

            $this->updateProgress('failed', 'Processing failed: ' . $e->getMessage(), 0);

            Log::error('ProcessDocumentJob failed', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);

            // Re-throw so Laravel can record the failure and retry
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Publish a progress event to the Redis Pub/Sub channel.
     *
     * Channel pattern: docubrain.document.{id}
     *
     * A Redis subscriber (redis-cli SUBSCRIBE / the Fase-6 WebSocket server)
     * can listen to this channel in real time.
     *
     * WHY Redis::publish and not a Laravel event?
     * Redis::publish goes DIRECTLY to the Pub/Sub bus — any subscriber connected
     * to Redis (even outside of PHP) receives it immediately.
     * A normal Laravel event only runs within the current PHP process.
     */
    private function updateProgress(string $status, string $message, int $progress): void
    {
        $payload = [
            'document_id' => $this->document->id,
            'status'      => $status,
            'message'     => $message,
            'progress'    => $progress,
        ];

        Subscription::broadcast('documentProgress', $payload);
    }

    /**
     * Simulates CPU/IO work.
     * Replaced by real logic in Fase 8.
     * Skipped when `APP_ENV=testing` to keep the test suite fast.
     */
    private function simulateWork(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        sleep(1);
    }
}
