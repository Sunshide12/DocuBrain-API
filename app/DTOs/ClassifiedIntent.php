<?php

namespace App\DTOs;

readonly class ClassifiedIntent
{
    public function __construct(
        public string $intent,          // e.g. "generate_quiz", "ask_question", "chat"
        public ?string $topic,           // specific subject mentioned, null if generic
        public bool $topicInDocument, // false when topic was not found in the document
        public float $confidence,      // 0.0 – 1.0
        public ?string $topicType = null, // "semantic" | "structural" | null
    ) {}

    /** True when the user is just chatting rather than making an actionable request. */
    public function isChat(): bool
    {
        return $this->intent === 'chat';
    }

    /**
     * True when the topic is a reference to a numbered/positional item in the
     * document (e.g. "problema 2.1.1", "el primer ejercicio") rather than a
     * conceptual subject. Callers should resolve these by document order,
     * not by semantic similarity — see StructuralChunkResolver.
     */
    public function isStructural(): bool
    {
        return $this->topicType === 'structural';
    }

    /**
     * True when the user mentioned a specific topic that is NOT present in the
     * document — the agent should return a friendly "not in this document" message.
     */
    public function isTopicMissing(): bool
    {
        return $this->topic !== null && ! $this->topicInDocument;
    }
}
