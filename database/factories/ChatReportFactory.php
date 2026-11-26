<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatReport> */
class ChatReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'reporter_id' => function (array $attributes): int {
                $message = ChatMessage::findOrFail($attributes['chat_message_id']);
                $conversation = $message->conversation;

                return $message->sender_id === $conversation->renter_id ? $conversation->owner_id : $conversation->renter_id;
            },
            'reason' => 'spam',
        ];
    }
}
