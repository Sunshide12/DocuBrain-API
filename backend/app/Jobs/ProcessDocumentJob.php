<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\DocumentProgressUpdated;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\TextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;

final class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 300;

    public function __construct(
        public readonly Document $document,
    ) {
        $this->timeout = config('services.document_processing.timeout', 300);
    }

    public function handle(TextExtractor $textExtractor, EmbeddingProvider $embeddingProvider): void
    {
        try {
            // Idempotencia: Limpiar chunks previos para que el job sea re-ejecutable
            $this->document->chunks()->delete();

            // Paso 1 — Extracting
            $this->updateProgress('extracting', 'Extracting text from PDF…', 10);
            $pages = $textExtractor->extract(
                Storage::path($this->document->file_path)
            );

            // Paso 2 — Chunking
            $this->updateProgress('chunking', 'Splitting text into chunks…', 40);
            $chunks = $this->chunkTextByPage($pages, 375, 37);
            
            foreach ($chunks as $chunk) {
                if (str_word_count($chunk['content']) > 6000) {
                    Log::warning("Chunk excepcionalmente largo detectado", ['document_id' => $this->document->id]);
                }
            }

            // Paso 3 — Embedding
            $this->updateProgress('embedding', 'Generating embeddings…', 75);
            $vectors = $embeddingProvider->embedBatch(
                array_column($chunks, 'content')
            );

            $rows = array_map(function($chunk, $vector) {
                return [
                    'document_id' => $this->document->id,
                    'chunk_index' => $chunk['index'],
                    'content'     => $chunk['content'],
                    'token_count' => $chunk['word_count'],
                    'page_number' => $chunk['page_number'],
                    'embedding'   => (new Vector($vector))->__toString(),
                ];
            }, $chunks, $vectors);

            DocumentChunk::insert($rows);

            // Paso 4 — Done
            $this->document->update(['status' => 'ready']);
            $this->updateProgress('ready', 'Document is ready.', 100);

            Log::info('ProcessDocumentJob completed', ['document_id' => $this->document->id]);
        } catch (\Throwable $e) {
            $this->document->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->updateProgress('failed', 'Processing failed: ' . $e->getMessage(), 0);

            Log::error('ProcessDocumentJob failed', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function updateProgress(string $status, string $message, int $progress): void
    {
        Log::info("Document {$this->document->id} is now in step: {$status}");
        $this->document->update(['status' => $status]);

        \Illuminate\Support\Facades\Cache::increment("documents.user.{$this->document->user_id}.version");

        try {
            DocumentProgressUpdated::dispatch(
                $this->document->id,
                $this->document->user_id,
                $status,
                $message,
                $progress
            );
        } catch (\Throwable $e) {
            Log::error("Failed to broadcast: " . $e->getMessage());
        }
    }

    private function chunkTextByPage(array $pages, int $wordsPerChunk, int $overlapWords): array
    {
        $chunks = [];
        $chunkIndex = 0;

        foreach ($pages as $pageNumber => $text) {
            // Split text keeping spaces and newlines
            $tokens = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            $allWordsAndSpaces = [];
            foreach ($tokens as $token) {
                if ($token === '') continue;
                $isSpace = preg_match('/^\s+$/', $token) === 1;
                $allWordsAndSpaces[] = ['text' => $token, 'is_space' => $isSpace];
            }

            $i = 0;
            while ($i < count($allWordsAndSpaces)) {
                $chunkStartIdx = $i;
                $wordsInThisChunk = 0;
                $chunkText = '';

                while ($i < count($allWordsAndSpaces) && $wordsInThisChunk < $wordsPerChunk) {
                    $item = $allWordsAndSpaces[$i];
                    $chunkText .= $item['text'];
                    if (!$item['is_space']) {
                        $wordsInThisChunk++;
                    }
                    $i++;
                }
                
                $chunkContent = trim($chunkText);
                if (!empty($chunkContent)) {
                    $chunks[] = [
                        'index' => $chunkIndex++,
                        'page_number' => $pageNumber,
                        'content' => $chunkContent,
                        'word_count' => count(explode(' ', preg_replace('/\s+/', ' ', $chunkContent))),
                    ];
                }

                if ($i < count($allWordsAndSpaces)) {
                    $overlapCount = 0;
                    $i--; // Step back to last added item
                    while ($i > $chunkStartIdx && $overlapCount < $overlapWords) {
                        if (!$allWordsAndSpaces[$i]['is_space']) {
                            $overlapCount++;
                        }
                        if ($overlapCount < $overlapWords) {
                            $i--;
                        }
                    }
                    // Prevent infinite loops if word is too long
                    if ($i <= $chunkStartIdx) {
                        $i = $chunkStartIdx + 1; // force advance at least 1 word/space
                    }
                }
            }
        }

        return $chunks;
    }
}
