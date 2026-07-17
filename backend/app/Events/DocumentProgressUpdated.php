<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Represents a progress update for a Document being processed.
 * Broadcasts in real-time to Laravel Reverb via Echo.
 */
final class DocumentProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $documentId,
        public readonly int $userId,
        /** One of: extracting | chunking | embedding | ready | failed */
        public readonly string $status,
        /** Human-readable message, e.g. "Extracting text from PDF…" */
        public readonly string $message,
        /** 0–100 */
        public readonly int $progress,
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast on a private channel specific to the user
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DocumentProgressUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'status'      => $this->status,
            'message'     => $this->message,
            'progress'    => $this->progress,
        ];
    }
}
