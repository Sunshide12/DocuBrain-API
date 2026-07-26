<?php

namespace App\Services\Contracts;

use App\Exceptions\TextExtractionException;

interface TextExtractor
{
    /**
     * Extrae texto de un documento. Retorna un array donde cada elemento
     * es el texto de una página específica, con la llave siendo el número de página (1-based).
     *
     * @return array<int, string>
     *
     * @throws TextExtractionException
     */
    public function extract(string $absolutePath): array;
}
