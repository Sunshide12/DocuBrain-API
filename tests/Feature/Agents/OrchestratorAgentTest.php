<?php

namespace Tests\Feature\Agents;

use App\Agents\OrchestratorAgent;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class OrchestratorAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversation(): array
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return [$user, $document, $conversation];
    }

    public function test_router_selects_the_math_solver_tool_with_a_single_routing_call(): void
    {
        [$user, , $conversation] = $this->makeConversation();

        Http::fake([
            '*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => json_encode([
                    'tool' => 'math_solver',
                    'intent' => 'solve_math',
                    'topic' => null,
                    'topic_type' => null,
                ])]]]])
                ->push(['choices' => [['message' => ['content' => 'Paso 1: ...']]]]),
        ]);

        $orchestrator = $this->app->make(OrchestratorAgent::class);
        $result = $orchestrator->handle('resolvé x^2 = 4', $conversation, $user->id);

        $this->assertEquals('math_solver', $result->agentKey);
        $this->assertEquals('steps', $result->responseType);
        $this->assertEquals('Paso 1: ...', $result->answer);
        Http::assertSentCount(2);
    }

    public function test_ambiguous_message_falls_back_to_clarification_without_calling_a_tool_llm(): void
    {
        [$user, , $conversation] = $this->makeConversation();

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'tool' => 'not_a_real_tool',
                'intent' => 'chat',
                'topic' => null,
                'topic_type' => null,
            ])]]]]),
        ]);

        $orchestrator = $this->app->make(OrchestratorAgent::class);
        $result = $orchestrator->handle('asdkjasd', $conversation, $user->id);

        $this->assertEquals('clarification', $result->agentKey);
        Http::assertSentCount(1);
    }

    public function test_exception_inside_a_tool_is_caught_and_returns_a_friendly_message(): void
    {
        [$user, , $conversation] = $this->makeConversation();

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'tool' => 'document_qa',
                'intent' => 'ask_question',
                'topic' => null,
                'topic_type' => null,
            ])]]]]),
        ]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andThrow(new \RuntimeException('embedding service down'));
        });

        $orchestrator = $this->app->make(OrchestratorAgent::class);
        $result = $orchestrator->handle('¿qué dice el documento?', $conversation, $user->id);

        $this->assertStringNotContainsString('embedding service down', $result->answer);
        $this->assertStringContainsString('problema', $result->answer);
    }

    public function test_trivial_greeting_skips_the_router_llm_call_entirely(): void
    {
        [$user, , $conversation] = $this->makeConversation();

        Http::fake();

        $orchestrator = $this->app->make(OrchestratorAgent::class);
        $result = $orchestrator->handle('hola!', $conversation, $user->id);

        $this->assertEquals('greetings', $result->agentKey);
        Http::assertNothingSent();
    }

    public function test_message_that_merely_contains_a_greeting_word_still_uses_the_real_router(): void
    {
        [$user, , $conversation] = $this->makeConversation();

        Http::fake([
            '*' => Http::response(['choices' => [['message' => ['content' => json_encode([
                'tool' => 'document_qa',
                'intent' => 'ask_question',
                'topic' => null,
                'topic_type' => null,
            ])]]]]),
        ]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn([0.1, 0.2]);
        });
        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->andReturn(new Collection);
        });

        $orchestrator = $this->app->make(OrchestratorAgent::class);
        $result = $orchestrator->handle('hola, tengo una pregunta sobre el capítulo 3', $conversation, $user->id);

        $this->assertEquals('document_qa', $result->agentKey);
        Http::assertSentCount(1);
    }
}
