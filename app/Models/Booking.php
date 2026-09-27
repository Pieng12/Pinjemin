<?php

namespace App\Models;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\PaymentPlan;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'booking_code',
    'item_id',
    'renter_user_id',
    'start_date',
    'end_date',
    'quantity',
    'daily_price',
    'rental_days',
    'subtotal',
    'deposit_amount',
    'total_amount',
    'status',
    'renter_note',
    'owner_note',
    'approved_at',
    'rejected_at',
    'cancelled_at',
    'completed_at',
    'fulfillment_method', 'fulfillment_snapshot', 'delivery_fee', 'quoted_at',
    'quote_expires_at', 'renter_confirmed_at', 'expired_at', 'handed_over_at',
    'cancelled_by', 'cancellation_reason', 'invoice_snapshot',
    'accepted_at', 'payment_due_at', 'payment_plan', 'payment_methods_snapshot',
    'payment_receipt_snapshot', 'payment_verified_at', 'payment_legacy', 'refund_status',
])]
class Booking extends Model
{
    public const Pickup = 'pickup';

    public const Delivery = 'delivery';

    public const RentalTimezone = 'Asia/Jakarta';

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => BookingStatus::Pending->value,
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->oldest('id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(BookingRefund::class);
    }

    public function verifiedPayments(): HasMany
    {
        return $this->payments()->where('status', BookingPaymentStatus::Verified->value);
    }

    public function verifiedAmount(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->where('status', BookingPaymentStatus::Verified)->sum('amount');
        }

        return (float) $this->verifiedPayments()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_amount - $this->verifiedAmount(), 2));
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    public function canBeCancelledByRenter(): bool
    {
        return ! $this->handed_over_at && in_array($this->effectiveStatus(), [
            BookingStatus::Pending, BookingStatus::AwaitingRenterConfirmation, BookingStatus::AwaitingPayment,
            BookingStatus::PartiallyPaid, BookingStatus::Approved,
        ], true);
    }

    public function quoteHasExpired(): bool
    {
        return $this->status === BookingStatus::AwaitingRenterConfirmation
            && (! $this->quote_expires_at || $this->quote_expires_at->lte(now()));
    }

    public function effectiveStatus(): BookingStatus
    {
        return $this->quoteHasExpired() || $this->paymentHasExpired() ? BookingStatus::Expired : $this->status;
    }

    public function paymentHasExpired(): bool
    {
        return $this->status === BookingStatus::AwaitingPayment
            && $this->verifiedAmount() <= 0
            && (! $this->payment_due_at || $this->payment_due_at->lte(now()));
    }

    public function returnNotice(): ?string
    {
        if ($this->status !== BookingStatus::Approved) {
            return null;
        }

        $today = now(self::RentalTimezone)->toDateString();

        return match (true) {
            $this->end_date->toDateString() < $today => 'Lewat Jatuh Tempo',
            $this->end_date->toDateString() === $today => 'Berakhir Hari Ini',
            default => null,
        };
    }

    public function scopeReserving(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', BookingStatus::Approved->value)
                ->orWhere('status', BookingStatus::PaymentReview->value)
                ->orWhere('status', BookingStatus::PartiallyPaid->value)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', BookingStatus::AwaitingRenterConfirmation->value)
                        ->where('quote_expires_at', '>', now());
                })->orWhere(function (Builder $query): void {
                    $query->where('status', BookingStatus::AwaitingPayment->value)
                        ->where('payment_due_at', '>', now());
                });
        });
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        if ($status === BookingStatus::Expired->value) {
            return $query->where(function (Builder $query): void {
                $query->where('status', BookingStatus::Expired->value)->orWhere(function (Builder $query): void {
                    $query->where('status', BookingStatus::AwaitingRenterConfirmation->value)
                        ->where(function (Builder $query): void {
                            $query->where('quote_expires_at', '<=', now())->orWhereNull('quote_expires_at');
                        });
                })->orWhere(function (Builder $query): void {
                    $query->where('status', BookingStatus::AwaitingPayment->value)
                        ->where(function (Builder $query): void {
                            $query->where('payment_due_at', '<=', now())->orWhereNull('payment_due_at');
                        })->whereDoesntHave('payments', fn (Builder $query) => $query->where('status', BookingPaymentStatus::Verified->value));
                });
            });
        }

        $query->where('status', $status);

        if ($status === BookingStatus::AwaitingRenterConfirmation->value) {
            return $query->where('quote_expires_at', '>', now());
        }

        return $status === BookingStatus::AwaitingPayment->value
            ? $query->where('payment_due_at', '>', now()) : $query;
    }

    public function getRouteKeyName(): string
    {
        return 'booking_code';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'daily_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => BookingStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'fulfillment_snapshot' => 'array',
            'invoice_snapshot' => 'array',
            'delivery_fee' => 'decimal:2',
            'quoted_at' => 'datetime',
            'quote_expires_at' => 'datetime',
            'renter_confirmed_at' => 'datetime',
            'expired_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'accepted_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'payment_plan' => PaymentPlan::class,
            'payment_methods_snapshot' => 'array',
            'payment_receipt_snapshot' => 'array',
            'payment_verified_at' => 'datetime',
            'payment_legacy' => 'boolean',
        ];
    }
}
