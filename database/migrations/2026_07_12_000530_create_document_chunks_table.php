<?php

use App\Models\Document;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — SQL Avanzado
 *
 * Crea la tabla document_chunks para almacenar los fragmentos de texto
 * extraídos de cada PDF. Cada chunk tiene un índice secuencial dentro del
 * documento, el contenido del texto, el recuento de tokens y la página de origen.
 *
 * NOTA: El campo `embedding vector(1536)` NO se incluye aquí.
 * Se agregará en Fase 8 (pgvector / RAG) mediante una migración separada
 * para mantener el scope de cada fase limpio.
 *
 * ÍNDICES:
 *   - document_id (FK + índice automático): soporta la query
 *       SELECT * FROM document_chunks WHERE document_id = ?
 *   - (document_id, chunk_index): índice compuesto para búsquedas ordenadas
 *       SELECT * FROM document_chunks WHERE document_id = ? ORDER BY chunk_index
 *     Esto convierte un Seq Scan + Sort en un Index Scan directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Document::class)
                ->constrained()
                ->cascadeOnDelete();

            // Posición del chunk dentro del documento (0-based).
            $table->unsignedInteger('chunk_index');

            // Contenido textual del chunk.
            $table->text('content');

            // Número aproximado de tokens (calculado en procesamiento).
            $table->unsignedSmallInteger('token_count')->default(0);

            // Página de origen dentro del PDF (nullable — no siempre disponible).
            $table->unsignedSmallInteger('page_number')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Índice compuesto: optimiza ORDER BY chunk_index dentro de un documento.
            // Sin este índice: Seq Scan + Sort (O(n log n))
            // Con este índice: Index Scan directo (O(log n))
            $table->index(['document_id', 'chunk_index'], 'idx_chunks_document_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
