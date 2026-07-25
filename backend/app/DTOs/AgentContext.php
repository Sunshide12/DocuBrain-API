<?php

namespace App\DTOs;

use App\Models\Conversation;
use App\Models\Document;

readonly class AgentContext
{
    public function __construct(
        public string            $question,
        public Conversation      $conversation,
        public ?Document         $document,
        public int               $userId,
        public ?ClassifiedIntent $intent = null,
    ) {}
}
