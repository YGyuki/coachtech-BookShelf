<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'author' => fake()->name(),
            'isbn_13' => fake()->unique()->isbn13(),
            'publication_date' => fake()->date(),
            'description' => fake()->realText(),
            'image_url' => fake()->url(),
        ];
    }
}
