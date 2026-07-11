<?php

namespace Tests\Feature\GraphQL;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    /**
     * Test 1: A document can be uploaded via the GraphQL mutation.
     *
     * NEW IN FASE 4: We add Bus::fake() to assert that ProcessDocumentJob
     * is dispatched after upload. The mutation should return status 'pending'
     * immediately — the heavy processing happens in the background.
     *
     * WHY Bus::fake() and NOT Queue::fake()?
     * In Laravel 13, Queue::fake() hooks into the queue DRIVER layer.
     * Bus::fake() hooks into the command BUS layer — which is what
     * `ProcessDocumentJob::dispatch()` uses (the Dispatchable trait).
     * Bus::fake() has assertDispatched(); Queue::fake() does not.
     */
    public function test_user_can_upload_document(): void
    {
        Storage::fake('local');
        Bus::fake(); // <── Intercepts dispatch() calls without running the job.

        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $operations = [
            'query'     => 'mutation UploadDocument($file: Upload!) { uploadDocument(file: $file, title: "My Test Document") { id title original_name mime_type size status file_path } }',
            'variables' => [
                'file' => null,
            ],
        ];

        $map = [
            '0' => ['variables.file'],
        ];

        $files = [
            '0' => $file,
        ];

        $response = $this->multipartGraphQL($operations, $map, $files);

        $response->assertJsonStructure([
            'data' => [
                'uploadDocument' => [
                    'id',
                    'title',
                    'original_name',
                    'mime_type',
                    'size',
                    'status',
                    'file_path',
                ],
            ],
        ]);

        // The document should be saved with status 'pending' immediately.
        $document = Document::first();
        $this->assertNotNull($document);
        $this->assertEquals('My Test Document', $document->title);
        $this->assertEquals('document.pdf', $document->original_name);
        $this->assertEquals('pending', $document->status);
        Storage::assertExists($document->file_path);

        // NEW (Fase 4): The job must have been dispatched to the queue.
        Bus::assertDispatched(ProcessDocumentJob::class, function (ProcessDocumentJob $job) use ($document): bool {
            return $job->document->id === $document->id;
        });
    }

    /**
     * Test 2: A user can query their documents (pagination).
     *
     * UPDATED IN FASE 4: The documents query now returns DocumentPaginator
     * (our custom type) instead of the built-in @paginate connection.
     */
    public function test_user_can_query_documents(): void
    {
        Bus::fake();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Document::factory(3)->create(['user_id' => $user1->id]);
        Document::factory(2)->create(['user_id' => $user2->id]);

        $token = $user1->createToken('test-token')->plainTextToken;
        $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query {
                documents {
                    data {
                        id
                        title
                    }
                    paginatorInfo {
                        total
                    }
                }
            }
        '
        );

        $response->assertJsonPath('data.documents.paginatorInfo.total', 3);
    }

    /**
     * Test 3: The documents query result is cached.
     *
     * After the first request, the result is stored in cache.
     * A second identical request with a new document added should
     * still serve the OLD cached total (stale reads confirm caching).
     */
    public function test_documents_query_result_is_cached(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        Document::factory(2)->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $query = '
            query {
                documents {
                    data { id }
                    paginatorInfo { total }
                }
            }
        ';

        // First request — should populate cache.
        $this->graphQL($query)->assertJsonPath('data.documents.paginatorInfo.total', 2);

        // Verify the versioned cache key was actually populated (version defaults to 1).
        $cacheKey = "documents.user.{$user->id}.v1.page.1.per.10";
        $this->assertTrue(Cache::has($cacheKey), "Expected versioned cache key '{$cacheKey}' to exist after first query.");

        // Second request — should still return the same total (served from cache).
        // To prove cache is being used, add a new document AFTER caching.
        Document::factory()->create(['user_id' => $user->id]);

        $secondResponse = $this->graphQL($query);
        // Still 2 because the cache has not been invalidated yet.
        $secondResponse->assertJsonPath('data.documents.paginatorInfo.total', 2);
    }

    /**
     * Test 4: Uploading a document invalidates the documents cache.
     *
     * After an upload, the cache entry created by the documents query
     * must be deleted so the next query hits the DB and returns fresh data.
     */
    public function test_upload_invalidates_documents_cache(): void
    {
        Storage::fake('local');
        Bus::fake();

        $user = User::factory()->create();
        Document::factory(2)->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        // 1. Seed the cache by querying first.
        $this->graphQL('query { documents { paginatorInfo { total } } }');

        // The versioned key (v1) should exist after the first query.
        $versionedKey = "documents.user.{$user->id}.v1.page.1.per.10";
        $versionKey   = "documents.user.{$user->id}.version";
        $this->assertTrue(Cache::has($versionedKey), 'Versioned cache key should exist before upload.');

        // 2. Upload a new document — fires DocumentUploaded → InvalidateDocumentsCache.
        $file = UploadedFile::fake()->create('new.pdf', 512, 'application/pdf');
        $this->multipartGraphQL(
            ['query' => 'mutation UploadDocument($file: Upload!) { uploadDocument(file: $file) { id } }', 'variables' => ['file' => null]],
            ['0' => ['variables.file']],
            ['0' => $file],
        );

        // 3. The version counter should now be 2 (incremented by the listener).
        // All subsequent queries will use key v2.* — a cache miss, so fresh data is fetched.
        $this->assertEquals(2, (int) Cache::get($versionKey), 'Version counter should be 2 after upload (invalidated).');
    }
}
