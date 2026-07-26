<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'question' => $this->faker->sentence().'?',
            'type' => $this->faker->randomElement(['multiple_choice', 'flashcard']),
            'options' => ['A) Option one', 'B) Option two', 'C) Option three', 'D) Option four'],
            'correct_answer' => 'A',
            'explanation' => $this->faker->sentence(),
            'page_number' => $this->faker->numberBetween(1, 10),
            'sort_order' => 0,
        ];
    }
}
