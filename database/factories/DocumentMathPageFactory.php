<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentMathPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentMathPage>
 */
class DocumentMathPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'page_number' => $this->faker->numberBetween(1, 50),
            'content' => $this->faker->paragraph().' $x^2 + '.$this->faker->numberBetween(1, 10).'x + '.$this->faker->numberBetween(1, 20).' = 0$',
        ];
    }
}
