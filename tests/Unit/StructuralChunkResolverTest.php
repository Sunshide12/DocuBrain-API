<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\StructuralChunkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuralChunkResolverTest extends TestCase
{
    use RefreshDatabase;

    private StructuralChunkResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new StructuralChunkResolver;
    }

    /** Seeds a document whose chunks reproduce the reported bug: several numbered
     *  exercises spread across chunks, in document order. */
    private function seedExerciseDocument(): Document
    {
        $document = Document::factory()->create();

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Problema 2.1.1 Hallar la solucion general de las siguientes ecuaciones: y\'\' + 3y\' - 10y = 6e^4x.',
        ]);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => 'Problema 2.1.2 Hallar las soluciones de los problemas de valores iniciales siguientes.',
        ]);

        DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'chunk_index' => 2,
            'content' => 'Problema 2.2.5 Sobre una superficie horizontal lisa se sujeta una masa de 2 kg mediante un resorte.',
        ]);

        return $document;
    }

    public function test_resolves_ordinal_first_reference_to_the_actual_first_item(): void
    {
        $document = $this->seedExerciseDocument();

        $result = $this->resolver->resolve(
            question: 'responde el primer ejercicio',
            topic: 'el primer ejercicio',
            documentId: $document->id,
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString('Problema 2.1.1', $result);
        $this->assertStringNotContainsString('Problema 2.2.5', $result);
    }

    public function test_resolves_ordinal_last_reference_to_the_final_item(): void
    {
        $document = $this->seedExerciseDocument();

        $result = $this->resolver->resolve(
            question: 'dame el ultimo ejercicio',
            topic: 'el ultimo ejercicio',
            documentId: $document->id,
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString('Problema 2.2.5', $result);
    }

    public function test_resolves_explicit_number_regardless_of_wording(): void
    {
        $document = $this->seedExerciseDocument();

        $result = $this->resolver->resolve(
            question: 'explicame el problema 2.2.5 por favor',
            topic: 'problema 2.2.5',
            documentId: $document->id,
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString('Problema 2.2.5', $result);
        $this->assertStringNotContainsString('Problema 2.1.1', $result);
    }

    public function test_returns_null_when_question_has_no_structural_reference(): void
    {
        $document = $this->seedExerciseDocument();

        $result = $this->resolver->resolve(
            question: 'explicame que es una ecuacion diferencial',
            topic: 'ecuacion diferencial',
            documentId: $document->id,
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_document_has_no_chunks(): void
    {
        $document = Document::factory()->create();

        $result = $this->resolver->resolve(
            question: 'responde el primer ejercicio',
            topic: 'el primer ejercicio',
            documentId: $document->id,
        );

        $this->assertNull($result);
    }
}
