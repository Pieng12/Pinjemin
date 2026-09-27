<?php

namespace Tests\Feature;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Models\Booking;
use App\Models\Item;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\PaymentPlan;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-10-01 08:00:00', Booking::RentalTimezone)->utc());
    }

    public function test_owner_can_manage_private_payment_methods_and_qris(): void
    {
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)->post(route('payment-methods.store'), [
            'type' => 'qris', 'account_name' => 'Toko Kamera', 'is_active' => 1, 'is_primary' => 1,
            'qris_image' => UploadedFile::fake()->image('qris.png', 500, 500),
        ])->assertSessionHasNoErrors();
        $method = $owner->paymentMethods()->firstOrFail();
        Storage::disk('local')->assertExists($method->qris_path);
        $this->get(route('payment-methods.image', $method))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('payment-methods.image', $method))->assertForbidden();
        $this->actingAs($owner)->put(route('payment-methods.update', $method), [
            'type' => 'bank', 'provider' => 'BCA', 'account_name' => 'Pemilik',
            'account_identifier' => '0012345678', 'is_active' => 1, 'is_primary' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('bank', $method->fresh()->type->value);
        $this->assertNull($method->fresh()->qris_path);
    }

    public function test_only_ten_active_methods_are_allowed_but_inactive_methods_can_be_saved(): void
    {
        $owner = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->count(10)->sequence(fn ($sequence): array => [
            'is_primary' => $sequence->index === 0,
            'sort_order' => $sequence->index,
        ])->create();

        $payload = [
            'type' => 'bank',
            'provider' => 'BCA',
            'account_name' => 'Pemilik',
            'account_identifier' => '1234567890',
            'is_active' => 1,
        ];
        $this->actingAs($owner)->post(route('payment-methods.store'), $payload)
            ->assertSessionHasErrors('type');
        $this->post(route('payment-methods.store'), array_merge($payload, ['is_active' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $owner->paymentMethods()->where('is_active', true)->count());
        $this->assertSame(11, $owner->paymentMethods()->count());
    }

    public function test_booking_keeps_payment_method_snapshot_after_owner_changes_profile_method(): void
    {
        [$booking, $owner] = $this->booking();
        $method = $owner->paymentMethods()->firstOrFail();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $snapshot = $booking->fresh()->payment_methods_snapshot;

        $method->update(['provider' => 'Mandiri', 'account_identifier' => '9999999999']);

        $this->assertSame('BCA', $booking->fresh()->payment_methods_snapshot[0]['provider']);
        $this->assertSame($snapshot, $booking->fresh()->payment_methods_snapshot);
    }

    public function test_full_payment_is_private_and_requires_owner_verification(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->status);
        $this->assertNotNull($booking->invoice_snapshot);
        $this->get(route('bookings.invoice', $booking))->assertOk();
        $this->get(route('bookings.payment-receipt', $booking))->assertForbidden();
        $this->assertSame(0, app(AvailabilityService::class)->getAvailableQuantity($item, $booking->start_date, $booking->end_date));
        $this->actingAs($renter)->get(route('my-bookings.show', $booking))
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Kirim Bukti Pembayaran')
            ->assertSee('BCA')
            ->assertSee('Alur Booking');

        $this->submitProof($booking, $renter, PaymentPlan::FullTransfer);
        $payment = $booking->payments()->firstOrFail();
        $this->assertSame(BookingStatus::PaymentReview, $booking->fresh()->status);
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('bookings.payment-proof', [$booking, $payment]))->assertForbidden();
        $this->actingAs($owner)->get(route('bookings.payment-proof', [$booking, $payment]))->assertOk();

        $this->patch(route('incoming-bookings.payments.review', [$booking, $payment]), ['decision' => 'verify'])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame(320000.0, (float) $booking->verifiedPayments()->sum('amount'));
        $this->get(route('bookings.payment-receipt', $booking))->assertOk()->assertDownload('PAY-'.$booking->booking_code.'.pdf');
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $payment]), ['decision' => 'verify'])->assertForbidden();
    }

    public function test_rejected_deposit_keeps_history_and_can_be_resubmitted_then_settled(): void
    {
        [$booking, $owner, $renter] = $this->booking(deposit: true);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->submitProof($booking, $renter, PaymentPlan::DepositTransfer);
        $first = $booking->payments()->firstOrFail();
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $first]), [
            'decision' => 'reject', 'owner_comment' => 'Nominal pada gambar tidak terlihat.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(BookingPaymentStatus::Rejected, $first->fresh()->status);
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);

        $this->submitProof($booking, $renter);
        $deposit = $booking->payments()->reorder()->latest('id')->firstOrFail();
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $deposit]), ['decision' => 'verify'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::PartiallyPaid, $booking->fresh()->status);
        $this->assertSame(20000.0, (float) $booking->fresh()->verifiedPayments()->sum('amount'));

        $this->submitProof($booking, $renter);
        $balance = $booking->payments()->reorder()->latest('id')->firstOrFail();
        $this->assertSame(300000.0, (float) $balance->amount);
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $balance]), ['decision' => 'verify'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $this->assertSame(3, $booking->payments()->count());
    }

    public function test_deposit_cash_is_recorded_together_with_handover(): void
    {
        [$booking, $owner, $renter] = $this->booking(deposit: true, cash: true);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->submitProof($booking, $renter, PaymentPlan::DepositCash);
        $deposit = $booking->payments()->firstOrFail();
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $deposit]), ['decision' => 'verify'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('incoming-bookings.cash-and-hand-over', $booking))->assertSessionHasErrors('payment');
        $this->travelTo($booking->start_date->copy()->timezone(Booking::RentalTimezone)->startOfDay()->utc());
        $this->actingAs($owner)->patch(route('incoming-bookings.cash-and-hand-over', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNotNull($booking->handed_over_at);
        $this->assertSame('cash', $booking->payments()->reorder()->latest('id')->firstOrFail()->channel);
        $this->assertSame((float) $booking->total_amount, (float) $booking->verifiedPayments()->sum('amount'));
    }

    public function test_submitted_proof_pauses_expiry_but_unpaid_booking_expires(): void
    {
        [$review, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $review))->assertSessionHasNoErrors();
        $this->submitProof($review, $renter, PaymentPlan::FullTransfer);
        $this->travelTo($review->fresh()->payment_due_at->addMinute());
        $this->artisan('bookings:expire-quotes')->assertSuccessful();
        $this->assertSame(BookingStatus::PaymentReview, $review->fresh()->status);

        [$unpaid, $secondOwner, $secondRenter, $secondItem] = $this->booking(startOffset: 4);
        $this->actingAs($secondOwner)->patch(route('incoming-bookings.approve', $unpaid))->assertSessionHasNoErrors();
        $this->travelTo($unpaid->fresh()->payment_due_at->addMinute());
        $this->artisan('bookings:expire-quotes')->assertSuccessful();
        $this->assertSame(BookingStatus::Expired, $unpaid->fresh()->status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($secondItem, $unpaid->start_date, $unpaid->end_date));
    }

    public function test_cancellation_after_verified_payment_requires_full_refund(): void
    {
        [$booking, $owner, $renter, $item] = $this->booking();
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking));
        $this->submitProof($booking, $renter, PaymentPlan::FullTransfer);
        $payment = $booking->payments()->firstOrFail();
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $payment]), ['decision' => 'verify']);
        $this->actingAs($owner)->patch(route('incoming-bookings.cancel', $booking), ['cancellation_reason' => 'Barang rusak sebelum diserahkan.'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $booking->fresh()->refund_status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($item, $booking->start_date, $booking->end_date));

        $this->actingAs($owner)->post(route('incoming-bookings.refund', $booking), [
            'refunded_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'),
            'proof' => UploadedFile::fake()->image('refund.jpg'),
            'note' => 'Dikembalikan penuh.',
        ])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('refunded', $booking->refund_status);
        $this->assertSame((float) $booking->verifiedPayments()->sum('amount'), (float) $booking->refund->amount);
        $this->actingAs($renter)->get(route('bookings.refund-proof', $booking))->assertOk();
    }

    /** @return array{Booking, User, User, Item} */
    private function booking(bool $deposit = false, bool $cash = false, int $startOffset = 2): array
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $item = Item::factory()->for($owner)->create([
            'daily_price' => 100000, 'deposit_amount' => 20000, 'quantity' => 1,
            'allow_deposit_payment' => $deposit, 'allow_cash_balance' => $cash,
        ]);
        $start = now(Booking::RentalTimezone)->addDays($startOffset)->toDateString();
        $booking = app(BookingService::class)->create($renter, $item, [
            'start_date' => $start,
            'end_date' => Carbon::parse($start)->addDays(2)->toDateString(),
            'quantity' => 1,
        ]);

        return [$booking, $owner, $renter, $item];
    }

    private function submitProof(Booking $booking, User $renter, ?PaymentPlan $plan = null): void
    {
        $payload = [
            'method_index' => 0,
            'transferred_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'),
            'proof' => UploadedFile::fake()->image('transfer.jpg', 800, 600),
        ];
        if ($plan) {
            $payload['payment_plan'] = $plan->value;
        }
        $this->actingAs($renter)->post(route('my-bookings.payments.store', $booking), $payload)->assertSessionHasNoErrors();
    }
}
