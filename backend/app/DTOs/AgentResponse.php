<?php

namespace App\DTOs;

readonly class AgentResponse
{
    /**
     * @param string      $answer       The agent's text response.
     * @param array       $sourceChunks References [['id' => ..., 'page_number' => ...], ...].
     * @param string|null $responseType 'text' | 'quiz' | 'steps'
     * @param array       $metadata     Extra structured data.
     */
    public function __construct(
        public string  $answer,
        public array   $sourceChunks = [],
        public ?string $responseType = 'text',
        public array   $metadata = [],
    ) {}
}
