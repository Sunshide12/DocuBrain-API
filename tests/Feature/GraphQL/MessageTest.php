<?php

namespace Tests\Feature\GraphQL;

use App\Agents\OrchestratorAgent;
use App\DTOs\ToolResponse;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use MakesGraphQLRequests, RefreshDatabase;

    public function test_can_send_message()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($user);

        // SendMessage delegates the whole turn to the OrchestratorAgent; stub it so
        // the test doesn't depend on a real LLM call.
        $this->mock(OrchestratorAgent::class, function (MockInterface $mock) {
            $mock->shouldReceive('handle')->once()->andReturn(
                new ToolResponse(answer: 'Respuesta simulada', agentKey: 'document_qa')
            );
        });

        $response = $this->graphQL('
            mutation ($conversation_id: ID!, $content: String!) {
                sendMessage(conversation_id: $conversation_id, content: $content) {
                    id
                    role
                    content
                    agent_key
                }
            }
        ', [
            'conversation_id' => $conversation->id,
            'content' => 'Hola IA',
        ]);

        $response->assertJsonStructure([
            'data' => [
                'sendMessage' => ['id', 'role', 'content', 'agent_key'],
            ],
        ]);

        $this->assertEquals('assistant', $response->json('data.sendMessage.role'));
        $this->assertEquals('document_qa', $response->json('data.sendMessage.agent_key'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hola IA',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'agent_key' => 'document_qa',
        ]);
    }

    public function test_cannot_send_message_without_a_document()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'document_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->graphQL('
            mutation ($conversation_id: ID!, $content: String!) {
                sendMessage(conversation_id: $conversation_id, content: $content) {
                    id
                }
            }
        ', [
            'conversation_id' => $conversation->id,
            'content' => 'Hola IA',
        ]);

        $response->assertGraphQLErrorMessage('Seleccioná un documento para chatear.');

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
        ]);
    }
}
