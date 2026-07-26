<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateAutoQuizJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateAutoQuizJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_quiz_with_questions_from_chunks(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'title' => 'Calculus Basics',
        ]);

        DocumentChunk::insert([
            [
                'document_id' => $document->id,
                'chunk_index' => 0,
                'page_number' => 1,
                'content' => 'The derivative of a function measures the rate of change. For f(x) = x^2, the derivative f\'(x) = 2x.',
                'token_count' => 20,
                'created_at' => now(),
            ],
            [
                'document_id' => $document->id,
                'chunk_index' => 1,
                'page_number' => 1,
                'content' => 'The integral is the inverse of the derivative. The integral of 2x is x^2 + C.',
                'token_count' => 17,
                'created_at' => now(),
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            [
                                'type' => 'multiple_choice',
                                'question' => 'What is the derivative of x^2?',
                                'options' => ['A) x', 'B) 2x', 'C) x^2', 'D) 2'],
                                'correct_answer' => 'B',
                                'explanation' => 'The power rule gives f\'(x) = 2x.',
                            ],
                            [
                                'type' => 'flashcard',
                                'question' => 'Define: integral',
                                'correct_answer' => 'The inverse operation of the derivative.',
                                'explanation' => 'Stated in the text.',
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        (new GenerateAutoQuizJob($document))->handle();

        $quiz = Quiz::where('document_id', $document->id)->where('user_id', $user->id)->first();

        $this->assertNotNull($quiz);
        $this->assertEquals('ready', $quiz->status);
        $this->assertEquals(2, $quiz->questions()->count());
        $this->assertDatabaseHas('quiz_questions', ['quiz_id' => $quiz->id, 'type' => 'multiple_choice']);
        $this->assertDatabaseHas('quiz_questions', ['quiz_id' => $quiz->id, 'type' => 'flashcard']);
    }

    public function test_job_marks_quiz_as_failed_when_llm_returns_malformed_json(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
        ]);

        DocumentChunk::insert([[
            'document_id' => $document->id,
            'chunk_index' => 0,
            'page_number' => 1,
            'content' => 'Some document content here.',
            'token_count' => 5,
            'created_at' => now(),
        ]]);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Here are some questions: 1. What is...'],
                ]],
            ], 200),
        ]);

        try {
            (new GenerateAutoQuizJob($document))->handle();
            $this->fail('Expected an exception to be thrown.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('malformed JSON', $e->getMessage());
        }

        $quiz = Quiz::where('document_id', $document->id)->first();
        $this->assertNotNull($quiz);
        $this->assertEquals('failed', $quiz->status);
    }
}
