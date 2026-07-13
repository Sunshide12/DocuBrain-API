<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'chunk_index' => $this->faker->numberBetween(0, 49),
            'content'     => $this->faker->paragraphs(3, true),
            'token_count' => $this->faker->numberBetween(100, 500),
            'page_number' => $this->faker->optional(0.8)->numberBetween(1, 20),
        ];
    }
}
