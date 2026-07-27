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
use Illuminate\Support\Facades\Cache;

class DocumentQATool implements AgentTool
{
    /** Max characters kept per chunk before injecting it into the prompt. */
    private const MAX_CHUNK_CHARS = 2000;

    /** How long a document+question answer is cached before the LLM is called again. */
    private const CACHE_TTL_SECONDS = 900;

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

        $documentId = $context->conversation->document_id;
        $cacheKey = $documentId ? $this->cacheKey($documentId, $context->question) : null;

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return new ToolResponse(
                    answer: $cached['answer'],
                    agentKey: $this->key(),
                    sourceChunks: $cached['source_chunks'] ?? [],
                );
            }
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

            // Dedup near-identical chunks (common with overlapping page splits) and cap
            // each chunk's length so the prompt doesn't carry redundant/oversized text.
            $chunks = $chunks->unique(fn ($chunk) => md5(trim(preg_replace('/\s+/', ' ', $chunk->content))))->values();

            $contextText = implode("\n\n---\n\n", $chunks->map(function ($chunk) {
                $content = mb_strlen($chunk->content) > self::MAX_CHUNK_CHARS
                    ? mb_substr($chunk->content, 0, self::MAX_CHUNK_CHARS).'…'
                    : $chunk->content;

                return 'Página '.($chunk->page_number ?? 'N/A').":\n".$content;
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
- Format the answer in Markdown: use headings (##), bullet/numbered lists, **bold**
  for key terms, and code blocks (```) for code or formulas when relevant.

## Context

$contextText

## User Question

$question
EOT;

        $answer = $this->openRouter->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['timeout' => 60]);

        if ($cacheKey !== null) {
            Cache::put($cacheKey, ['answer' => $answer, 'source_chunks' => $sourceChunks], self::CACHE_TTL_SECONDS);
        }

        return new ToolResponse(
            answer: $answer,
            agentKey: $this->key(),
            sourceChunks: $sourceChunks,
        );
    }

    /**
     * Cache key scoped to the document and the normalized question text, so that
     * repeated/near-identical questions about the same document skip the embedding
     * search and the LLM call entirely.
     */
    private function cacheKey(int $documentId, string $question): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $question)));

        return "docqa.answer.{$documentId}.".md5($normalized);
    }
}
