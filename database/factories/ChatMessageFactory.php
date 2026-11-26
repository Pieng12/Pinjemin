<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ChatMessage> */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_conversation_id' => ChatConversation::factory(),
            'sender_id' => fn (array $attributes) => ChatConversation::findOrFail($attributes['chat_conversation_id'])->renter_id,
            'submission_id' => (string) Str::uuid(),
            'body' => fake()->sentence(),
        ];
    }
}
