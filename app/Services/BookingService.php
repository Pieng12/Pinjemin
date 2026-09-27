<?php

namespace App\Services;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private RentalPriceCalculator $calculator,
        private PaymentService $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $renter, Item $item, array $data): Booking
    {
        return DB::transaction(function () use ($renter, $item, $data): Booking {
            $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->ensureBookable($renter, $item, $data['start_date'], $data['end_date'], (int) $data['quantity']);
            $method = $data['fulfillment_method'] ?? Booking::Pickup;
            if ($method === Booking::Delivery && ! $item->delivery_enabled) {
                throw ValidationException::withMessages(['fulfillment_method' => 'Pemilik tidak menyediakan pengiriman untuk barang ini.']);
            }
            $pricing = $this->calculator->calculate($item, $data['start_date'], $data['end_date'], (int) $data['quantity']);
            $this->ensureTotalFits($pricing['total_amount']);

            return Booking::create([
                'booking_code' => $this->generateBookingCode(),
                'item_id' => $item->id,
                'renter_user_id' => $renter->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'quantity' => (int) $data['quantity'],
                'daily_price' => $item->daily_price,
                'rental_days' => $pricing['rental_days'],
                'subtotal' => $pricing['subtotal'],
                'deposit_amount' => $pricing['deposit_total'],
                'total_amount' => $pricing['total_amount'],
                'status' => BookingStatus::Pending,
                'renter_note' => $data['renter_note'] ?? null,
                'fulfillment_method' => $method,
                'fulfillment_snapshot' => [
                    'item_name' => $item->name,
                    'owner' => ['name' => $item->user->name, 'email' => $item->user->email, 'phone' => $item->user->phone],
                    'renter' => ['name' => $renter->name, 'email' => $renter->email, 'phone' => $renter->phone],
                    'pickup' => ['city' => $item->city, 'address' => $item->address],
                    'free_delivery' => $method === Booking::Delivery && $item->free_delivery,
                    'recipient' => $method === Booking::Delivery ? [
                        'name' => $data['recipient_name'], 'phone' => $data['recipient_phone'],
                        'city' => $data['recipient_city'], 'address' => $data['recipient_address'],
                    ] : null,
                    'payment_policy' => [
                        'allow_deposit' => $item->allow_deposit_payment,
                        'allow_cash_balance' => $item->allow_deposit_payment && $item->allow_cash_balance,
                    ],
                ],
            ]);
        }, attempts: 3);
    }

    public function approve(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $booking): void {
            $this->ensureStatus($booking, BookingStatus::Pending);
            if ($booking->fulfillment_method === Booking::Delivery && ! ($booking->fulfillment_snapshot['free_delivery'] ?? false)) {
                throw ValidationException::withMessages(['delivery_fee' => 'Kirim tawaran ongkir untuk disetujui penyewa terlebih dahulu.']);
            }
            $this->ensureApprovalAvailable($booking);
            $this->payments->beginPayment($booking);
        });
    }

    public function quoteDelivery(Booking $booking, float $deliveryFee): Booking
    {
        return $this->locked($booking, function (Booking $booking) use ($deliveryFee): void {
            $this->ensureStatus($booking, BookingStatus::Pending);
            if ($booking->fulfillment_method !== Booking::Delivery || ($booking->fulfillment_snapshot['free_delivery'] ?? false)) {
                throw ValidationException::withMessages(['booking' => 'Booking ini tidak memerlukan tawaran ongkir.']);
            }
            $this->ensureApprovalAvailable($booking);
            if (! $booking->item->user->paymentMethods()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['payment_method' => 'Tambahkan metode pembayaran aktif sebelum mengirim tawaran ongkir.']);
            }
            $total = round((float) $booking->subtotal + (float) $booking->deposit_amount + $deliveryFee, 2);
            $this->ensureTotalFits($total);
            $expires = now()->addHours(24)->min(Carbon::parse($booking->start_date->toDateString(), Booking::RentalTimezone)->endOfDay()->utc());
            $booking->update([
                'delivery_fee' => $deliveryFee, 'total_amount' => $total,
                'status' => BookingStatus::AwaitingRenterConfirmation,
                'quoted_at' => now(), 'quote_expires_at' => $expires,
            ]);
        });
    }

    public function confirmDelivery(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $booking): void {
            $this->ensureStatus($booking, BookingStatus::AwaitingRenterConfirmation);
            $this->ensureApprovalAvailable($booking);
            $booking->renter_confirmed_at = now();
            $this->payments->beginPayment($booking);
        });
    }

    public function reject(Booking $booking, ?string $ownerNote = null): Booking
    {
        return $this->locked($booking, function (Booking $booking) use ($ownerNote): void {
            $this->ensureStatus($booking, BookingStatus::Pending);
            $booking->update([
                'status' => BookingStatus::Rejected,
                'owner_note' => $ownerNote,
                'rejected_at' => now(),
            ]);

        });
    }

    public function cancel(Booking $booking, ?User $actor = null, ?string $reason = null): Booking
    {
        return $this->locked($booking, function (Booking $booking) use ($actor, $reason): void {
            if (! $booking->canBeCancelledByRenter()) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking dengan status ini tidak dapat dibatalkan.',
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor?->id === $booking->item->user_id ? 'owner' : 'renter',
                'cancellation_reason' => $reason,
                'refund_status' => $booking->verifiedAmount() > 0 ? 'pending' : null,
            ]);
        });
    }

    public function handOver(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $booking): void {
            $this->ensureStatus($booking, BookingStatus::Approved);
            if ($booking->handed_over_at || $booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
                throw ValidationException::withMessages(['booking' => 'Penyerahan hanya dapat dicatat sekali, pada atau setelah tanggal mulai.']);
            }
            if ($this->availability->getAvailableQuantity($booking->item, now(Booking::RentalTimezone)->toDateString(), $booking->end_date->max(now(Booking::RentalTimezone))->toDateString(), $booking) < $booking->quantity) {
                throw ValidationException::withMessages(['booking' => 'Ada konflik stok atau barang terlambat kembali. Selesaikan pengembalian sebelum penyerahan.']);
            }
            $booking->update(['handed_over_at' => now()]);
        });
    }

    public function complete(Booking $booking): Booking
    {
        return $this->locked($booking, function (Booking $booking): void {
            $this->ensureStatus($booking, BookingStatus::Approved);
            if (! $booking->handed_over_at) {
                throw ValidationException::withMessages(['booking' => 'Catat penyerahan barang sebelum mengonfirmasi pengembalian.']);
            }
            $booking->update(['status' => BookingStatus::Completed, 'completed_at' => now()]);
        });
    }

    public function expireQuotes(): int
    {
        $quotes = Booking::query()->where('status', BookingStatus::AwaitingRenterConfirmation->value)
            ->where(function ($query): void {
                $query->where('quote_expires_at', '<=', now())->orWhereNull('quote_expires_at');
            })->update(['status' => BookingStatus::Expired->value, 'expired_at' => now()]);

        return $quotes + $this->payments->expireAwaitingPayments();
    }

    /** @param Closure(Booking): void $operation */
    private function locked(Booking $booking, Closure $operation): Booking
    {
        $result = DB::transaction(function () use ($booking, $operation): Booking {
            $item = Item::query()->whereKey($booking->item_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('item', $item);
            if ($booking->quoteHasExpired() || $booking->paymentHasExpired()) {
                $booking->update(['status' => BookingStatus::Expired, 'expired_at' => now()]);
            } else {
                $operation($booking);
            }

            return $booking->refresh();
        }, attempts: 3);

        if ($result->status === BookingStatus::Expired) {
            throw ValidationException::withMessages(['booking' => 'Tawaran ongkir sudah kedaluwarsa. Silakan ajukan booking baru.']);
        }

        return $result;
    }

    private function ensureStatus(Booking $booking, BookingStatus $expected): void
    {
        if ($booking->status !== $expected) {
            throw ValidationException::withMessages(['booking' => 'Status booking sudah berubah. Muat ulang halaman untuk melihat status terbaru.']);
        }
    }

    private function ensureApprovalAvailable(Booking $booking): void
    {
        $item = $booking->item;
        if (! $item->is_active || $item->status !== ItemStatus::Available || ! filled($item->address)) {
            throw ValidationException::withMessages(['booking' => 'Aktifkan barang dan lengkapi lokasi pengambilan sebelum menyetujui booking.']);
        }
        if ($booking->start_date->toDateString() < now(Booking::RentalTimezone)->toDateString()) {
            throw ValidationException::withMessages(['booking' => 'Tanggal mulai sudah lewat. Silakan ajukan booking baru.']);
        }
        if ($this->availability->getAvailableQuantity($item, $booking->start_date, $booking->end_date, $booking) < $booking->quantity) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah barang tidak lagi tersedia pada periode tersebut.']);
        }
    }

    private function ensureTotalFits(float $total): void
    {
        if ($total > 9999999999.99 || $total < 0) {
            throw ValidationException::withMessages(['quantity' => 'Total biaya terlalu besar. Kurangi jumlah unit atau durasi sewa.']);
        }
    }

    private function ensureBookable(User $renter, Item $item, string $startDate, string $endDate, int $quantity): void
    {
        if ($item->user_id === $renter->id) {
            throw ValidationException::withMessages([
                'item' => 'Kamu tidak dapat menyewa barang milik sendiri.',
            ]);
        }

        if (! $item->is_active || $item->status !== ItemStatus::Available) {
            throw ValidationException::withMessages([
                'item' => 'Barang tidak tersedia untuk disewa.',
            ]);
        }

        if (! filled($item->address)) {
            throw ValidationException::withMessages(['item' => 'Pemilik belum melengkapi lokasi pengambilan barang.']);
        }

        if ($quantity > $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang yang tersedia hanya '.$item->quantity.' unit.',
            ]);
        }

        $availableQuantity = $this->availability->getAvailableQuantity($item, $startDate, $endDate);

        if ($quantity > $availableQuantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang yang tersedia hanya '.$availableQuantity.' unit.',
            ]);
        }
    }

    private function generateBookingCode(): string
    {
        do {
            $bookingCode = 'PNJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Booking::query()->where('booking_code', $bookingCode)->exists());

        return $bookingCode;
    }
}
