<?php

namespace App\Services;

use App\DTOs\AnswerResult;
use App\Services\Contracts\AnswerGenerator;
use Illuminate\Support\Facades\Http;

class OpenRouterAnswerGenerator implements AnswerGenerator
{
    public function generate(string $question, array $contextChunks): AnswerResult
    {
        if (empty($contextChunks)) {
            return new AnswerResult(
                answer: 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                sourceChunks: []
            );
        }

        $baseUrl = config('services.openrouter.base_url');
        $apiKey = config('services.openrouter.api_key');
        $model = config('services.openrouter.llm_model');

        $contextText = implode("\n\n---\n\n", array_map(function ($chunk) {
            return "Página " . ($chunk->page_number ?? 'N/A') . ":\n" . $chunk->content;
        }, $contextChunks));

        $prompt = <<<EOT
Eres un asistente que responde preguntas ÚNICAMENTE basándote en el contexto...
EOT;


        // Decisión: Sin historial de conversación. Cada pregunta es independiente.
        // El costo por consulta sube linealmente con los turnos anteriores si se añade historial.
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("Error OpenRouter API Completions: " . $response->status() . " - " . $response->body());
        }

        $answer = $response->json('choices.0.message.content') ?? '';

        $sourceChunks = array_map(function ($chunk) {
            return [
                'id' => $chunk->id,
                'page_number' => $chunk->page_number
            ];
        }, $contextChunks);

        return new AnswerResult(
            answer: trim($answer),
            sourceChunks: $sourceChunks
        );
    }
}
