<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
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
            'title' => fake()->sentence(rand(2, 5)),
            'publisher' => fake()->company(),
            'author' => fake()->name(),
            'genre' => fake()->randomElement([
                'Fiction', 'Non-fiction', 'Science Fiction', 'Fantasy',
                'Mystery', 'Thriller', 'Romance', 'Biography',
                'History', 'Self-Help', 'Poetry', 'Drama',
            ]),
            'publication_date' => fake()->dateTimeBetween('-50 years', 'now')->format('Y-m-d'),
            'word_count' => fake()->numberBetween(20_000, 200_000),
            'price_usd' => fake()->randomFloat(2, 4.99, 99.99),
        ];
    }
}
