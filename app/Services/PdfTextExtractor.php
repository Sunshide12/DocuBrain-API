<?php

namespace App\Services;

use App\Exceptions\TextExtractionException;
use App\Services\Contracts\TextExtractor;
use Spatie\PdfToText\Pdf;

class PdfTextExtractor implements TextExtractor
{
    /**
     * @param string $absolutePath
     * @return array<int, string>
     * @throws TextExtractionException
     */
    public function extract(string $absolutePath): array
    {
        try {
            $text = Pdf::getText($absolutePath);
            
            if (blank($text)) {
                throw new TextExtractionException('No se pudo extraer texto legible del PDF');
            }

            // pdftotext separa las páginas usando el carácter form-feed (\x0C)
            $pages = explode("\x0C", $text);
            
            $result = [];
            foreach ($pages as $index => $pageText) {
                $trimmed = trim($pageText);
                if (!empty($trimmed)) {
                    $result[$index + 1] = $trimmed;
                }
            }
            
            if (empty($result)) {
                throw new TextExtractionException('No se pudo extraer texto legible del PDF');
            }

            return $result;
        } catch (\Spatie\PdfToText\Exceptions\PdfNotFound $e) {
            throw new TextExtractionException("PDF no encontrado: " . $e->getMessage(), 0, $e);
        } catch (\Spatie\PdfToText\Exceptions\CouldNotExtractText $e) {
            // Dejar burbujear o lanzar propia
            throw new TextExtractionException("Fallo al extraer texto: " . $e->getMessage(), 0, $e);
        }
    }
}
