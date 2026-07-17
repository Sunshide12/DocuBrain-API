<?php

namespace Tests\Feature\GraphQL;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase, MakesGraphQLRequests;

    public function test_can_send_message()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->mock(\App\Services\Contracts\EmbeddingProvider::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        });

        $this->mock(\App\Services\Contracts\AnswerGenerator::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('generate')->andReturn(new \App\DTOs\AnswerResult(
                answer: 'Respuesta simulada',
                sourceChunks: []
            ));
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
            'content' => 'Hola IA'
        ]);

        // Should return the assistant message
        $response->assertJsonStructure([
            'data' => [
                'sendMessage' => [
                    'id',
                    'role',
                    'content'
                ]
            ]
        ]);

        $this->assertEquals('assistant', $response->json('data.sendMessage.role'));
        
        // Assert user message is in database
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hola IA',
        ]);
        
        // Assert assistant message is in database
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);
    }
}
