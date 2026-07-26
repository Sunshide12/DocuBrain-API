<?php

namespace Tests\Feature\GraphQL;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\OpenRouterClient;
use App\Services\OrchestratorRouter;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class SendMessageRagTest extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    /** Stubs the router so it always routes to document_qa without a real LLM call. */
    private function fakeRouterToDocumentQA(): void
    {
        $this->mock(OrchestratorRouter::class, function (MockInterface $mock) {
            $mock->shouldReceive('route')->andReturn([
                'tool' => 'document_qa',
                'intent' => 'ask_question',
                'topic' => null,
                'topic_type' => null,
            ]);
        });
    }

    public function test_send_message_returns_answer_with_source_chunk_ids()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        $chunk1 = DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'page_number' => 1,
        ]);
        $chunk2 = DocumentChunk::factory()->create([
            'document_id' => $document->id,
            'page_number' => 2,
        ]);

        $this->fakeRouterToDocumentQA();

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) use ($chunk1, $chunk2) {
            $mock->shouldReceive('search')->andReturn(
                new Collection([$chunk1, $chunk2])
            );
        });

        $this->mock(OpenRouterClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('chat')->andReturn('Esta es la respuesta simulada.');
        });

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '
                mutation($conversation_id: ID!, $content: String!) {
                    sendMessage(conversation_id: $conversation_id, content: $content) {
                        role
                        content
                        source_chunks {
                            id
                            page_number
                        }
                    }
                }
            ',
            'variables' => [
                'conversation_id' => $conversation->id,
                'content' => '¿Cuál es la pregunta simulada?',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'sendMessage' => [
                    'role' => 'assistant',
                    'content' => 'Esta es la respuesta simulada.',
                    'source_chunks' => [
                        ['id' => $chunk1->id, 'page_number' => 1],
                        ['id' => $chunk2->id, 'page_number' => 2],
                    ],
                ],
            ],
        ]);
    }

    public function test_send_message_returns_no_context_response_when_no_similar_chunks()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        $this->fakeRouterToDocumentQA();

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->andReturn(
                new Collection([])
            );
        });

        $response = $this->actingAs($user)->postGraphQL([
            'query' => '
                mutation($conversation_id: ID!, $content: String!) {
                    sendMessage(conversation_id: $conversation_id, content: $content) {
                        role
                        content
                        source_chunks {
                            id
                        }
                    }
                }
            ',
            'variables' => [
                'conversation_id' => $conversation->id,
                'content' => '¿Cuál es la pregunta simulada sin contexto?',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'sendMessage' => [
                    'role' => 'assistant',
                    'content' => 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                    'source_chunks' => [],
                ],
            ],
        ]);
    }

    public function test_send_message_cannot_access_other_users_chunks()
    {
        $user1 = User::factory()->create();
        $document1 = Document::factory()->create(['user_id' => $user1->id]);
        DocumentChunk::factory()->create([
            'document_id' => $document1->id,
            'content' => 'Secret info from user 1',
        ]);

        $user2 = User::factory()->create();
        $document2 = Document::factory()->create(['user_id' => $user2->id]);
        $conversation2 = Conversation::factory()->create([
            'user_id' => $user2->id,
            'document_id' => $document2->id,
        ]);

        $this->fakeRouterToDocumentQA();

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        // Real PgvectorSimilaritySearch: scopes to user2's documents, finds 0 chunks.
        $response = $this->actingAs($user2)->postGraphQL([
            'query' => '
                mutation($conversation_id: ID!, $content: String!) {
                    sendMessage(conversation_id: $conversation_id, content: $content) {
                        content
                    }
                }
            ',
            'variables' => [
                'conversation_id' => $conversation2->id,
                'content' => 'Tell me the secret',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'sendMessage' => [
                    'content' => 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                ],
            ],
        ]);
    }
}
