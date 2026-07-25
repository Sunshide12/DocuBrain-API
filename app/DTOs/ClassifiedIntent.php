<?php

namespace App\DTOs;

readonly class ClassifiedIntent
{
    public function __construct(
        public string  $intent,          // e.g. "generate_quiz", "ask_question", "chat"
        public ?string $topic,           // specific subject mentioned, null if generic
        public bool    $topicInDocument, // false when topic was not found in the document
        public float   $confidence,      // 0.0 – 1.0
    ) {}

    /** True when the user is just chatting rather than making an actionable request. */
    public function isChat(): bool
    {
        return $this->intent === 'chat';
    }

    /**
     * True when the user mentioned a specific topic that is NOT present in the
     * document — the agent should return a friendly "not in this document" message.
     */
    public function isTopicMissing(): bool
    {
        return $this->topic !== null && !$this->topicInDocument;
    }
}
