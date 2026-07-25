<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class QuizGenerationCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Quiz   $quiz,
        public readonly int    $userId,
        public readonly string $status, // ready | failed
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'QuizGenerationCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'quiz_id'     => $this->quiz->id,
            'document_id' => $this->quiz->document_id,
            'status'      => $this->status,
        ];
    }
}
