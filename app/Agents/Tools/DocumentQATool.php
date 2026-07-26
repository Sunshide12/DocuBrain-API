<?php

declare(strict_types=1);

namespace App\Agents\Tools;

use App\DTOs\ToolContext;
use App\DTOs\ToolResponse;
use App\Services\Contracts\AgentTool;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\OpenRouterClient;
use App\Services\PgvectorSimilaritySearch;
use App\Services\StructuralChunkResolver;

class DocumentQATool implements AgentTool
{
    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
        private readonly StructuralChunkResolver $structuralResolver,
        private readonly OpenRouterClient $openRouter,
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

    public function requiresDocument(): bool
    {
        return true;
    }

    public function execute(ToolContext $context): ToolResponse
    {
        if ($context->intent->isTopicMissing()) {
            $topic = $context->intent->topic;

            return new ToolResponse(
                answer: "El documento no contiene información sobre \"{$topic}\". Puedo responder preguntas sobre los temas que están en el PDF.",
                agentKey: $this->key(),
            );
        }

        $sourceChunks = [];
        $contextText = null;

        if ($context->intent->isStructural() && $context->conversation->document_id) {
            $contextText = $this->structuralResolver->resolve(
                question: $context->question,
                topic: $context->intent->topic ?? '',
                documentId: $context->conversation->document_id,
            );
        }

        if ($contextText === null) {
            $threshold = (float) config('services.openrouter.similarity_threshold', 0.75);

            $questionVector = $this->embeddingProvider->embed($context->question);

            $chunks = $this->similaritySearch->search(
                queryVector: $questionVector,
                userId: $context->userId,
                documentId: $context->conversation->document_id,
                threshold: $threshold,
            );

            if ($chunks->isEmpty()) {
                return new ToolResponse(
                    answer: 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                    agentKey: $this->key(),
                    sourceChunks: [],
                );
            }

            $contextText = implode("\n\n---\n\n", $chunks->map(function ($chunk) {
                return 'Página '.($chunk->page_number ?? 'N/A').":\n".$chunk->content;
            })->all());

            $sourceChunks = $chunks->map(fn ($chunk) => [
                'id' => $chunk->id,
                'page_number' => $chunk->page_number,
            ])->all();
        }

        $question = $context->question;

        $prompt = <<<EOT
You are an intelligent and helpful AI assistant. Your primary objective is to answer the user's questions based on the provided document context.

## Rules

- Use the provided context as your primary source of truth.
- You may use your general knowledge to explain, summarize, or clarify the concepts found in the context, but do NOT contradict the document.
- Do not invent specific facts, numbers, or quotes that should come from the document.
- Tell to the user the page number from where the information was extracted.
- Write the answer in the same language as the user's question.


## Context

$contextText

## User Question

$question
EOT;

        $answer = $this->openRouter->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['timeout' => 60]);

        return new ToolResponse(
            answer: $answer,
            agentKey: $this->key(),
            sourceChunks: $sourceChunks,
        );
    }
}
