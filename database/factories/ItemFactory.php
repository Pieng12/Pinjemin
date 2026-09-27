<?php

namespace Database\Factories;

use App\ItemCondition;
use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(4),
            'condition' => fake()->randomElement(ItemCondition::values()),
            'daily_price' => fake()->numberBetween(25000, 350000),
            'deposit_amount' => fake()->optional()->numberBetween(50000, 500000),
            'quantity' => fake()->numberBetween(1, 5),
            'city' => fake()->randomElement(['Medan', 'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta']),
            'address' => fake()->streetAddress(),
            'status' => ItemStatus::Available->value,
            'is_active' => true,
            'rules' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ItemStatus::Inactive->value,
            'is_active' => false,
        ]);
    }
}
