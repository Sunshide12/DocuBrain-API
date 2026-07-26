<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentMathPage;
use App\Services\Contracts\MathExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ProcessMathExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 300;

    public function __construct(
        public readonly Document $document,
    ) {}

    public function handle(MathExtractor $extractor): void
    {
        // Idempotent: clear previously extracted pages before re-extracting
        $this->document->mathPages()->delete();

        $pages = $extractor->extract(
            Storage::path($this->document->file_path)
        );

        $rows = [];
        foreach ($pages as $pageNumber => $content) {
            $trimmed = trim($content);
            if ($trimmed === '') {
                continue;
            }
            $rows[] = [
                'document_id' => $this->document->id,
                'page_number' => (int) $pageNumber,
                'content' => $trimmed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rows)) {
            DocumentMathPage::insert($rows);
        }

        $this->document->update(['math_extracted_at' => now()]);

        Log::info('ProcessMathExtractionJob completed', [
            'document_id' => $this->document->id,
            'pages' => count($rows),
        ]);
    }
}
