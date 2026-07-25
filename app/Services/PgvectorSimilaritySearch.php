<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Collection;

class PgvectorSimilaritySearch
{
    /**
     * @param array $queryVector
     * @param int $userId
     * @param int|null $documentId
     * @param float $threshold
     * @param int $limit
     * @return Collection
     */
    public function search(array $queryVector, int $userId, ?int $documentId, float $threshold, int $limit = 5): Collection
    {
        // Fuerza bruta sin índice vectorial: para < 10K chunks el escaneo secuencial es suficiente.
        // Un índice HNSW tiene sentido a partir de ~100K filas.
        $query = DocumentChunk::query()
            ->whereHas('document', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        if ($documentId) {
            $query->where('document_id', $documentId);
        }

        return $query->similarTo($queryVector, $threshold)
            ->limit($limit)
            ->get();
    }
}
