<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_booking_from_eighteenth_to_twentieth_uses_three_days_and_releases_twenty_first(): void
    {
        $this->travelTo(Carbon::parse('2026-09-18T01:00:00Z'));
        $item = Item::factory()->create(['quantity' => 1]);
        UserPaymentMethod::factory()->for($item->user)->create(['is_primary' => true]);
        $this->actingAs(User::factory()->create())->post(route('items.bookings.store', $item), [
            'start_date' => '2026-09-18', 'end_date' => '2026-09-20', 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $booking = $item->bookings()->firstOrFail();
        $this->assertSame(3, $booking->rental_days);
        $this->actingAs($item->user)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $days = collect($this->getJson(route('items.calendar', [$item, 'month' => '2026-09']))->assertOk()->json('days'))->keyBy('date');
        foreach (['2026-09-18', '2026-09-19', '2026-09-20'] as $date) {
            $this->assertSame(0, $days[$date]['available_quantity']);
        }
        $this->assertSame(1, $days['2026-09-21']['available_quantity']);
    }

    public function test_rental_today_uses_wib_even_before_utc_date_changes(): void
    {
        $this->travelTo(Carbon::parse('2026-09-18T17:01:00Z'));
        $item = Item::factory()->create();
        $this->getJson(route('items.calendar', [$item, 'month' => '2026-09']))->assertOk()->assertJsonPath('today', '2026-09-19');
        $this->actingAs(User::factory()->create())->post(route('items.bookings.store', $item), [
            'start_date' => '2026-09-18', 'end_date' => '2026-09-20', 'quantity' => 1,
        ])->assertSessionHasErrors('start_date');
        $this->post(route('items.bookings.store', $item), [
            'start_date' => '2026-09-19', 'end_date' => '2026-09-20', 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $item->bookings()->firstOrFail()->rental_days);
    }

    public function test_owner_delivery_settings_require_pickup_address_for_publication(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        ItemPhoto::factory()->for($item)->create();
        $payload = [
            'name' => $item->name, 'category_id' => $item->category_id, 'description' => $item->description,
            'condition' => $item->condition->value, 'daily_price' => $item->daily_price, 'city' => $item->city,
            'address' => '', 'quantity' => $item->quantity, 'is_active' => true,
            'delivery_enabled' => true, 'free_delivery' => true,
        ];
        $this->actingAs($owner)->put(route('my-items.update', $item), $payload)->assertSessionHasErrors('address');
        $payload['address'] = 'Jalan Pengambilan Baru 11';
        $this->put(route('my-items.update', $item), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($item->fresh()->delivery_enabled);
        $this->assertTrue($item->fresh()->free_delivery);
        $payload['delivery_enabled'] = false;
        $this->put(route('my-items.update', $item->fresh()), $payload)->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->delivery_enabled);
        $this->assertFalse($item->fresh()->free_delivery);
    }

    public function test_calendar_blocks_inclusive_dates_and_reopens_after_owner_cancellation(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $days = collect($this->getJson(route('items.calendar', [$item, 'month' => '2026-09']))->assertOk()->json('days'))->keyBy('date');
        $this->assertSame(0, $days['2026-09-20']['available_quantity']);
        $this->assertSame(0, $days['2026-09-22']['available_quantity']);
        $this->assertSame(1, $days['2026-09-23']['available_quantity']);
        $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertSee('Jalan Pemilik Rahasia 99');
        $this->actingAs($owner)->patch(route('incoming-bookings.cancel', $booking), ['cancellation_reason' => 'Barang perlu perawatan.'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('owner', $booking->fresh()->cancelled_by);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-09-20', '2026-09-22'));
    }

    public function test_staggered_reservations_use_daily_peak_not_sum_of_all_overlaps(): void
    {
        $this->travelTo(Carbon::parse('2026-09-18T01:00:00Z'));
        $item = Item::factory()->create(['quantity' => 2]);
        foreach (['2026-09-20', '2026-09-22'] as $date) {
            Booking::factory()->approved()->for($item)->create(['start_date' => $date, 'end_date' => $date, 'quantity' => 1]);
        }
        $this->getJson(route('items.availability', [$item, 'start_date' => '2026-09-20', 'end_date' => '2026-09-22']))->assertOk()->assertJsonPath('available_quantity', 1);
    }

    public function test_booking_cannot_cross_an_unavailable_day_even_with_free_endpoints(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        Booking::factory()->approved()->for($item)->create(['start_date' => '2026-09-21', 'end_date' => '2026-09-21', 'quantity' => 1]);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasErrors('quantity');
        $this->actingAs($renter)->post(route('items.bookings.store', $item), ['start_date' => '2026-09-20', 'end_date' => '2026-09-22', 'quantity' => 1])->assertSessionHasErrors('quantity');
    }

    public function test_paid_delivery_requires_quote_and_renter_confirmation_even_for_zero_fee(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(delivery: true);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasErrors('delivery_fee');
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 0])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(BookingStatus::AwaitingRenterConfirmation, $booking->status);
        $this->assertNull($booking->approved_at);
        $this->assertNull($booking->invoice_snapshot);
        $this->assertSame(0, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-09-20', '2026-09-22'));
        $this->actingAs($renter)->get(route('bookings.invoice', $booking))->assertForbidden();
        $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertSee('Setujui Total')->assertDontSee('Jalan Pemilik Rahasia 99');
        $this->patch(route('my-bookings.confirm-delivery', $booking))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->renter_confirmed_at);
        $this->assertSame('0.00', $booking->fresh()->delivery_fee);
    }

    public function test_delivery_fee_is_once_per_booking_and_invoice_is_frozen(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(delivery: true, quantity: 2);
        $snapshot = $booking->fulfillment_snapshot;
        $owner->update(['name' => 'Changed Owner']);
        $renter->update(['name' => 'Changed Renter', 'address' => 'New profile address']);
        $item->update(['daily_price' => 900000, 'address' => 'Changed pickup address', 'delivery_enabled' => false, 'free_delivery' => true]);
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 35000])->assertSessionHasNoErrors();
        $this->actingAs($renter)->patch(route('my-bookings.confirm-delivery', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('655000.00', $booking->total_amount);
        $this->assertSame($snapshot, $booking->fulfillment_snapshot);
        $this->assertSame($snapshot['owner'], $booking->invoice_snapshot['owner']);
        $this->assertSame('Jalan Penyewa 10', $booking->invoice_snapshot['recipient']['address']);
        $this->assertSame('Jalan Pemilik Rahasia 99', $booking->invoice_snapshot['pickup']['address']);
        $this->assertSame('35000.00', $booking->invoice_snapshot['booking']['delivery_fee']);
        $this->get(route('bookings.invoice', $booking))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('INV-'.$booking->booking_code.'.pdf');
        $html = view('bookings.invoice', ['booking' => $booking, 'invoice' => $booking->invoice_snapshot])->render();
        $this->assertStringContainsString('Rp 655.000', $html);
        $this->assertStringNotContainsString('Changed Owner', $html);
        $this->actingAs($owner)->patch(route('incoming-bookings.cancel', $booking), ['cancellation_reason' => 'Tidak dapat mengirim.'])->assertSessionHasNoErrors();
        $this->assertSame($booking->invoice_snapshot, $booking->fresh()->invoice_snapshot);
        $this->get(route('bookings.invoice', $booking))->assertOk();
        $this->assertStringContainsString('DIBATALKAN', view('bookings.invoice', ['booking' => $booking->fresh(), 'invoice' => $booking->invoice_snapshot])->render());
    }

    public function test_free_delivery_does_not_need_quote_or_second_approval(): void
    {
        [$booking, $owner] = $this->booking(delivery: true, free: true);
        $booking->item->update(['free_delivery' => false]);
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 10000])->assertForbidden();
        $this->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->renter_confirmed_at);
        $this->assertSame('0.00', $booking->fresh()->delivery_fee);
    }

    public function test_quote_cannot_be_changed_and_decline_releases_stock(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(delivery: true);
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 25000])->assertSessionHasNoErrors();
        $this->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 99000])->assertForbidden();
        $this->actingAs($renter)->patch(route('my-bookings.decline-delivery', $booking))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('25000.00', $booking->fresh()->delivery_fee);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-09-20', '2026-09-22'));
    }

    public function test_expired_quote_releases_stock_without_scheduler_and_cannot_be_confirmed(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(delivery: true);
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 25000])->assertSessionHasNoErrors();
        $this->travelTo($booking->fresh()->quote_expires_at);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-09-20', '2026-09-22'));
        $this->actingAs($renter)->get(route('my-bookings.index', ['status' => 'expired']))->assertSee($booking->booking_code)->assertSee('Kedaluwarsa');
        $this->patch(route('my-bookings.confirm-delivery', $booking))->assertSessionHasErrors('booking');
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertNull($booking->fresh()->invoice_snapshot);
    }

    public function test_scheduler_expires_quotes_idempotently_and_caps_hold_at_start_day_end_wib(): void
    {
        [$booking, $owner] = $this->booking(delivery: true);
        $this->travelTo(Carbon::parse('2026-09-20T10:00:00Z'));
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 10000])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-20 23:59:59', $booking->fresh()->quote_expires_at->timezone(Booking::RentalTimezone)->format('Y-m-d H:i:s'));
        $this->travelTo(Carbon::parse('2026-09-20T17:00:00Z'));
        $this->artisan('bookings:expire-quotes')->assertSuccessful();
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->artisan('bookings:expire-quotes')->expectsOutput('0 booking kedaluwarsa.')->assertSuccessful();
    }

    public function test_handover_prevents_cancellation_and_early_return_frees_future_dates(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->markAsPaid($booking);
        $this->patch(route('incoming-bookings.hand-over', $booking))->assertSessionHasErrors('booking');
        $this->travelTo(Carbon::parse('2026-09-20T01:00:00Z'));
        $this->patch(route('incoming-bookings.hand-over', $booking))->assertSessionHasNoErrors();
        $this->patch(route('incoming-bookings.hand-over', $booking))->assertForbidden();
        $this->patch(route('incoming-bookings.cancel', $booking), ['cancellation_reason' => 'Tidak jadi'])->assertForbidden();
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertForbidden();
        $this->actingAs($owner)->patch(route('incoming-bookings.complete', $booking))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-09-21', '2026-09-22'));
        $this->get(route('bookings.invoice', $booking))->assertOk();
    }

    public function test_overdue_units_remain_reserved_until_return_confirmation(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->markAsPaid($booking);
        $this->travelTo(Carbon::parse('2026-09-20T01:00:00Z'));
        $this->patch(route('incoming-bookings.hand-over', $booking))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-09-22T01:00:00Z'));
        $this->get(route('incoming-bookings.index'))->assertSee('Berakhir Hari Ini');
        $this->travelTo(Carbon::parse('2026-09-23T01:00:00Z'));
        $this->assertSame(0, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-10-01', '2026-10-05'));
        $this->get(route('incoming-bookings.show', $booking))->assertSee('Lewat Jatuh Tempo')->assertSee('Konfirmasi Barang Kembali');
        $this->patch(route('incoming-bookings.complete', $booking))->assertSessionHasNoErrors();
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-10-01', '2026-10-05'));
    }

    public function test_payment_review_only_reserves_original_period_when_item_was_not_handed_over(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23T01:00:00Z'));
        $item = Item::factory()->create(['quantity' => 1]);
        Booking::factory()->for($item)->create([
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 1,
            'status' => BookingStatus::PaymentReview->value,
            'handed_over_at' => null,
        ]);

        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, '2026-10-01', '2026-10-05'));
    }

    public function test_new_bookings_require_location_and_valid_delivery_choice_and_recipient(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $payload = ['start_date' => '2026-09-20', 'end_date' => '2026-09-22', 'quantity' => 1];
        $this->actingAs($renter)->post(route('items.bookings.store', $item), $payload + ['fulfillment_method' => 'delivery'])
            ->assertSessionHasErrors(['recipient_name', 'recipient_phone', 'recipient_city', 'recipient_address']);
        $this->post(route('items.bookings.store', $item), $payload + $this->recipient() + ['fulfillment_method' => 'delivery'])->assertSessionHasErrors('fulfillment_method');
        $item->update(['address' => null]);
        $this->post(route('items.bookings.store', $item), $payload)->assertSessionHasErrors('item');
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasErrors('booking');
    }

    public function test_private_addresses_and_invoice_are_not_exposed_to_other_users_or_calendar(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(delivery: true, free: true);
        $this->get(route('items.show', $item))->assertDontSee('Jalan Pemilik Rahasia 99');
        $this->getJson(route('items.calendar', [$item, 'month' => '2026-09']))->assertOk()->assertDontSee('Jalan Pemilik Rahasia 99')->assertDontSee($renter->email);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create())->get(route('bookings.invoice', $booking))->assertForbidden();
        foreach (['quote-delivery', 'cancel', 'hand-over', 'complete'] as $action) {
            $this->patch(route('incoming-bookings.'.$action, $booking), ['delivery_fee' => 10000, 'cancellation_reason' => 'Not mine'])->assertForbidden();
        }
        $this->patch(route('my-bookings.confirm-delivery', $booking))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('bookings.invoice', $booking))->assertOk();
        $this->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Jalan Penyewa 10');
    }

    public function test_stock_cannot_be_reduced_below_live_reservations(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking(quantity: 2);
        ItemPhoto::factory()->for($item)->create();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->put(route('my-items.update', $item), [
            'name' => $item->name, 'category_id' => $item->category_id, 'description' => $item->description,
            'condition' => $item->condition->value, 'daily_price' => $item->daily_price, 'city' => $item->city,
            'address' => $item->address, 'quantity' => 1, 'is_active' => true,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_invalid_quote_fees_and_missing_cancellation_reason_are_rejected(): void
    {
        [$booking, $owner] = $this->booking(delivery: true);
        foreach ([-1, 'invalid', 1000000000, 1.234] as $fee) {
            $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => $fee])->assertSessionHasErrors('delivery_fee');
        }
        $this->patch(route('incoming-bookings.cancel', $booking))->assertSessionHasErrors('cancellation_reason');
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_owner_is_directed_to_add_an_active_payment_method_before_quoting_delivery(): void
    {
        [$booking, $owner] = $this->booking(delivery: true);
        $owner->paymentMethods()->delete();

        $this->actingAs($owner)
            ->get(route('incoming-bookings.show', $booking))
            ->assertOk()
            ->assertSee('Metode pembayaran belum tersedia')
            ->assertSee('Atur Metode Pembayaran')
            ->assertDontSee('Kirim Tawaran Ongkir');

        $this->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 20000])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'payment_method' => 'Tambahkan metode pembayaran aktif sebelum mengirim tawaran ongkir.',
            ]);

        $this->get(route('incoming-bookings.show', $booking))
            ->assertOk()
            ->assertSee('Tambahkan metode pembayaran aktif sebelum mengirim tawaran ongkir.');
    }

    /** @return array{Booking, User, User, Item} */
    private function booking(bool $delivery = false, bool $free = false, int $quantity = 1): array
    {
        $this->travelTo(Carbon::parse('2026-09-18T01:00:00Z'));
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create([
            'quantity' => $quantity, 'daily_price' => 100000, 'deposit_amount' => 10000,
            'address' => 'Jalan Pemilik Rahasia 99', 'delivery_enabled' => $delivery, 'free_delivery' => $free,
        ]);
        $this->actingAs($renter)->post(route('items.bookings.store', $item), [
            'start_date' => '2026-09-20', 'end_date' => '2026-09-22', 'quantity' => $quantity,
            'fulfillment_method' => $delivery ? 'delivery' : 'pickup',
        ] + ($delivery ? $this->recipient() : []))->assertSessionHasNoErrors();
        $booking = Booking::query()->where('item_id', $item->id)->firstOrFail();

        return [$booking, $owner, $renter, $item];
    }

    /** @return array<string, string> */
    private function recipient(): array
    {
        return ['recipient_name' => 'Penerima', 'recipient_phone' => '08123456789', 'recipient_city' => 'Medan', 'recipient_address' => 'Jalan Penyewa 10'];
    }

    private function markAsPaid(Booking $booking): void
    {
        $booking->update([
            'status' => BookingStatus::Approved,
            'approved_at' => now(),
            'payment_verified_at' => now(),
            'payment_legacy' => true,
        ]);
    }
}
