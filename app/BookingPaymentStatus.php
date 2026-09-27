<?php

namespace App;

enum BookingPaymentStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Sedang diperiksa',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }
}
