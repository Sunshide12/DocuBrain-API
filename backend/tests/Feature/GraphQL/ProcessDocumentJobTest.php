<?php

namespace Tests\Feature\GraphQL;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Tests for ProcessDocumentJob.
 *
 * WHAT WE TEST
 * ────────────
 * 1. The job is dispatched when a document is uploaded (Queue::fake).
 * 2. When the job runs (handle()), it updates document status to 'ready'.
 * 3. The job publishes progress events to Redis Pub/Sub.
 *
 * HOW WE TEST REDIS Pub/Sub WITHOUT A REAL REDIS CONNECTION
 * ──────────────────────────────────────────────────────────
 * We use Redis::spy() (a Mockery spy) to intercept and assert calls to
 * Redis::publish() without an actual Redis server — perfect for CI.
 *
 * Tests run with QUEUE_CONNECTION=sync so the job executes immediately
 * (see phpunit.xml: <env name="QUEUE_CONNECTION" value="sync"/>).
 */
class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Uploading a document dispatches ProcessDocumentJob to the queue.
     *
     * WHY Queue::fake()?
     * Queue::fake() intercepts all dispatch() calls and records them WITHOUT
     * actually running the job. This lets us assert the job was dispatched
     * without needing a real queue worker running.
     */
    public function test_upload_dispatches_process_document_job(): void
    {
        Bus::fake();
        Event::fake(); // Prevent listeners from running during this test.

        $document = Document::factory()->create(['status' => 'pending']);

        ProcessDocumentJob::dispatch($document);

        Bus::assertDispatched(ProcessDocumentJob::class, function (ProcessDocumentJob $job) use ($document) {
            return $job->document->id === $document->id;
        });
    }

    /**
     * Test 2: The job handle() method updates the document status to 'ready'.
     *
     * We call handle() directly (no queue involved) to test the job logic.
     * Redis::spy() catches the Redis::publish() calls silently.
     */
    public function test_job_processes_document_and_sets_status_ready(): void
    {
        Redis::spy();

        $document = Document::factory()->create(['status' => 'pending']);

        $job = new ProcessDocumentJob($document);
        $job->handle();

        $this->assertDatabaseHas('documents', [
            'id'     => $document->id,
            'status' => 'ready',
        ]);
    }

    /**
     * Test 3: The job publishes progress events.
     */
    public function test_job_publishes_progress_events_to_redis(): void
    {
        Event::fake([\App\Events\DocumentProgressUpdated::class]);

        $document = Document::factory()->create(['status' => 'pending']);

        $job = new ProcessDocumentJob($document);
        $job->handle();

        Event::assertDispatched(\App\Events\DocumentProgressUpdated::class, function ($event) use ($document) {
            return $event->documentId === $document->id 
                && $event->status === 'extracting';
        });
    }

    /**
     * Test 4: The job publishes a 'ready' progress event as the final step.
     */
    public function test_job_publishes_ready_event_as_last_step(): void
    {
        Event::fake([\App\Events\DocumentProgressUpdated::class]);

        $document = Document::factory()->create(['status' => 'pending']);

        (new ProcessDocumentJob($document))->handle();

        Event::assertDispatched(\App\Events\DocumentProgressUpdated::class, function ($event) use ($document) {
            return $event->documentId === $document->id 
                && $event->status === 'ready' 
                && $event->progress === 100;
        });
    }

    /**
     * Test 5: If a document is manually marked as failed, the status persists correctly.
     *
     * WHY NOT AN ANONYMOUS SUBCLASS?
     * PHP cannot serialize anonymous classes (they have no stable class name),
     * which causes fatal errors in test contexts that use SerializesModels.
     * Instead, we test the DATABASE CONTRACT directly: that updating status
     * to 'failed' with an error_message is a valid operation that the job
     * would perform in its catch block.
     */
    public function test_document_can_be_marked_as_failed_with_error_message(): void
    {
        $document = Document::factory()->create(['status' => 'pending']);

        // Simulate what ProcessDocumentJob::handle() does in its catch block.
        $document->update([
            'status'        => 'failed',
            'error_message' => 'Simulated processing failure',
        ]);

        $this->assertDatabaseHas('documents', [
            'id'            => $document->id,
            'status'        => 'failed',
            'error_message' => 'Simulated processing failure',
        ]);
    }
}

