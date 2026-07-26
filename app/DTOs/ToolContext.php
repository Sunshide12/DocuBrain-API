<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Conversation;
use App\Models\Document;

readonly class ToolContext
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history  Last messages of the conversation, oldest first.
     */
    public function __construct(
        public string $question,
        public Conversation $conversation,
        public ?Document $document,
        public int $userId,
        public ClassifiedIntent $intent,
        public array $history = [],
    ) {}
}
