<?php

namespace Tests\Feature\GraphQL;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use MakesGraphQLRequests, RefreshDatabase;

    public function test_can_create_conversation_with_document()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'original_name' => 'test.pdf']);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            mutation ($document_id: ID!) {
                createConversation(document_id: $document_id) {
                    id
                    title
                    document {
                        id
                    }
                }
            }
        ', ['document_id' => $document->id]);

        $response->assertJsonStructure([
            'data' => [
                'createConversation' => [
                    'id',
                    'title',
                    'document' => ['id'],
                ],
            ],
        ]);

        $this->assertEquals('Chat sobre: test.pdf', $response->json('data.createConversation.title'));
    }

    public function test_can_create_global_conversation()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            mutation {
                createConversation {
                    id
                    title
                    document {
                        id
                    }
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'createConversation' => [
                    'id',
                    'title',
                    'document',
                ],
            ],
        ]);

        $this->assertEquals('Búsqueda Global', $response->json('data.createConversation.title'));
        $this->assertNull($response->json('data.createConversation.document'));
    }

    public function test_creating_conversation_twice_for_same_document_reuses_existing_one()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'original_name' => 'test.pdf']);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $query = '
            mutation ($document_id: ID!) {
                createConversation(document_id: $document_id) {
                    id
                }
            }
        ';

        $first = $this->graphQL($query, ['document_id' => $document->id]);
        $second = $this->graphQL($query, ['document_id' => $document->id]);

        $this->assertEquals(
            $first->json('data.createConversation.id'),
            $second->json('data.createConversation.id')
        );
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_creating_global_conversation_twice_reuses_existing_one()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $query = '
            mutation {
                createConversation {
                    id
                }
            }
        ';

        $first = $this->graphQL($query);
        $second = $this->graphQL($query);

        $this->assertEquals(
            $first->json('data.createConversation.id'),
            $second->json('data.createConversation.id')
        );
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_cannot_access_other_users_conversation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::factory()->create(['user_id' => $user1->id]);

        $token = $user2->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            query ($id: ID!) {
                conversation(id: $id) {
                    id
                }
            }
        ', ['id' => $conversation->id]);

        $this->assertNull($response->json('data.conversation'));
    }

    public function test_can_delete_conversation()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            mutation ($id: ID!) {
                deleteConversation(id: $id)
            }
        ', ['id' => $conversation->id]);

        $response->assertJson([
            'data' => [
                'deleteConversation' => true,
            ],
        ]);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }
}
