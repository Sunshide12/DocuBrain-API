<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
use App\Models\User;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_id' => null,
            'title' => $this->faker->sentence(3),
            'total_tokens' => $this->faker->numberBetween(0, 5000),
        ];
    }
}
