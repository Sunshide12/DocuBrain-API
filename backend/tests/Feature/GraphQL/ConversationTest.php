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
    use RefreshDatabase, MakesGraphQLRequests;

    public function test_can_create_conversation_with_document()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'original_name' => 'test.pdf']);

        $this->actingAs($user);

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
                    'document' => ['id']
                ]
            ]
        ]);

        $this->assertEquals("Chat sobre: test.pdf", $response->json('data.createConversation.title'));
    }

    public function test_can_create_global_conversation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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
                    'document'
                ]
            ]
        ]);

        $this->assertEquals("Búsqueda Global", $response->json('data.createConversation.title'));
        $this->assertNull($response->json('data.createConversation.document'));
    }

    public function test_cannot_access_other_users_conversation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::factory()->create(['user_id' => $user1->id]);

        $this->actingAs($user2);

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

        $this->actingAs($user);

        $response = $this->graphQL('
            mutation ($id: ID!) {
                deleteConversation(id: $id)
            }
        ', ['id' => $conversation->id]);

        $response->assertJson([
            'data' => [
                'deleteConversation' => true
            ]
        ]);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }
}
