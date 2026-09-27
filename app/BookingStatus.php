<?php

namespace App;

enum BookingStatus: string
{
    case Pending = 'pending';
    case AwaitingRenterConfirmation = 'awaiting_renter_confirmation';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentReview = 'payment_review';
    case PartiallyPaid = 'partially_paid';
    case Expired = 'expired';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::AwaitingRenterConfirmation => 'Konfirmasi Ongkir',
            self::AwaitingPayment => 'Menunggu Pembayaran',
            self::PaymentReview => 'Pembayaran Diperiksa',
            self::PartiallyPaid => 'DP Terverifikasi',
            self::Expired => 'Kedaluwarsa',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Completed => 'Selesai',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::AwaitingRenterConfirmation => 'bg-blue-50 text-blue-700',
            self::AwaitingPayment => 'bg-amber-50 text-amber-700',
            self::PaymentReview => 'bg-violet-50 text-violet-700',
            self::PartiallyPaid => 'bg-cyan-50 text-cyan-700',
            self::Expired => 'bg-zinc-100 text-zinc-600',
            self::Approved => 'bg-emerald-50 text-emerald-700',
            self::Rejected => 'bg-red-50 text-red-700',
            self::Cancelled => 'bg-zinc-100 text-zinc-600',
            self::Completed => 'bg-sky-50 text-sky-700',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
