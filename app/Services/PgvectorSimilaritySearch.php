<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

class PgvectorSimilaritySearch
{
    public function search(array $queryVector, int $userId, ?int $documentId, float $threshold, int $limit = 5): Collection
    {
        // Fuerza bruta sin índice vectorial: para < 10K chunks el escaneo secuencial es suficiente.
        // Un índice HNSW tiene sentido a partir de ~100K filas.
        return $this->baseQuery($userId, $documentId)
            ->similarTo($queryVector, $threshold)
            ->limit($limit)
            ->get();
    }

    /**
     * Picks how many chunks to feed the LLM instead of always taking a fixed top-N.
     *
     * An absolute threshold does not work here: measured on real documents, cosine
     * similarity is compressed and document-dependent (a relevant chunk scores ~0.65
     * in one PDF and ~0.53 in another), so any fixed cut either lets nearly every
     * chunk through or rejects everything. What does carry signal is the gap to the
     * best match, so the cut is relative to it: keep the chunks that are about as
     * good as the best one, drop the tail that merely shares vocabulary.
     *
     * $floorThreshold stays as a sanity floor for the case where nothing matches.
     */
    public function searchAdaptive(
        array $queryVector,
        int $userId,
        ?int $documentId,
        float $floorThreshold,
        int $candidates = 12,
        float $relativeMargin = 0.12,
        int $maxChunks = 8,
    ): Collection {
        // SQLite (testing) has no `<=>` operator, so the similarity column cannot be
        // computed there. scopeSimilarTo already degrades to an unfiltered query on
        // other drivers; mirror that instead of emitting invalid SQL.
        if (DB::getDriverName() !== 'pgsql') {
            return $this->search($queryVector, $userId, $documentId, $floorThreshold, $maxChunks);
        }

        $vector = (new Vector($queryVector))->__toString();

        $rows = $this->baseQuery($userId, $documentId)
            ->selectRaw('1 - (embedding <=> ?) AS similarity', [$vector])
            ->similarTo($queryVector, $floorThreshold)
            ->limit($candidates)
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $cutoff = (float) $rows->first()->similarity - $relativeMargin;

        return $rows
            ->filter(fn (DocumentChunk $chunk) => (float) $chunk->similarity >= $cutoff)
            ->take($maxChunks)
            ->values();
    }

    private function baseQuery(int $userId, ?int $documentId): Builder
    {
        $query = DocumentChunk::query()
            ->select('document_chunks.*')
            ->whereHas('document', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        if ($documentId) {
            $query->where('document_id', $documentId);
        }

        return $query;
    }
}
