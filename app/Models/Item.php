<?php

namespace App\Models;

use App\ItemCondition;
use App\ItemStatus;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'category_id',
    'name',
    'slug',
    'description',
    'condition',
    'daily_price',
    'deposit_amount',
    'quantity',
    'city',
    'address',
    'latitude',
    'longitude',
    'status',
    'is_active',
    'rules',
    'delivery_enabled',
    'free_delivery',
    'allow_deposit_payment',
    'allow_cash_balance',
])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected $attributes = [
        'condition' => ItemCondition::Good->value,
        'status' => ItemStatus::Available->value,
        'is_active' => true,
        'quantity' => 1,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ItemPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(ItemPhoto::class)->where('is_primary', true)->oldest('sort_order')->oldest('id');
    }

    public function scopeMarketplace(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', ItemStatus::Available->value);
    }

    public function conditionLabel(): string
    {
        return $this->condition->label();
    }

    public function statusLabel(): string
    {
        return $this->is_active && $this->status === ItemStatus::Available
            ? ItemStatus::Available->label()
            : ItemStatus::Inactive->label();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'status' => ItemStatus::class,
            'daily_price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'delivery_enabled' => 'boolean',
            'free_delivery' => 'boolean',
            'allow_deposit_payment' => 'boolean',
            'allow_cash_balance' => 'boolean',
        ];
    }
}
