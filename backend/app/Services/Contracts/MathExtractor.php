<?php

namespace App\Services\Contracts;

interface MathExtractor
{
    /**
     * Extract text with mathematical notation preserved from a PDF.
     * Returns an array keyed by page number with LaTeX-formatted content.
     *
     * @return array<int, string>
     */
    public function extract(string $absolutePath): array;
}
