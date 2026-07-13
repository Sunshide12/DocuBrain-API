<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fragmento de texto extraído de un documento PDF.
 *
 * Cada Document se divide en múltiples DocumentChunk durante el procesamiento.
 * Los chunks son la unidad básica para la búsqueda semántica (Fase 8).
 *
 * @property int         $id
 * @property int         $document_id
 * @property int         $chunk_index     Posición 0-based dentro del documento
 * @property string      $content         Texto del fragmento
 * @property int         $token_count     Número aproximado de tokens
 * @property int|null    $page_number     Página de origen (nullable)
 * @property \Carbon\Carbon $created_at
 */
class DocumentChunk extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentChunkFactory> */
    use HasFactory;

    // No hay updated_at en esta tabla (los chunks no se modifican).
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'token_count',
        'page_number',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'page_number' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
