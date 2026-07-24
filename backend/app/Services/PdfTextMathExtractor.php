<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\MathExtractor;
use App\Services\Contracts\TextExtractor;

/**
 * Extracts per-page text from a PDF using the same pdftotext pipeline
 * already used for the normal document processing. No external API needed.
 *
 * The output is plain text with Unicode math symbols as-is (e.g. Σ, π, ∫).
 * The Math Solver LLM prompt is fully capable of reasoning over this format.
 */
class PdfTextMathExtractor implements MathExtractor
{
    public function __construct(
        private readonly TextExtractor $textExtractor,
    ) {}

    public function extract(string $absolutePath): array
    {
        return $this->textExtractor->extract($absolutePath);
    }
}
