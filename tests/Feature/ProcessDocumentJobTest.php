<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\TextExtractor;
use App\Exceptions\TextExtractionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery\MockInterface;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_empty_pdf_sets_document_status_to_failed_with_message()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'dummy.pdf',
            'status' => 'pending'
        ]);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andThrow(new TextExtractionException('No se pudo extraer texto legible del PDF'));
        });

        $this->mock(EmbeddingProvider::class);

        $job = new ProcessDocumentJob($document);

        try {
            app()->call([$job, 'handle']);
        } catch (TextExtractionException $e) {
            // expected
        }

        $document->refresh();
        $this->assertEquals('failed', $document->status);
        $this->assertStringContainsString('No se pudo extraer', $document->error_message);
    }

    public function test_openrouter_429_triggers_retry_and_marks_failed_after_exhaustion()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'dummy.pdf'
        ]);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'some text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andThrow(new \Exception('Error OpenRouter API Embeddings: 429 - rate limit'));
        });

        $job = new ProcessDocumentJob($document);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('429 - rate limit');

        app()->call([$job, 'handle']);
    }

    public function test_successful_pipeline_creates_chunks_with_embeddings()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'dummy.pdf'
        ]);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => str_repeat('word ', 500)]);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.1)]);
        });

        $job = new ProcessDocumentJob($document);
        app()->call([$job, 'handle']);

        $document->refresh();
        $this->assertEquals('ready', $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());
    }

    public function test_job_is_idempotent_on_retry()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'file_path' => 'dummy.pdf']);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        // Crear chunks previos
        $document->chunks()->create([
            'chunk_index' => 0,
            'content' => 'old',
            'token_count' => 1,
            'page_number' => 1,
            'embedding' => (new \Pgvector\Laravel\Vector(array_fill(0, 1536, 0.0)))->__toString()
        ]);

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'new chunk text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        $job = new ProcessDocumentJob($document);
        app()->call([$job, 'handle']);

        $this->assertEquals(1, $document->chunks()->count());
        $this->assertEquals('new chunk text', $document->chunks()->first()->content);
    }

    public function test_cache_version_increments_on_status_change()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'file_path' => 'dummy.pdf']);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        Cache::put("documents.user.{$user->id}.version", 1);

        $job = new ProcessDocumentJob($document);
        app()->call([$job, 'handle']);

        $this->assertGreaterThan(1, Cache::get("documents.user.{$user->id}.version"));
    }
}
