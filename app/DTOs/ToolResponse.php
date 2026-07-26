<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ToolResponse
{
    /**
     * @param  string  $answer  The tool's text response.
     * @param  array  $sourceChunks  References [['id' => ..., 'page_number' => ...], ...].
     * @param  string|null  $responseType  'text' | 'quiz' | 'steps'
     * @param  array  $metadata  Extra structured data.
     * @param  string  $agentKey  Key of the tool that produced this response, persisted on the message for UI attribution.
     */
    public function __construct(
        public string $answer,
        public string $agentKey,
        public array $sourceChunks = [],
        public ?string $responseType = 'text',
        public array $metadata = [],
    ) {}
}
