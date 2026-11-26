<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatConversation> */
class ChatConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'item_name' => fn (array $attributes) => Item::findOrFail($attributes['item_id'])->name,
            'renter_id' => User::factory(),
            'owner_id' => fn (array $attributes) => Item::findOrFail($attributes['item_id'])->user_id,
        ];
    }
}
