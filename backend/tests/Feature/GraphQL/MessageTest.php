<?php

namespace Tests\Feature\GraphQL;

use App\Agents\AgentRegistry;
use App\DTOs\AgentResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Contracts\AgentHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase, MakesGraphQLRequests;

    public function test_can_send_message()
    {
        $user         = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->mock(AgentRegistry::class, function (MockInterface $mock) {
            $fakeAgent = \Mockery::mock(AgentHandler::class);
            $fakeAgent->shouldReceive('handle')->andReturn(
                new AgentResponse(answer: 'Respuesta simulada')
            );
            $mock->shouldReceive('resolve')->with('document_qa')->andReturn($fakeAgent);
        });

        $response = $this->graphQL('
            mutation ($conversation_id: ID!, $content: String!) {
                sendMessage(conversation_id: $conversation_id, content: $content) {
                    id
                    role
                    content
                }
            }
        ', [
            'conversation_id' => $conversation->id,
            'content'         => 'Hola IA',
        ]);

        $response->assertJsonStructure([
            'data' => [
                'sendMessage' => ['id', 'role', 'content'],
            ],
        ]);

        $this->assertEquals('assistant', $response->json('data.sendMessage.role'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => 'Hola IA',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
        ]);
    }
}
