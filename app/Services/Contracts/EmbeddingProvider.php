<?php

namespace App\Services\Contracts;

interface EmbeddingProvider
{
    /**
     * @return array<float>
     */
    public function embed(string $text): array;

    /**
     * @param  string[]  $texts
     * @return array<int, array<float>>
     */
    public function embedBatch(array $texts): array;
}
