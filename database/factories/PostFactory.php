<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'image_url' => null,
            'image_filter_score' => fake()->randomFloat(4, 0.5, 1.0),
            'text_genuineness_score' => fake()->randomFloat(4, 0.5, 1.0),
            'authenticity_score' => fake()->randomFloat(4, 0.5, 1.0),
            'metadata' => [],
        ];
    }
}
