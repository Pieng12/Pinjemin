<?php

namespace Database\Seeders;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\User;
use App\Services\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OwnerBusinessDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const OwnerEmail = 'pemilik.demo@pinjemin.test';

    public function run(RentalPriceCalculator $prices): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Data demo pemesanan dan pencairan hanya tersedia untuk lingkungan lokal dan pengujian.');
        }

        $password = Str::password(20, symbols: false);
        DB::transaction(function () use ($prices, $password): void {
            $owner = User::query()->firstOrNew(['email' => self::OwnerEmail]);
            if ($owner->isAdmin()) {
                throw new RuntimeException('Email demo sudah digunakan akun administrator.');
            }
            $owner->forceFill(['name' => 'Pemilik Demo Pinjemin', 'password' => $password, 'remember_token' => null])->save();

            $renters = [];
            foreach (range(1, 8) as $number) {
                $renters[] = User::query()->firstOrCreate(['email' => "penyewa.demo.{$number}@pinjemin.test"], [
                    'name' => "Penyewa Demo {$number}",
                    'password' => Str::password(20, symbols: false),
                ]);
            }

            $category = Category::query()->firstOrCreate(['slug' => 'demo-kinerja-penyewaan'], [
                'name' => 'Barang Demo', 'is_active' => false,
            ]);
            $today = CarbonImmutable::today(Booking::RentalTimezone);
            $sequence = 0;
            foreach ([
                ['kamera', 'Kamera Mirrorless', 125000, [2, 5, 9, 13, 20, 27, 38, 54, 76, 87], [[1, BookingStatus::Pending]]],
                ['proyektor', 'Proyektor Portabel', 85000, [3, 8, 16, 25, 43, 63], [[1, BookingStatus::Approved]]],
                ['tenda', 'Tenda Camping', 65000, [6, 14, 23, 36, 57], [[2, BookingStatus::Cancelled]]],
                ['speaker', 'Speaker Portabel', 70000, [10, 22, 51], [[2, BookingStatus::AwaitingPayment]]],
                ['bor', 'Bor Listrik', 45000, [17, 34, 79], [[3, BookingStatus::Rejected], [5, BookingStatus::Expired]]],
            ] as [$slug, $name, $rate, $completedDays, $otherOrders]) {
                $item = $owner->items()->updateOrCreate(['slug' => 'demo-kinerja-'.$slug], [
                    'category_id' => $category->id, 'name' => $name.' (Demo)', 'daily_price' => $rate,
                    'description' => 'Contoh barang untuk melihat laporan Kinerja Penyewaan.', 'quantity' => 4,
                    'city' => 'Medan', 'address' => 'Lokasi pengambilan demo, Medan',
                    'is_active' => false, 'status' => ItemStatus::Inactive,
                ]);

                $completedOrders = array_map(fn (int $daysAgo): array => [$daysAgo, BookingStatus::Completed], $completedDays);
                foreach ([...$completedOrders, ...$otherOrders] as [$daysAgo, $status]) {
                    $code = sprintf('PNJ-DEMO-KINERJA-%03d', ++$sequence);
                    $renter = $renters[($sequence - 1) % count($renters)];
                    $created = $today->subDays($daysAgo)->setTime(9, 0);
                    $completed = $status === BookingStatus::Completed;
                    $approved = $status === BookingStatus::Approved;
                    $awaitingPayment = $status === BookingStatus::AwaitingPayment;
                    $accepted = $completed || $approved || $awaitingPayment;
                    $start = $completed ? $created->addDay()->startOfDay() : $today->addDays(2);
                    $duration = $completed ? min($daysAgo - 1, $sequence % 3 + 1) : 2;
                    $end = $start->addDays($duration - 1);
                    $quantity = $completed ? $sequence % 2 + 1 : 1;
                    $price = $prices->calculate($item, $start->toDateString(), $end->toDateString(), $quantity);
                    $returned = $completed ? $end->setTime(16, 0)->utc() : null;

                    $booking = $item->bookings()->updateOrCreate(['booking_code' => $code], [
                        'renter_user_id' => $renter->id, 'status' => $status, 'created_at' => $created->utc(),
                        'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
                        'daily_price' => $rate, 'quantity' => $quantity, 'rental_days' => $price['rental_days'],
                        'subtotal' => $price['subtotal'], 'deposit_amount' => $price['deposit_total'], 'total_amount' => $price['total_amount'],
                        'fulfillment_method' => Booking::Pickup, 'renter_note' => 'Pemesanan contoh untuk laporan demo.',
                        'fulfillment_snapshot' => ['pricing_version' => 2, 'payment_collection' => 'platform', 'commission_rate' => 5],
                        'accepted_at' => $accepted ? $created->addHour()->utc() : null,
                        'payment_due_at' => $awaitingPayment ? now()->addHours(12) : null,
                        'approved_at' => $completed || $approved ? $created->addHours(2)->utc() : null,
                        'payment_verified_at' => $completed || $approved ? $created->addHours(2)->utc() : null,
                        'handed_over_at' => $completed ? $start->setTime(9, 0)->utc() : null,
                        'returned_at' => $returned, 'completed_at' => $returned,
                        'cancelled_at' => $status === BookingStatus::Cancelled ? $created->addHours(2)->utc() : null,
                        'rejected_at' => $status === BookingStatus::Rejected ? $created->addHours(2)->utc() : null,
                        'expired_at' => $status === BookingStatus::Expired ? $created->addDay()->utc() : null,
                    ]);

                    if ($accepted) {
                        $this->seedPaymentAndPayout($booking, $returned?->addHours(2), $completed || $approved);
                    }
                }
            }
        });

        $this->command?->info('Data Kinerja Penyewaan siap. Masuk dengan akun demo untuk melihat grafik.');
        $this->command?->line('Email: '.self::OwnerEmail);
        $this->command?->line('Kata sandi demo: '.$password);
    }

    private function seedPaymentAndPayout(Booking $booking, ?CarbonImmutable $paidOutAt, bool $paid): void
    {
        if ($paid) {
            $booking->payments()->updateOrCreate(['transaction_reference' => 'DEMO-BAYAR-'.$booking->booking_code], [
                'submitted_by_user_id' => $booking->renter_user_id,
                'kind' => 'full', 'channel' => 'transfer', 'amount' => $booking->total_amount,
                'method_snapshot' => ['type' => 'bank', 'label' => 'Transfer Demo'],
                'status' => BookingPaymentStatus::Verified, 'transferred_at' => $booking->accepted_at,
                'submitted_at' => $booking->accepted_at, 'reviewed_at' => $booking->payment_verified_at,
            ]);
        }

        $commission = round((float) $booking->subtotal * 0.05, 2);
        $booking->payout()->updateOrCreate([], [
            'status' => $paidOutAt ? 'paid' : 'pending',
            'commission_rate' => 5, 'commission_amount' => $commission,
            'amount' => round((float) $booking->total_amount - $commission, 2),
            'destination_snapshot' => [
                'type' => 'bank', 'label' => 'Rekening Demo', 'account_name' => 'Pemilik Demo Pinjemin', 'account_identifier' => null,
            ],
            'events' => [], 'paid_at' => $paidOutAt,
            'transaction_reference' => $paidOutAt ? 'DEMO-CAIR-'.$booking->booking_code : null,
        ]);
    }
}
