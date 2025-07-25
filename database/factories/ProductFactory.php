<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['Food', 'Beverage', 'Dessert', 'Snack', 'Supplement']),
            'tags' => $this->faker->randomElements(['organic', 'healthy', 'vegan', 'gluten-free', 'sugar-free', 'low-fat'], $this->faker->numberBetween(1, 3)),
            'serving_size' => $this->faker->randomFloat(2, 10, 500),
            'serving_unit' => $this->faker->randomElement(['g', 'ml', 'oz', 'cup', 'piece']),
            'servings_per_container' => $this->faker->numberBetween(1, 20),
            'is_public' => $this->faker->boolean(70), // 70% chance of being public
        ];
    }
}
