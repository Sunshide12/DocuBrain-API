<?php

namespace Tests\Feature\GraphQL;

use App\Agents\QuizGeneratorAgent;
use App\DTOs\AgentContext;
use App\DTOs\ClassifiedIntent;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
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

    private function fakeQuestionsResponse(array $questions): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($questions)],
                ]],
            ], 200),
        ]);
    }

    private function makeConversation(int $chunkCount = 0, int $tokensPerChunk = 200): array
    {
        $user     = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id, 'status' => 'ready']);

        for ($i = 0; $i < $chunkCount; $i++) {
            DocumentChunk::factory()->create([
                'document_id' => $document->id,
                'chunk_index' => $i,
                'page_number' => $i + 1,
                'token_count' => $tokensPerChunk,
            ]);
        }

        $conversation = Conversation::factory()->create([
            'user_id'     => $user->id,
            'document_id' => $document->id,
            'agent_type'  => 'quiz_generator',
        ]);

        return [$user, $document, $conversation];
    }

    public function test_agent_falls_back_to_uniform_sampling_when_no_topic_matches(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 10, tokensPerChunk: 200);

        // Topic extraction now happens upstream in IntentClassifier before the agent
        // runs; a null topic on the context means "generic quiz request", and the
        // agent should sample uniformly without ever touching embeddings/search.
        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $this->fakeQuestionsResponse([
            ['type' => 'multiple_choice', 'question' => 'Q1', 'options' => ['A) 1', 'B) 2', 'C) 3', 'D) 4'], 'correct_answer' => 'A', 'explanation' => 'x'],
            ['type' => 'flashcard', 'question' => 'Q2', 'correct_answer' => 'A2', 'explanation' => 'x'],
            ['type' => 'true_false', 'question' => 'Q3', 'correct_answer' => 'True', 'explanation' => 'x'],
            ['type' => 'open_ended', 'question' => 'Q4', 'correct_answer' => 'Model answer', 'explanation' => 'Rubric'],
            ['type' => 'flashcard', 'question' => 'Q5', 'correct_answer' => 'A5', 'explanation' => 'x'],
        ]);

        $agent  = $this->app->make(QuizGeneratorAgent::class);
        $result = $agent->handle(new AgentContext(
            question:     'Quiz me',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertCount(5, $result->metadata['questions']);
        // No overflow: default 5 questions vs capacity of 6 (2000 tokens / 300)
        $this->assertEquals(0, $conversation->messages()->where('response_type', 'text')->count());
    }

    public function test_agent_uses_similarity_chunks_when_topic_is_relevant(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 20, tokensPerChunk: 200);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn(array_fill(0, 1536, 0.1));
        });

        $topicChunks = new Collection([
            new DocumentChunk(['id' => 101, 'content' => 'Photosynthesis converts light energy.', 'page_number' => 2]),
            new DocumentChunk(['id' => 102, 'content' => 'Chlorophyll absorbs light.', 'page_number' => 2]),
            new DocumentChunk(['id' => 103, 'content' => 'Glucose stores chemical energy.', 'page_number' => 3]),
        ]);

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) use ($topicChunks) {
            $mock->shouldReceive('search')->once()->andReturn($topicChunks);
        });

        $this->fakeQuestionsResponse([
            ['type' => 'multiple_choice', 'question' => 'What does photosynthesis convert?', 'options' => ['A) Heat', 'B) Light energy', 'C) Sound', 'D) Kinetic energy'], 'correct_answer' => 'B', 'explanation' => 'x'],
        ]);

        $agent  = $this->app->make(QuizGeneratorAgent::class);
        $result = $agent->handle(new AgentContext(
            question:     '3 questions about photosynthesis',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
            intent:       new ClassifiedIntent(
                intent:          'generate_quiz',
                topic:           'photosynthesis',
                topicInDocument: true,
                confidence:      0.9,
            ),
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertCount(1, $result->metadata['questions']);
        $this->assertEquals('multiple_choice', $result->metadata['questions'][0]['type']);
    }

    public function test_agent_returns_text_when_document_has_no_content(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 0);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
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

    public function test_overflow_request_generates_warning_message_and_caps_questions(): void
    {
        // 3 chunks * 100 tokens = 300 total tokens => capacity of 1 question
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 3, tokensPerChunk: 100);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $this->fakeQuestionsResponse([
            ['type' => 'multiple_choice', 'question' => 'Q1', 'options' => ['A) 1', 'B) 2', 'C) 3', 'D) 4'], 'correct_answer' => 'A', 'explanation' => 'x'],
        ]);

        $agent  = $this->app->make(QuizGeneratorAgent::class);
        $result = $agent->handle(new AgentContext(
            question:     'Give me 10 questions',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertCount(1, $result->metadata['questions']);

        $warning = $conversation->messages()->where('response_type', 'text')->first();
        $this->assertNotNull($warning);
        $this->assertStringContainsString('10', $warning->content);
        $this->assertStringContainsString('1', $warning->content);
    }

    public function test_requested_count_is_capped_at_twenty(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 50, tokensPerChunk: 200);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $this->fakeQuestionsResponse([
            ['type' => 'flashcard', 'question' => 'Q1', 'correct_answer' => 'A1', 'explanation' => 'x'],
        ]);

        $agent = $this->app->make(QuizGeneratorAgent::class);
        $agent->handle(new AgentContext(
            question:     'Give me 50 questions',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        // 50 tokens/chunk * ... capacity (50*200/300=33) exceeds the hard cap of 20,
        // so the prompt should ask for exactly 20 questions, and no overflow warning fires.
        Http::assertSent(function ($request) {
            return str_contains($request->data()['messages'][0]['content'], 'exactly 20 questions');
        });
        $this->assertEquals(0, $conversation->messages()->where('response_type', 'text')->count());
    }

    public function test_prompt_includes_previous_user_messages_but_not_assistant_replies(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 10, tokensPerChunk: 200);

        $conversation->messages()->create(['role' => 'user', 'content' => 'Make 3 flashcards']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'huge formatted quiz text...', 'response_type' => 'quiz']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Now make 5 true/false questions']);
        // Mirrors what SendMessage.php does: the current question is persisted before the agent runs.
        $conversation->messages()->create(['role' => 'user', 'content' => 'Quiz me on the previous topics']);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $this->fakeQuestionsResponse([
            ['type' => 'true_false', 'question' => 'Q1', 'correct_answer' => 'True', 'explanation' => 'x'],
        ]);

        $agent = $this->app->make(QuizGeneratorAgent::class);
        $agent->handle(new AgentContext(
            question:     'Quiz me on the previous topics',
            conversation: $conversation,
            document:     $document,
            userId:       $user->id,
        ));

        Http::assertSent(function ($request) {
            $prompt = $request->data()['messages'][0]['content'];
            return str_contains($prompt, 'Previous Requests')
                && str_contains($prompt, 'Make 3 flashcards')
                && str_contains($prompt, 'Now make 5 true/false questions')
                && !str_contains($prompt, 'huge formatted quiz text');
        });
    }
}
