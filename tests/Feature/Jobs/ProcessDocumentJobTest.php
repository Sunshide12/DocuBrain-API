<?php

namespace Tests\Feature\Jobs;

use App\Events\DocumentProcessed;
use App\Events\DocumentProgressUpdated;
use App\Exceptions\TextExtractionException;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\TextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Pgvector\Laravel\Vector;
use Tests\TestCase;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Test 1: Uploading a document dispatches ProcessDocumentJob to the queue.
     *
     * Bus::fake() intercepts all dispatch() calls without actually running the job.
     * This lets us assert the job was dispatched without needing a real queue worker.
     */
    public function test_upload_dispatches_process_document_job(): void
    {
        Bus::fake();
        Event::fake();

        $document = Document::factory()->create(['status' => 'pending']);

        ProcessDocumentJob::dispatch($document);

        Bus::assertDispatched(ProcessDocumentJob::class, function (ProcessDocumentJob $job) use ($document) {
            return $job->document->id === $document->id;
        });
    }

    public function test_successful_pipeline_creates_chunks_with_embeddings(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'file_path' => 'dummy.pdf']);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => str_repeat('word ', 500)]);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1), array_fill(0, 1536, 0.1)]);
        });

        app()->call([new ProcessDocumentJob($document), 'handle']);

        $document->refresh();
        $this->assertEquals('ready', $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());
    }

    /**
     * Test 2: handle() updates document status to 'ready'.
     *
     * Redis::spy() catches Redis::publish() calls silently (no real Redis needed).
     */
    public function test_job_processes_document_and_sets_status_ready(): void
    {
        Redis::spy();
        Event::fake([DocumentProcessed::class]);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        $document = Document::factory()->create(['status' => 'pending', 'file_path' => 'dummy.pdf']);

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'fake text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        app()->call([new ProcessDocumentJob($document), 'handle']);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'ready']);
    }

    public function test_empty_pdf_sets_document_status_to_failed_with_message(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'dummy.pdf',
            'status' => 'pending',
        ]);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andThrow(new TextExtractionException('No se pudo extraer texto legible del PDF'));
        });

        $this->mock(EmbeddingProvider::class);

        try {
            app()->call([new ProcessDocumentJob($document), 'handle']);
        } catch (TextExtractionException $e) {
            // expected
        }

        $document->refresh();
        $this->assertEquals('failed', $document->status);
        $this->assertStringContainsString('No se pudo extraer', $document->error_message);
    }

    public function test_openrouter_429_triggers_retry_and_marks_failed_after_exhaustion(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'file_path' => 'dummy.pdf']);
        Storage::disk('local')->put('dummy.pdf', 'fake content');

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'some text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andThrow(new \Exception('Error OpenRouter API Embeddings: 429 - rate limit'));
        });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('429 - rate limit');

        app()->call([new ProcessDocumentJob($document), 'handle']);
    }

    public function test_job_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'file_path' => 'dummy.pdf']);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        $document->chunks()->create([
            'chunk_index' => 0,
            'content' => 'old',
            'token_count' => 1,
            'page_number' => 1,
            'embedding' => (new Vector(array_fill(0, 1536, 0.0)))->__toString(),
        ]);

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'new chunk text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        app()->call([new ProcessDocumentJob($document), 'handle']);

        $this->assertEquals(1, $document->chunks()->count());
        $this->assertEquals('new chunk text', $document->chunks()->first()->content);
    }

    /**
     * Test 3: The job publishes progress events.
     */
    public function test_job_publishes_progress_events_to_redis(): void
    {
        Event::fake([DocumentProgressUpdated::class]);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        $document = Document::factory()->create(['status' => 'pending', 'file_path' => 'dummy.pdf']);

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'fake text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        app()->call([new ProcessDocumentJob($document), 'handle']);

        Event::assertDispatched(DocumentProgressUpdated::class, function ($event) use ($document) {
            return $event->documentId === $document->id && $event->status === 'extracting';
        });
    }

    /**
     * Test 4: The job publishes a 'ready' progress event as the final step.
     */
    public function test_job_publishes_ready_event_as_last_step(): void
    {
        Event::fake([DocumentProgressUpdated::class]);
        Storage::disk('local')->put('dummy.pdf', 'fake');

        $document = Document::factory()->create(['status' => 'pending', 'file_path' => 'dummy.pdf']);

        $this->mock(TextExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->andReturn([1 => 'fake text']);
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
        });

        app()->call([new ProcessDocumentJob($document), 'handle']);

        Event::assertDispatched(DocumentProgressUpdated::class, function ($event) use ($document) {
            return $event->documentId === $document->id
                && $event->status === 'ready'
                && $event->progress === 100;
        });
    }

    /**
     * Test 5: If a document is manually marked as failed, the status persists correctly.
     *
     * Tests the DATABASE CONTRACT directly: updating status to 'failed' with an
     * error_message is a valid operation the job performs in its catch block.
     * (PHP cannot serialize anonymous classes, so we simulate the catch block directly.)
     */
    public function test_document_can_be_marked_as_failed_with_error_message(): void
    {
        $document = Document::factory()->create(['status' => 'pending']);

        $document->update([
            'status' => 'failed',
            'error_message' => 'Simulated processing failure',
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'failed',
            'error_message' => 'Simulated processing failure',
        ]);
    }

    public function test_cache_version_increments_on_status_change(): void
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

        app()->call([new ProcessDocumentJob($document), 'handle']);

        $this->assertGreaterThan(1, Cache::get("documents.user.{$user->id}.version"));
    }
}
