<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\QuizGeneratorTool;
use App\DTOs\ClassifiedIntent;
use App\DTOs\ToolContext;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\OpenRouterClient;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class QuizGeneratorToolTest extends TestCase
{
    use RefreshDatabase;

    private function fakeQuestionsResponse(array $questions): void
    {
        $this->mock(OpenRouterClient::class, function (MockInterface $mock) use ($questions) {
            $mock->shouldReceive('chat')->andReturn(json_encode($questions));
        });
    }

    private function defaultIntent(?string $topic = null): ClassifiedIntent
    {
        return new ClassifiedIntent(
            intent: 'generate_quiz',
            topic: $topic,
            topicInDocument: true,
            confidence: 1.0,
        );
    }

    private function makeConversation(int $chunkCount = 0, int $tokensPerChunk = 200): array
    {
        $user = User::factory()->create();
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
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return [$user, $document, $conversation];
    }

    public function test_tool_falls_back_to_uniform_sampling_when_no_topic_matches(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 10, tokensPerChunk: 200);

        // Null topic = generic quiz request; tool must sample uniformly without touching embeddings/search.
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

        $tool = $this->app->make(QuizGeneratorTool::class);
        $result = $tool->execute(new ToolContext(
            question: 'Quiz me',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertCount(5, $result->metadata['questions']);
        $this->assertEquals(0, $conversation->messages()->where('response_type', 'text')->count());
    }

    public function test_tool_uses_similarity_chunks_when_topic_is_relevant(): void
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

        $tool = $this->app->make(QuizGeneratorTool::class);
        $result = $tool->execute(new ToolContext(
            question: '3 questions about photosynthesis',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent('photosynthesis'),
        ));

        $this->assertEquals('quiz', $result->responseType);
        $this->assertCount(1, $result->metadata['questions']);
        $this->assertEquals('multiple_choice', $result->metadata['questions'][0]['type']);
    }

    public function test_tool_returns_text_when_document_has_no_content(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 0);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $tool = $this->app->make(QuizGeneratorTool::class);
        $result = $tool->execute(new ToolContext(
            question: 'Quiz me on quantum physics',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
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

        $tool = $this->app->make(QuizGeneratorTool::class);
        $result = $tool->execute(new ToolContext(
            question: 'Give me 10 questions',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
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

        $capturedPrompt = null;
        $this->mock(OpenRouterClient::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('chat')
                ->withArgs(function (array $messages) use (&$capturedPrompt) {
                    $capturedPrompt = $messages[0]['content'];

                    return true;
                })
                ->andReturn(json_encode([
                    ['type' => 'flashcard', 'question' => 'Q1', 'correct_answer' => 'A1', 'explanation' => 'x'],
                ]));
        });

        $tool = $this->app->make(QuizGeneratorTool::class);
        $tool->execute(new ToolContext(
            question: 'Give me 50 questions',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
        ));

        $this->assertStringContainsString('exactly 20 questions', $capturedPrompt);
        $this->assertEquals(0, $conversation->messages()->where('response_type', 'text')->count());
    }

    public function test_prompt_includes_previous_user_messages_but_not_assistant_replies(): void
    {
        [$user, $document, $conversation] = $this->makeConversation(chunkCount: 10, tokensPerChunk: 200);

        $conversation->messages()->create(['role' => 'user', 'content' => 'Make 3 flashcards']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'huge formatted quiz text...', 'response_type' => 'quiz']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Now make 5 true/false questions']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Quiz me on the previous topics']);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->never();
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')->never();
        });

        $capturedPrompt = null;
        $this->mock(OpenRouterClient::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('chat')
                ->withArgs(function (array $messages) use (&$capturedPrompt) {
                    $capturedPrompt = $messages[0]['content'];

                    return true;
                })
                ->andReturn(json_encode([
                    ['type' => 'true_false', 'question' => 'Q1', 'correct_answer' => 'True', 'explanation' => 'x'],
                ]));
        });

        $tool = $this->app->make(QuizGeneratorTool::class);
        $tool->execute(new ToolContext(
            question: 'Quiz me on the previous topics',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
        ));

        $this->assertStringContainsString('Previous Requests', $capturedPrompt);
        $this->assertStringContainsString('Make 3 flashcards', $capturedPrompt);
        $this->assertStringContainsString('Now make 5 true/false questions', $capturedPrompt);
        $this->assertStringNotContainsString('huge formatted quiz text', $capturedPrompt);
    }
}
