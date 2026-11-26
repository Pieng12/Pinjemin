<?php

namespace Tests\Feature;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPayout;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerBusinessReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_their_orders_and_counts_repeat_renters_once_in_the_wib_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', Booking::RentalTimezone));
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $camera = Item::factory()->for($owner, 'user')->create(['name' => 'Kamera Milik Saya']);
        $tripod = Item::factory()->for($owner, 'user')->create(['name' => 'Tripod Milik Saya']);
        Booking::factory()->for($camera)->for($renter, 'renter')->create([
            'created_at' => '2026-11-18 17:00:00', 'status' => BookingStatus::Completed,
        ]);
        Booking::factory()->for($camera)->for($renter, 'renter')->create([
            'created_at' => '2026-11-24 17:00:00', 'status' => BookingStatus::AwaitingRenterConfirmation,
            'quote_expires_at' => now()->subMinute(),
        ]);
        $awaiting = Booking::factory()->for($tripod)->create([
            'created_at' => '2026-11-24 20:00:00', 'status' => BookingStatus::AwaitingPayment,
            'payment_due_at' => now()->subMinute(),
        ]);
        BookingPayment::factory()->for($awaiting)->create(['status' => BookingPaymentStatus::Verified]);
        Booking::factory()->for($tripod)->create(['created_at' => '2026-11-18 16:59:59']);
        $otherItem = Item::factory()->create(['name' => 'Barang Pemilik Lain']);
        Booking::factory()->for($otherItem)->create();

        $response = $this->actingAs($owner)->get(route('owner.dashboard', ['period' => 7]));

        $response->assertOk()->assertSee('Tren Pemesanan')->assertDontSee('Barang Pemilik Lain');
        $report = $response->viewData('businessReport');
        $this->assertSame(3, $report['orders']);
        $this->assertSame(2, $report['renters']);
        $this->assertSame(1, $report['completed']);
        $this->assertSame(1, $report['statuses']['expired']);
        $this->assertSame(1, $report['statuses']['in_progress']);
        $this->assertSame([1, 0, 0, 0, 0, 0, 2], array_column($report['periods'], 'orders'));
        $this->assertSame(['Kamera Milik Saya', 'Tripod Milik Saya'], array_column($report['popularItems'], 'name'));
        $this->assertSame([2, 1], array_column($report['popularItems'], 'orders'));
    }

    public function test_chart_counts_only_transferred_payouts_and_uses_the_transfer_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00:00', Booking::RentalTimezone));
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner, 'user')->create();
        $oldBooking = Booking::factory()->for($item)->create(['created_at' => '2026-10-20 11:00:00', 'status' => BookingStatus::Completed]);
        BookingPayout::factory()->for($oldBooking)->create([
            'status' => 'paid', 'paid_at' => '2026-11-18 17:00:00', 'amount' => '95500.25', 'commission_amount' => '4500.75',
        ]);
        BookingPayout::factory()->for(Booking::factory()->for($item))->create([
            'status' => 'paid', 'paid_at' => '2026-11-24 18:00:00', 'amount' => '42000.75', 'commission_amount' => '2000.25',
        ]);
        BookingPayout::factory()->for(Booking::factory()->for($item))->create(['status' => 'on_hold', 'amount' => 780000]);
        BookingPayout::factory()->for(Booking::factory()->for($item))->create(['status' => 'paid', 'paid_at' => '2026-11-18 16:59:59', 'amount' => 230000]);
        BookingPayout::factory()->create(['status' => 'paid', 'paid_at' => now(), 'amount' => 560000]);

        $response = $this->actingAs($owner)->get(route('owner.dashboard', ['period' => 7]));

        $response->assertOk()->assertSee('Rp 137.501')->assertSee('Rp 6.501');
        $report = $response->viewData('businessReport');
        $this->assertSame(13750100, $report['transferredCents']);
        $this->assertSame(650100, $report['commissionCents']);
        $this->assertSame([9550025, 0, 0, 0, 0, 0, 4200075], array_column($report['periods'], 'transferredCents'));
    }

    public function test_empty_report_and_supported_periods_render_without_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('owner.dashboard', ['period' => 90]))->assertOk()
            ->assertSee('Belum ada transfer yang tercatat pada periode ini.')
            ->assertSee('Belum ada pengajuan pada periode ini.')
            ->assertViewHas('businessReport', fn (array $report): bool => $report['days'] === 90 && count($report['periods']) === 6);

        $this->get(route('owner.dashboard', ['period' => '365']))->assertOk()
            ->assertViewHas('businessReport', fn (array $report): bool => $report['days'] === 30);
    }
}
