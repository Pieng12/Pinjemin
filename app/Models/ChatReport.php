<?php

namespace App\Models;

use Database\Factories\ChatReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_message_id', 'reporter_id', 'reason', 'details'])]
class ChatReport extends Model
{
    /** @use HasFactory<ChatReportFactory> */
    use HasFactory;

    public const Reasons = [
        'spam' => 'Spam',
        'harassment' => 'Pelecehan',
        'fraud' => 'Dugaan penipuan',
        'other' => 'Lainnya',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
