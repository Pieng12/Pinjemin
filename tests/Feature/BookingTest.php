<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Models\User;
use App\Models\UserPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-16');
    }

    public function test_guest_cannot_create_booking(): void
    {
        $item = Item::factory()->create();

        $this->post(route('items.bookings.store', $item), $this->validBookingPayload())
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_book_their_own_item(): void
    {
        $this->travelTo('2026-09-16');
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->from(route('items.show', $item))
            ->post(route('items.bookings.store', $item), $this->validBookingPayload())
            ->assertRedirect(route('items.show', $item))
            ->assertSessionHasErrors('item');
    }

    public function test_user_can_create_booking_for_another_users_item(): void
    {
        $this->travelTo('2026-09-16');
        $renter = User::factory()->create();
        $item = Item::factory()->for(User::factory())->create();

        $this->actingAs($renter)
            ->post(route('items.bookings.store', $item), $this->validBookingPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'item_id' => $item->id,
            'renter_user_id' => $renter->id,
        ]);
    }

    public function test_new_booking_starts_as_pending(): void
    {
        $booking = $this->createBooking();

        $this->assertSame(BookingStatus::Pending, $booking->status);
    }

    public function test_booking_uses_item_price_snapshot(): void
    {
        $booking = $this->createBooking(itemAttributes: ['daily_price' => 100000]);
        $booking->item->update(['daily_price' => 150000]);

        $this->assertSame('100000.00', $booking->refresh()->daily_price);
    }

    public function test_inclusive_rental_day_calculation_is_correct(): void
    {
        $booking = $this->createBooking(payload: [
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 1,
        ]);

        $this->assertSame(3, $booking->rental_days);
    }

    public function test_total_calculation_is_correct(): void
    {
        $booking = $this->createBooking(
            itemAttributes: ['daily_price' => 100000, 'deposit_amount' => 200000],
            payload: ['quantity' => 2],
        );

        $this->assertSame('600000.00', $booking->subtotal);
        $this->assertSame('1000000.00', $booking->total_amount);
    }

    public function test_deposit_is_calculated_per_unit(): void
    {
        $booking = $this->createBooking(
            itemAttributes: ['deposit_amount' => 200000],
            payload: ['quantity' => 2],
        );

        $this->assertSame('400000.00', $booking->deposit_amount);
    }

    public function test_quantity_above_stock_is_rejected(): void
    {
        $this->travelTo('2026-09-16');
        $renter = User::factory()->create();
        $item = Item::factory()->for(User::factory())->create(['quantity' => 1]);

        $this->actingAs($renter)
            ->from(route('items.show', $item))
            ->post(route('items.bookings.store', $item), $this->validBookingPayload(['quantity' => 2]))
            ->assertRedirect(route('items.show', $item))
            ->assertSessionHasErrors('quantity');
    }

    public function test_inactive_item_cannot_be_booked(): void
    {
        $this->travelTo('2026-09-16');
        $renter = User::factory()->create();
        $item = Item::factory()->inactive()->for(User::factory())->create();

        $this->actingAs($renter)
            ->from(route('items.show', $item))
            ->post(route('items.bookings.store', $item), $this->validBookingPayload())
            ->assertSessionHasErrors('item');
    }

    public function test_owner_can_approve_pending_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->createBooking(owner: $owner);

        $this->actingAs($owner)
            ->patch(route('incoming-bookings.approve', $booking))
            ->assertRedirect(route('incoming-bookings.show', $booking));

        $this->assertSame(BookingStatus::AwaitingPayment, $booking->refresh()->status);
    }

    public function test_non_owner_cannot_approve_booking(): void
    {
        $booking = $this->createBooking();

        $this->actingAs(User::factory()->create())
            ->patch(route('incoming-bookings.approve', $booking))
            ->assertForbidden();
    }

    public function test_owner_can_reject_pending_booking(): void
    {
        $owner = User::factory()->create();
        $booking = $this->createBooking(owner: $owner);

        $this->actingAs($owner)
            ->patch(route('incoming-bookings.reject', $booking), ['owner_note' => 'Tanggal belum cocok.'])
            ->assertRedirect(route('incoming-bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertSame('Tanggal belum cocok.', $booking->owner_note);
    }

    public function test_non_owner_cannot_reject_booking(): void
    {
        $booking = $this->createBooking();

        $this->actingAs(User::factory()->create())
            ->patch(route('incoming-bookings.reject', $booking))
            ->assertForbidden();
    }

    public function test_renter_can_cancel_their_booking(): void
    {
        $renter = User::factory()->create();
        $booking = $this->createBooking(renter: $renter);

        $this->actingAs($renter)
            ->patch(route('my-bookings.cancel', $booking))
            ->assertRedirect(route('my-bookings.show', $booking));

        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_other_user_cannot_cancel_booking(): void
    {
        $booking = $this->createBooking();

        $this->actingAs(User::factory()->create())
            ->patch(route('my-bookings.cancel', $booking))
            ->assertForbidden();
    }

    public function test_approved_overlapping_booking_reduces_availability(): void
    {
        $this->travelTo('2026-09-16');
        $item = Item::factory()->create(['quantity' => 5]);
        Booking::factory()->approved()->for($item)->create([
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 2,
        ]);

        $this->getJson(route('items.availability', $item).'?start_date=2026-09-21&end_date=2026-09-22')
            ->assertOk()
            ->assertJsonPath('available_quantity', 3);
    }

    public function test_pending_booking_does_not_reduce_availability(): void
    {
        $this->travelTo('2026-09-16');
        $item = Item::factory()->create(['quantity' => 5]);
        Booking::factory()->for($item)->create([
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 2,
            'status' => BookingStatus::Pending->value,
        ]);

        $this->getJson(route('items.availability', $item).'?start_date=2026-09-21&end_date=2026-09-22')
            ->assertOk()
            ->assertJsonPath('available_quantity', 5);
    }

    public function test_approval_fails_when_stock_is_no_longer_available(): void
    {
        $owner = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create(['quantity' => 1]);
        $first = Booking::factory()->for($item)->create($this->bookingAttributes());
        $second = Booking::factory()->for($item)->create($this->bookingAttributes());

        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $first));

        $this->actingAs($owner)
            ->from(route('incoming-bookings.show', $second))
            ->patch(route('incoming-bookings.approve', $second))
            ->assertRedirect(route('incoming-bookings.show', $second))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(BookingStatus::Pending, $second->refresh()->status);
    }

    public function test_two_overlapping_bookings_cannot_exceed_quantity(): void
    {
        $owner = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create(['quantity' => 1]);
        $approved = Booking::factory()->for($item)->create($this->bookingAttributes([
            'status' => BookingStatus::Approved->value,
            'approved_at' => now(),
        ]));
        $pending = Booking::factory()->for($item)->create($this->bookingAttributes());

        $this->actingAs($owner)
            ->from(route('incoming-bookings.show', $pending))
            ->patch(route('incoming-bookings.approve', $pending))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(BookingStatus::Approved, $approved->refresh()->status);
        $this->assertSame(BookingStatus::Pending, $pending->refresh()->status);
    }

    public function test_non_overlapping_booking_can_be_approved(): void
    {
        $owner = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create(['quantity' => 1]);
        Booking::factory()->for($item)->create($this->bookingAttributes([
            'status' => BookingStatus::Approved->value,
            'approved_at' => now(),
        ]));
        $pending = Booking::factory()->for($item)->create($this->bookingAttributes([
            'start_date' => '2026-09-23',
            'end_date' => '2026-09-24',
        ]));

        $this->actingAs($owner)
            ->patch(route('incoming-bookings.approve', $pending))
            ->assertRedirect(route('incoming-bookings.show', $pending));

        $this->assertSame(BookingStatus::AwaitingPayment, $pending->refresh()->status);
    }

    public function test_item_with_booking_cannot_be_hard_deleted(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner)->create();
        Booking::factory()->for($item)->create($this->bookingAttributes());

        $this->actingAs($owner)
            ->from(route('my-items.show', $item))
            ->delete(route('my-items.destroy', $item))
            ->assertRedirect(route('my-items.show', $item))
            ->assertSessionHasErrors('item');

        $this->assertModelExists($item);
    }

    public function test_booking_remains_when_item_is_deactivated(): void
    {
        $owner = User::factory()->create();
        $item = Item::factory()->for($owner)->create();
        $booking = Booking::factory()->approved()->for($item)->create($this->bookingAttributes());

        $this->actingAs($owner)
            ->patch(route('my-items.toggle-status', $item))
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(ItemStatus::Inactive, $item->status);
        $this->assertModelExists($booking);
    }

    public function test_admin_can_view_booking_list(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->createBooking();

        $this->actingAs($admin)
            ->get(route('admin.bookings.index'))
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    /**
     * @param  array<string, mixed>  $itemAttributes
     * @param  array<string, mixed>  $payload
     */
    private function createBooking(?User $owner = null, ?User $renter = null, array $itemAttributes = [], array $payload = []): Booking
    {
        $this->travelTo('2026-09-16');
        $owner ??= User::factory()->create();
        $renter ??= User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create(array_merge([
            'daily_price' => 100000,
            'deposit_amount' => 200000,
            'quantity' => 3,
        ], $itemAttributes));

        $this->actingAs($renter)
            ->post(route('items.bookings.store', $item), $this->validBookingPayload($payload))
            ->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides */
    private function validBookingPayload(array $overrides = []): array
    {
        return array_merge([
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 1,
            'renter_note' => 'Tolong disiapkan sebelum siang.',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function bookingAttributes(array $overrides = []): array
    {
        return array_merge([
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
            'quantity' => 1,
            'daily_price' => 100000,
            'rental_days' => 3,
            'subtotal' => 300000,
            'deposit_amount' => 200000,
            'total_amount' => 500000,
            'status' => BookingStatus::Pending->value,
        ], $overrides);
    }
}
