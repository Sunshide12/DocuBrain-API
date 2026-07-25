<?php

namespace App\GraphQL\Mutations;

use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ReviewOpenEndedAnswer
{
    private const VALID_SCORES = ['excellent', 'good', 'partial', 'incorrect'];

    public function __invoke($_, array $args): array
    {
        $question = QuizQuestion::with('quiz')->findOrFail($args['question_id']);

        if ($question->quiz->user_id !== Auth::id()) {
            throw new \Exception('Unauthorized');
        }

        $userAnswer = $args['user_answer'];

        $prompt = <<<EOT
You are grading a student's answer to an open-ended study question. Judge it ONLY against the model answer below — do not use outside knowledge.

## Question
{$question->question}

## Model Answer
{$question->correct_answer}

## Grading Rubric
{$question->explanation}

## Student's Answer
{$userAnswer}

## Output
Output ONLY a valid JSON object, no surrounding text, no markdown fences:
{"score":"excellent|good|partial|incorrect","feedback":"1-3 sentences on what was right or missing"}
EOT;

        $baseUrl = config('services.openrouter.base_url');
        $apiKey  = config('services.openrouter.api_key');
        $model   = config('services.openrouter.llm_model');

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                'model'    => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenRouter API error: " . $response->status() . " - " . $response->body());
        }

        $raw = trim($response->json('choices.0.message.content') ?? '');
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);

        $parsed = json_decode($raw, true);

        $score = strtolower($parsed['score'] ?? '');
        if (!in_array($score, self::VALID_SCORES, true)) {
            $score = 'partial';
        }

        return [
            'score'     => $score,
            'feedback'  => $parsed['feedback'] ?? $raw,
            'isCorrect' => in_array($score, ['excellent', 'good'], true),
        ];
    }
}
