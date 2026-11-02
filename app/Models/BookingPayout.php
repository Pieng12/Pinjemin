<?php

namespace App\Models;

use App\BookingStatus;
use Database\Factories\BookingPayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPayout extends Model
{
    /** @use HasFactory<BookingPayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id', 'status', 'commission_rate', 'commission_amount', 'amount',
        'destination_snapshot', 'events', 'hold_reason', 'processed_by_user_id',
        'processing_at', 'paid_at', 'received_at', 'transaction_reference', 'transaction_key', 'proof_path', 'note',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isEligible(): bool
    {
        return $this->booking->status === BookingStatus::Completed
            && $this->booking->handed_over_at !== null
            && ! $this->booking->payment_legacy
            && $this->booking->remainingAmount() <= 0
            && ! $this->booking->refund_status
            && $this->booking->inspectionsSettled();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => $this->booking->effectiveStatus() === BookingStatus::Expired
                ? 'Booking kedaluwarsa' : ($this->isEligible() ? 'Siap dicairkan' : 'Menunggu penyewaan selesai'),
            'processing' => 'Sedang diproses',
            'on_hold' => 'Ditahan untuk pemeriksaan',
            'paid' => $this->received_at ? 'Diterima pemilik' : 'Sudah ditransfer',
            'cancelled' => 'Dibatalkan',
            default => $this->status,
        };
    }

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2', 'commission_amount' => 'decimal:2', 'amount' => 'decimal:2',
            'destination_snapshot' => 'array', 'events' => 'array',
            'processing_at' => 'datetime', 'paid_at' => 'datetime', 'received_at' => 'datetime',
        ];
    }
}
