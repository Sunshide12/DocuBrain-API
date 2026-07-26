<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
/**
 * @extends Factory<Conversation>
 */
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_id' => null,
            'agent_type' => 'orchestrator',
            'title' => $this->faker->sentence(3),
            'total_tokens' => $this->faker->numberBetween(0, 5000),
        ];
    }
}
