<?php

namespace App\Models;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['item_id', 'item_name', 'renter_id', 'owner_id'])]
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function closedMessage(): ?string
    {
        return match (true) {
            $this->item_id !== null => null,
            $this->direct_key !== null => 'Riwayat chat lama hanya dapat dibaca. Gunakan katalog untuk menghubungi pemilik barang.',
            default => 'Barang telah dihapus. Percakapan ini hanya dapat dibaca.',
        };
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $pair) => $pair->where('renter_id', $user->id)->orWhere('owner_id', $user->id));
    }

    public function otherParticipant(User $user): User
    {
        return $user->id === $this->renter_id ? $this->owner : $this->renter;
    }

    public function readColumn(User $user): string
    {
        return $user->id === $this->renter_id ? 'renter_read_id' : 'owner_read_id';
    }

    public function scopeWithUnreadFor(Builder $query, User $user): Builder
    {
        return $query->withExists(['messages as unread_exists' => fn (Builder $messages) => $messages
            ->where('sender_id', '!=', $user->id)
            ->whereRaw('chat_messages.id > CASE WHEN chat_conversations.renter_id = ? THEN chat_conversations.renter_read_id ELSE chat_conversations.owner_read_id END', [$user->id])]);
    }
}
