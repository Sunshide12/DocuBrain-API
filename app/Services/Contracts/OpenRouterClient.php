<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface OpenRouterClient
{
    /**
     * Send a chat completion request and return the raw text content of the reply.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{max_tokens?: int, temperature?: float, timeout?: int}  $options
     *
     * @throws \RuntimeException When the API call fails.
     */
    public function chat(array $messages, array $options = []): string;
}
