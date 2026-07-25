<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Estructura de datos de prueba:
     *   - 3 usuarios
     *   - 5 documentos por usuario (15 documentos totales)
     *   - 10 chunks por documento (150 chunks totales)
     *
     * Este volumen es suficiente para:
     *   1. Ver diferencia entre Seq Scan e Index Scan con EXPLAIN ANALYZE
     *   2. Demostrar el problema N+1 (15 queries extra sin eager loading)
     *   3. Tener datos realistas para queries de paginación
     */
    public function run(): void
    {
        User::factory(3)->create()->each(function (User $user) {
            Document::factory(5)->create(['user_id' => $user->id])->each(function (Document $document) {
                // Generamos 10 chunks con chunk_index secuencial (0-9)
                // para que el índice compuesto (document_id, chunk_index) sea útil.
                DocumentChunk::factory(10)->sequence(
                    fn ($seq) => ['chunk_index' => $seq->index, 'document_id' => $document->id]
                )->create(['document_id' => $document->id]);
            });
        });

        // Usuario fijo para acceso manual / GraphQL Playground
        $demoUser = User::factory()->create([
            'name'  => 'Demo User',
            'email' => 'demo@docubrain.dev',
        ]);

        Document::factory(5)->create(['user_id' => $demoUser->id])->each(function (Document $document) {
            DocumentChunk::factory(10)->sequence(
                fn ($seq) => ['chunk_index' => $seq->index, 'document_id' => $document->id]
            )->create(['document_id' => $document->id]);
        });
    }
}
