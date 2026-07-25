<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — SQL Avanzado: Índices en la tabla documents
 *
 * La tabla documents ya tiene el índice en `user_id` (FK automática de Laravel).
 * Agregamos índices adicionales para optimizar las queries más frecuentes:
 *
 * 1. idx_documents_user_created — índice compuesto (user_id, created_at DESC)
 *    Query que optimiza:
 *      SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 10
 *    Sin índice: Index Scan en user_id + Sort separado
 *    Con índice: Index Scan en (user_id, created_at) — elimina el Sort
 *
 * 2. idx_documents_status — índice simple en status
 *    Query que optimiza:
 *      SELECT * FROM documents WHERE status = 'processing'
 *    Sin índice: Seq Scan completo
 *    Con índice: Index Scan (útil cuando pocos documentos tienen ese status)
 *
 * NOTA: En tablas pequeñas (<1000 filas) PostgreSQL puede ignorar estos índices
 * y hacer Seq Scan de todas formas (es más rápido). El optimizador decide.
 * Con EXPLAIN ANALYZE puedes ver cuándo se activan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Índice compuesto para la query de listado paginado por usuario.
            // Convierte la combinación WHERE user_id + ORDER BY created_at en un solo Index Scan.
            $table->index(['user_id', 'created_at'], 'idx_documents_user_created');

            // Índice para filtrar por estado de procesamiento.
            $table->index('status', 'idx_documents_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_user_created');
            $table->dropIndex('idx_documents_status');
        });
    }
};
