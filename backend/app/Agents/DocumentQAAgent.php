<?php

namespace App\Agents;

use App\DTOs\AgentContext;
use App\DTOs\AgentResponse;
use App\Services\Contracts\AgentHandler;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Support\Facades\Http;

class DocumentQAAgent implements AgentHandler
{
    public function __construct(
        private readonly EmbeddingProvider      $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
    ) {}

    public function key(): string
    {
        return 'document_qa';
    }

    public function name(): string
    {
        return 'Document Q&A';
    }

    public function description(): string
    {
        return 'Answer questions using the content of the selected document.';
    }

    public function handle(AgentContext $context): AgentResponse
    {
        $threshold = (float) config('services.openrouter.similarity_threshold', 0.75);

        $questionVector = $this->embeddingProvider->embed($context->question);

        $chunks = $this->similaritySearch->search(
            queryVector: $questionVector,
            userId: $context->userId,
            documentId: $context->conversation->document_id,
            threshold: $threshold,
        );

        if ($chunks->isEmpty()) {
            return new AgentResponse(
                answer: 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                sourceChunks: [],
            );
        }

        $contextText = implode("\n\n---\n\n", $chunks->map(function ($chunk) {
            return "Página " . ($chunk->page_number ?? 'N/A') . ":\n" . $chunk->content;
        })->all());

        $question = $context->question;

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

        $baseUrl = config('services.openrouter.base_url');
        $apiKey  = config('services.openrouter.api_key');
        $model   = config('services.openrouter.llm_model');

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        $answer = trim($response->json('choices.0.message.content') ?? '');

        $sourceChunks = $chunks->map(fn($chunk) => [
            'id'          => $chunk->id,
            'page_number' => $chunk->page_number,
        ])->all();

        return new AgentResponse(
            answer: $answer,
            sourceChunks: $sourceChunks,
        );
    }
}
