<?php

namespace Tests\Feature\Tools;

use App\Agents\Tools\DocumentQATool;
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
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentQAToolTest extends TestCase
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

    private function defaultIntent(): ClassifiedIntent
    {
        return new ClassifiedIntent(
            intent: 'ask_question',
            topic: null,
            topicInDocument: true,
            confidence: 1.0,
        );
    }

    public function test_answers_off_topic_questions_instead_of_refusing_when_topic_is_missing(): void
    {
        [$user, $document, $conversation] = $this->makeConversation();

        // A topic the document does not cover is the normal off-topic case: the tool
        // must still answer, telling the model there is no related passage so it can
        // say so and then help from general knowledge.
        $capturedPrompt = null;
        $this->mock(OpenRouterClient::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('chat')->once()->andReturnUsing(function (array $messages) use (&$capturedPrompt) {
                $capturedPrompt = $messages[0]['content'];

                return 'Eso no está en el documento, pero la fotosíntesis es…';
            });
        });

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn([0.1, 0.2]);
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) {
            $mock->shouldReceive('searchAdaptive')->once()->andReturn(new Collection([]));
        });

        $intent = new ClassifiedIntent(
            intent: 'ask_question',
            topic: 'fotosíntesis',
            topicInDocument: false,
            confidence: 1.0,
        );

        $tool = $this->app->make(DocumentQATool::class);
        $response = $tool->execute(new ToolContext(
            question: '¿qué es la fotosíntesis?',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $intent,
        ));

        $this->assertStringContainsString('fotosíntesis', $response->answer);
        $this->assertStringContainsString('no passage related', $capturedPrompt);
        $this->assertSame([], $response->sourceChunks);
    }

    public function test_dedupes_and_truncates_chunks_before_prompting_the_llm(): void
    {
        [$user, $document, $conversation] = $this->makeConversation();

        $duplicateContent = "Este es el contenido del capítulo.\n\ncon   espacios raros";
        $longContent = str_repeat('a', 2500);

        $chunks = new Collection([
            DocumentChunk::factory()->create(['document_id' => $document->id, 'page_number' => 1, 'content' => $duplicateContent]),
            // Same text after whitespace-normalization -> must be deduped.
            DocumentChunk::factory()->create(['document_id' => $document->id, 'page_number' => 1, 'content' => 'Este es el contenido del capítulo. con espacios raros']),
            DocumentChunk::factory()->create(['document_id' => $document->id, 'page_number' => 2, 'content' => $longContent]),
        ]);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn([0.1, 0.2]);
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) use ($chunks) {
            $mock->shouldReceive('searchAdaptive')->once()->andReturn($chunks);
        });

        $capturedPrompt = null;
        $this->mock(OpenRouterClient::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('chat')
                ->once()
                ->withArgs(function (array $messages) use (&$capturedPrompt) {
                    $capturedPrompt = $messages[0]['content'];

                    return true;
                })
                ->andReturn('Respuesta de prueba.');
        });

        $tool = $this->app->make(DocumentQATool::class);
        $response = $tool->execute(new ToolContext(
            question: '¿de qué trata el capítulo?',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
        ));

        $this->assertEquals('Respuesta de prueba.', $response->answer);
        $this->assertCount(2, $response->sourceChunks); // deduped from 3 to 2
        // Assert the prompt was assembled, without pinning the exact wording of the
        // instructions (they get tuned; the structure is what this test is about).
        $this->assertStringContainsString('## Rules', $capturedPrompt);
        $this->assertStringContainsString('¿de qué trata el capítulo?', $capturedPrompt);
        $this->assertEquals(1, substr_count($capturedPrompt, 'Este es el contenido del capítulo'));
        $this->assertStringNotContainsString(str_repeat('a', 2500), $capturedPrompt);
        $this->assertStringContainsString(str_repeat('a', 2000).'…', $capturedPrompt);
    }

    public function test_caches_the_answer_and_skips_llm_and_embedding_on_a_repeated_question(): void
    {
        Cache::flush();

        [$user, $document, $conversation] = $this->makeConversation();

        $chunk = DocumentChunk::factory()->create(['document_id' => $document->id, 'page_number' => 3, 'content' => 'Contenido único.']);

        $this->mock(EmbeddingProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->once()->andReturn([0.1, 0.2]);
        });

        $this->mock(PgvectorSimilaritySearch::class, function (MockInterface $mock) use ($chunk) {
            $mock->shouldReceive('searchAdaptive')->once()->andReturn(new Collection([$chunk]));
        });

        $this->mock(OpenRouterClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('chat')->once()->andReturn('Respuesta cacheada.');
        });

        $tool = $this->app->make(DocumentQATool::class);
        $context = new ToolContext(
            question: '¿Qué dice el documento sobre el tema?',
            conversation: $conversation,
            document: $document,
            userId: $user->id,
            intent: $this->defaultIntent(),
        );

        $first = $tool->execute($context);
        $second = $tool->execute($context);

        $this->assertEquals('Respuesta cacheada.', $first->answer);
        $this->assertEquals('Respuesta cacheada.', $second->answer);
    }
}
