<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-quotes')]
#[Description('Bebaskan reservasi tawaran ongkir dan pembayaran yang sudah kedaluwarsa')]
class ExpireBookingQuotes extends Command
{
    public function handle(BookingService $bookings): int
    {
        $this->info($bookings->expireQuotes().' booking kedaluwarsa.');

        return self::SUCCESS;
    }
}
