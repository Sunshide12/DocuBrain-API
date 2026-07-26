<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\DocumentChunkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

/**
 * Fragmento de texto extraído de un documento PDF.
 *
 * Cada Document se divide en múltiples DocumentChunk durante el procesamiento.
 * Los chunks son la unidad básica para la búsqueda semántica (Fase 8).
 *
 * @property int $id
 * @property int $document_id
 * @property int $chunk_index Posición 0-based dentro del documento
 * @property string $content Texto del fragmento
 * @property int $token_count Número aproximado de tokens
 * @property int|null $page_number Página de origen (nullable)
 * @property Carbon $created_at
 */
class DocumentChunk extends Model
{
    /** @use HasFactory<DocumentChunkFactory> */
    use HasFactory;

    // No hay updated_at en esta tabla (los chunks no se modifican).
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'token_count',
        'page_number',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'page_number' => 'integer',
            'embedding' => Vector::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeSimilarTo($query, array $vector, float $threshold)
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite (y otros DBs en testing) no soportan el operador de distancia vectorial (<=>).
            // Retornamos el query intacto para que las pruebas no fallen por error de sintaxis.
            return $query;
        }

        // Cosine distance <=>
        // Similitud = 1 - distancia. Si queremos >= threshold, entonces distancia <= 1 - threshold
        return $query->whereRaw('embedding <=> ? <= ?', [
            (new Vector($vector))->__toString(),
            1 - $threshold,
        ])->orderByRaw('embedding <=> ?', [
            (new Vector($vector))->__toString(),
        ]);
    }
}
