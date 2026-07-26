<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'title' => 'Auto-generated Quiz: '.$this->faker->words(3, true),
            'status' => 'ready',
            'error_message' => null,
        ];
    }

    public function generating(): static
    {
        return $this->state(['status' => 'generating']);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'LLM returned malformed JSON.',
        ]);
    }
}
