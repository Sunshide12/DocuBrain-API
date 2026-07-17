<?php

namespace App\Services\Contracts;

interface TextExtractor
{
    /**
     * Extrae texto de un documento. Retorna un array donde cada elemento
     * es el texto de una página específica, con la llave siendo el número de página (1-based).
     *
     * @param string $absolutePath
     * @return array<int, string>
     * @throws \App\Exceptions\TextExtractionException
     */
    public function extract(string $absolutePath): array;
}
