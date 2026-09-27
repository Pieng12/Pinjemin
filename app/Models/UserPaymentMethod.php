<?php

namespace App\Models;

use App\PaymentMethodType;
use Database\Factories\UserPaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPaymentMethod extends Model
{
    /** @use HasFactory<UserPaymentMethodFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'provider', 'account_name', 'account_identifier', 'qris_path',
        'instructions', 'is_active', 'is_primary', 'sort_order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->provider ?: $this->type->label();
    }

    /** @return array<string, mixed> */
    public function snapshot(?string $qrisPath = null): array
    {
        return [
            'source_id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->displayName(),
            'provider' => $this->provider,
            'account_name' => $this->account_name,
            'account_identifier' => $this->account_identifier,
            'qris_path' => $qrisPath,
            'instructions' => $this->instructions,
        ];
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }
}
