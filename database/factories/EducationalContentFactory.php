<?php

namespace Database\Factories;

use App\Enums\EcntCategory;
use App\Models\EducationalContent;
use Illuminate\Database\Eloquent\Factories\Factory;

class EducationalContentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'type' => EducationalContent::TYPE_ARTICLE,
            'url_or_path' => $this->faker->url(),
            'ecnt_category' => $this->faker->randomElement(array_column(EcntCategory::cases(), 'value')),
        ];
    }
}
