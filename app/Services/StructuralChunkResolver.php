<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentChunk;

/**
 * Resolves "structural" references — explicit numbered items ("problema 2.1.1",
 * "ejercicio 3") or ordinal positions ("el primer ejercicio", "el último punto") —
 * against document order (chunk_index) instead of pure vector similarity.
 *
 * Cosine similarity has no notion of "first" or "second": the chunk whose
 * embedding sits closest to the query can be any numbered item in the document,
 * regardless of where it actually appears. This resolver reconstructs the
 * document in order and locates numbered items directly in the text.
 */
class StructuralChunkResolver
{
    /** Ordinal words mapped to a 1-based position from the start of the document. */
    private const ORDINALS = [
        'primer' => 1, 'primero' => 1, 'primera' => 1, 'first' => 1,
        'segundo' => 2, 'segunda' => 2, 'second' => 2,
        'tercer' => 3, 'tercero' => 3, 'tercera' => 3, 'third' => 3,
        'cuarto' => 4, 'cuarta' => 4, 'fourth' => 4,
        'quinto' => 5, 'quinta' => 5, 'fifth' => 5,
        'sexto' => 6, 'sexta' => 6, 'sixth' => 6,
    ];

    /** Words that mean "from the end" rather than "from the start". */
    private const LAST_WORDS = ['último', 'ultimo', 'última', 'ultima', 'last'];

    /** Heading markers that introduce a new numbered item (Spanish + English). */
    private const MARKER_PATTERN = '/\b(problema|ejercicio|punto|secci[oó]n|cap[ií]tulo|apartado|problem|exercise|section|chapter|item)\s+([ivxlcdm]+|\d+(?:\.\d+)*)/iu';

    /** Hard cap so a pathological document can't blow up memory/token usage; beyond this we bail out to vector search. */
    private const MAX_CHUNKS_TO_SCAN = 500;

    /** Max characters to return around the resolved item, when no next marker bounds it. */
    private const MAX_ITEM_LENGTH = 4000;

    /**
     * @return string|null The resolved item's raw text, or null when the question
     *                     carries no resolvable structural/ordinal reference —
     *                     callers should fall back to vector search in that case.
     */
    public function resolve(string $question, string $topic, int $documentId): ?string
    {
        $target = $this->resolveTarget($question, $topic);

        if ($target === null) {
            return null;
        }

        $chunks = DocumentChunk::query()
            ->where('document_id', $documentId)
            ->orderBy('chunk_index')
            ->limit(self::MAX_CHUNKS_TO_SCAN)
            ->get(['content']);

        if ($chunks->isEmpty()) {
            return null;
        }

        return $this->extractItem($chunks->pluck('content')->implode("\n"), $target);
    }

    /**
     * @return array{0: 'position'|'last'|'number', 1: int|string}|null
     */
    private function resolveTarget(string $question, string $topic): ?array
    {
        // Explicit number mentioned by the user (e.g. "problema 2.1.1", "ejercicio 3")
        // takes priority — it's an exact reference, not a relative position.
        if (preg_match('/(\d+(?:\.\d+)*)/', $topic, $m)) {
            return ['number', $m[1]];
        }

        $normalized = ' '.preg_replace(
            '/[^\p{L}\p{N}\s]/u',
            ' ',
            mb_strtolower($question)
        ).' ';
        $normalized = ' '.trim(preg_replace('/\s+/', ' ', $normalized)).' ';

        foreach (self::LAST_WORDS as $word) {
            if (str_contains($normalized, " {$word} ")) {
                return ['last', 0];
            }
        }

        foreach (self::ORDINALS as $word => $position) {
            if (str_contains($normalized, " {$word} ")) {
                return ['position', $position];
            }
        }

        return null;
    }

    /**
     * @param  array{0: 'position'|'last'|'number', 1: int|string}  $target
     */
    private function extractItem(string $fullText, array $target): ?string
    {
        preg_match_all(self::MARKER_PATTERN, $fullText, $matches, PREG_OFFSET_CAPTURE);

        $offsets = $matches[0] ?? [];
        $numbers = $matches[2] ?? [];

        if (empty($offsets)) {
            return null;
        }

        [$type, $value] = $target;

        $index = match ($type) {
            'last' => count($offsets) - 1,
            'position' => $value - 1,
            'number' => $this->findByNumber($numbers, (string) $value),
        };

        if ($index === null || ! isset($offsets[$index])) {
            return null;
        }

        $start = $offsets[$index][1];
        $end = $offsets[$index + 1][1] ?? min($start + self::MAX_ITEM_LENGTH, mb_strlen($fullText));

        return trim(mb_substr($fullText, $start, $end - $start));
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $numbers  Offset-captured number group from MARKER_PATTERN.
     */
    private function findByNumber(array $numbers, string $needle): ?int
    {
        foreach ($numbers as $i => $capture) {
            if ($capture[0] === $needle) {
                return $i;
            }
        }

        return null;
    }
}
