<?php

namespace App\Models;

use App\BookingPaymentStatus;
use Database\Factories\BookingPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayment extends Model
{
    /** @use HasFactory<BookingPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id', 'submitted_by_user_id', 'reviewed_by_user_id', 'kind', 'channel', 'amount',
        'method_snapshot', 'proof_path', 'transferred_at', 'renter_note', 'status',
        'owner_comment', 'submitted_at', 'reviewed_at',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method_snapshot' => 'array',
            'status' => BookingPaymentStatus::class,
            'transferred_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
