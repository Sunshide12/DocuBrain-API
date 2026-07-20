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
You are an AI assistant whose knowledge is limited to the document provided below.

Your primary objective is to answer the reader's questions accurately using ONLY the information contained in the extracted document context.

## Rules

- Treat the provided context as your only source of truth.
- Never invent, assume, or complete missing information.
- If the answer cannot be determined from the provided context, explicitly say so in a natural way. For example:
  - "This document does not mention that."
  - "Based on the pages I have available, I cannot answer that."
  - "The provided text does not contain enough information to determine that."

- Do NOT use external knowledge, even if you know the answer.
- Do NOT speculate.
- Do NOT fabricate citations or page numbers.
- If multiple sections of the context contribute to the answer, combine them into a single coherent response.

## Response Style

- Answer naturally and conversationally.
- Be concise by default.
- If the user requests more detail, provide a more comprehensive explanation using only the document.
- Preserve terminology used by the document.
- When appropriate, mention the page number(s) where the information was found.

## Context

$contextText

## User Question

$question
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
