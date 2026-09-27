<?php

namespace App\Models;

use Database\Factories\BookingRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRefund extends Model
{
    /** @use HasFactory<BookingRefundFactory> */
    use HasFactory;

    protected $fillable = ['booking_id', 'recorded_by_user_id', 'amount', 'proof_path', 'refunded_at', 'note'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_at' => 'datetime'];
    }
}
