<?php

namespace Tests\Feature\GraphQL;

use App\Agents\MathSolverAgent;
use App\DTOs\AgentContext;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentMathPage;
use App\Models\User;
use App\Services\Contracts\MathExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class MathSolverAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_returns_steps_response_type_when_math_pages_already_exist()
    {
        $user     = User::factory()->create();
        $document = Document::factory()->create([
            'user_id'           => $user->id,
            'status'            => 'ready',
            'math_extracted_at' => now(),
        ]);
        DocumentMathPage::factory()->create([
            'document_id' => $document->id,
            'page_number' => 1,
            'content'     => 'Solve: $x^2 + 5x + 6 = 0$',
        ]);
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
            'agent_type'  => 'math_solver',
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'Step 1: Factor the quadratic...']]]
            ], 200),
        ]);

        $agent  = new MathSolverAgent();
        $result = $agent->handle(new AgentContext(
            question:     'How do I solve the quadratic?',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        $this->assertEquals('steps', $result->responseType);
        $this->assertEquals('Step 1: Factor the quadratic...', $result->answer);
    }

    public function test_agent_triggers_extraction_and_returns_steps_when_not_yet_extracted()
    {
        $user     = User::factory()->create();
        $document = Document::factory()->create([
            'user_id'           => $user->id,
            'status'            => 'ready',
            'math_extracted_at' => null,
        ]);
        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
            'agent_type'  => 'math_solver',
        ]);

        $this->mock(MathExtractor::class, function (MockInterface $mock) {
            $mock->shouldReceive('extract')->once()->andReturn([
                1 => '$x^2 + 5x + 6 = 0$, solve by factoring.',
                2 => 'Step 1: $(x+2)(x+3) = 0$, so $x = -2$ or $x = -3$.',
            ]);
        });

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => 'Step 1: Factor...']]]
            ], 200),
        ]);

        $agent  = new MathSolverAgent();
        $result = $agent->handle(new AgentContext(
            question:     'Solve the quadratic equation on page 1',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        // Extraction should have run and stored pages
        $this->assertDatabaseHas('document_math_pages', [
            'document_id' => $document->id,
            'page_number' => 1,
        ]);
        $this->assertDatabaseHas('document_math_pages', [
            'document_id' => $document->id,
            'page_number' => 2,
        ]);
        $this->assertNotNull($document->fresh()->math_extracted_at);

        // Agent should return steps response type
        $this->assertEquals('steps', $result->responseType);
    }
}
