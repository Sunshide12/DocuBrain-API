<?php

namespace Tests\Feature\GraphQL;

use App\Agents\AgentRegistry;
use App\Agents\QuizGeneratorAgent;
use App\DTOs\AgentContext;
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

class QuizGeneratorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_returns_quiz_response_type_with_questions_in_metadata(): void
    {
        $user     = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'status' => 'ready']);
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
            'agent_type'  => 'quiz_generator',
        ]);

        // Mock EmbeddingProvider so no real API call is made
        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn(array_fill(0, 1536, 0.1));
        });

        // Mock PgvectorSimilaritySearch to return a fake chunk
        $fakeChunk = new \App\Models\DocumentChunk([
            'id'          => 1,
            'content'     => 'Photosynthesis converts light energy into chemical energy stored in glucose.',
            'page_number' => 2,
        ]);
        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) use ($fakeChunk) {
            $mock->shouldReceive('search')->once()->andReturn(new Collection([$fakeChunk]));
        });

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            [
                                'type'           => 'multiple_choice',
                                'question'       => 'What does photosynthesis convert?',
                                'options'        => ['A) Heat', 'B) Light energy', 'C) Sound', 'D) Kinetic energy'],
                                'correct_answer' => 'B',
                                'explanation'    => 'The text states light energy is converted to chemical energy.',
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $agent  = $this->app->make(QuizGeneratorAgent::class);
        $result = $agent->handle(new AgentContext(
            question:     'Make a multiple-choice quiz about photosynthesis',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertNotEmpty($result->answer);
        $this->assertArrayHasKey('questions', $result->metadata);
        $this->assertCount(1, $result->metadata['questions']);
        $this->assertEquals('multiple_choice', $result->metadata['questions'][0]['type']);
    }

    public function test_agent_returns_text_when_no_relevant_chunks_found(): void
    {
        $user     = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'status' => 'ready']);
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
            'agent_type'  => 'quiz_generator',
        ]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn(array_fill(0, 1536, 0.0));
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->once()->andReturn(new Collection([]));
        });

        $agent  = $this->app->make(QuizGeneratorAgent::class);
        $result = $agent->handle(new AgentContext(
            question:     'Quiz me on quantum physics',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        $this->assertEquals('text', $result->responseType);
        $this->assertStringContainsStringIgnoringCase('could not find', $result->answer);
    }

    private function installFreshQuizAgent(): void
    {
        $agent    = $this->app->make(QuizGeneratorAgent::class);
        $registry = new AgentRegistry();
        $registry->register($agent);
        $this->app->instance(AgentRegistry::class, $registry);
    }
}
