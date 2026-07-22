<?php

namespace Tests\Feature\GraphQL;

use App\Agents\AgentRegistry;
use App\DTOs\AgentResponse;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Contracts\AgentHandler;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class SendMessageRagTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    /**
     * Helper: rebuild DocumentQAAgent from the container (picking up any mocked dependencies)
     * and install it into a fresh AgentRegistry that replaces the singleton.
     */
    private function installFreshDocQAAgent(): void
    {
        $agent    = $this->app->make(\App\Agents\DocumentQAAgent::class);
        $registry = new AgentRegistry();
        $registry->register($agent);
        $this->app->instance(AgentRegistry::class, $registry);
    }

    public function test_send_message_returns_answer_with_source_chunk_ids()
    {
        $user         = User::factory()->create();
        $document     = Document::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
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

        $this->mock(AgentRegistry::class, function (MockInterface $mock) use ($chunk1, $chunk2) {
            $fakeAgent = \Mockery::mock(AgentHandler::class);
            $fakeAgent->shouldReceive('handle')->andReturn(new AgentResponse(
                answer: 'Esta es la respuesta simulada.',
                sourceChunks: [
                    ['id' => $chunk1->id, 'page_number' => $chunk1->page_number],
                    ['id' => $chunk2->id, 'page_number' => $chunk2->page_number],
                ],
            ));
            $mock->shouldReceive('resolve')->with('document_qa')->andReturn($fakeAgent);
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
                'content'         => '¿Cuál es la pregunta simulada?',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'sendMessage' => [
                    'role'    => 'assistant',
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
        $user         = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->andReturn(
                new \Illuminate\Database\Eloquent\Collection([])
            );
        });

        $this->installFreshDocQAAgent();

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
                'content'         => '¿Cuál es la pregunta simulada sin contexto?',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'sendMessage' => [
                    'role'         => 'assistant',
                    'content'      => 'No tengo información suficiente para responder esa pregunta con los documentos disponibles.',
                    'source_chunks' => [],
                ],
            ],
        ]);
    }

    public function test_send_message_cannot_access_other_users_chunks()
    {
        $user1     = User::factory()->create();
        $document1 = Document::factory()->create(['user_id' => $user1->id]);
        DocumentChunk::factory()->create([
            'document_id' => $document1->id,
            'content'     => 'Secret info from user 1',
        ]);

        $user2         = User::factory()->create();
        $conversation2 = Conversation::factory()->create([
            'user_id'     => $user2->id,
            'document_id' => null,
        ]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        // Real PgvectorSimilaritySearch: scopes to user2's documents, finds 0 chunks.
        $this->installFreshDocQAAgent();

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
                'content'         => 'Tell me the secret',
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
