<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\OpenRouterClient;
use Illuminate\Support\Facades\Http;

class OpenRouterHttpClient implements OpenRouterClient
{
    public function chat(array $messages, array $options = []): string
    {
        $baseUrl = config('services.openrouter.base_url');
        $apiKey = config('services.openrouter.api_key');
        $model = $options['model'] ?? config('services.openrouter.llm_model');
        $timeout = $options['timeout'] ?? 60;

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->post(rtrim($baseUrl, '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw new \RuntimeException('OpenRouter API error: '.$response->status().' - '.$response->body());
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
