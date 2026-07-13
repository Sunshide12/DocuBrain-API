<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
use App\Models\Conversation;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant']),
            'content' => $this->faker->paragraph(),
            'source_chunk_ids' => null,
            'prompt_tokens' => $this->faker->numberBetween(10, 500),
            'completion_tokens' => $this->faker->numberBetween(10, 500),
        ];
    }
}
