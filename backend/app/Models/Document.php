<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'original_name',
        'file_path',
        'mime_type',
        'size',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Los fragmentos de texto extraídos de este documento.
     * Ordenados por chunk_index para respetar el orden original del PDF.
     *
     * Fase 3: Esta relación es la base para demostrar el problema N+1.
     * Fase 8: Se usará para la búsqueda semántica por similitud de embeddings.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }
}
