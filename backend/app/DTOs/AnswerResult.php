<?php

namespace App\DTOs;

readonly class AnswerResult
{
    /**
     * @param string $answer
     * @param array $sourceChunks Un array de arrays, e.g. [['id' => 1, 'page_number' => 2], ...]
     */
    public function __construct(
        public string $answer,
        public array  $sourceChunks,
    ) {}
}
