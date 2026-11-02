<?php

namespace Tests\Feature;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Item;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-01 09:00:00', Booking::RentalTimezone)->utc());
    }

    public function test_admins_share_platform_methods_and_owners_cannot_modify_them(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = ['type' => 'bank', 'provider' => 'Bank Pinjemin', 'account_name' => 'Pinjemin', 'account_identifier' => '111222333', 'is_active' => 1, 'is_primary' => 1];
        $this->actingAs($admin)->post(route('admin.payment-methods.store'), $payload)->assertSessionHasNoErrors();
        $method = UserPaymentMethod::query()->where('is_platform', true)->firstOrFail();
        $this->assertSame(0, $admin->paymentMethods()->count());
        $otherAdmin = User::factory()->admin()->create();
        $this->actingAs($otherAdmin)->get(route('admin.payment-methods.index'))->assertOk()->assertSee('111222333');
        $this->put(route('admin.payment-methods.update', $method), array_replace($payload, ['provider' => 'Bank Baru']))->assertSessionHasNoErrors();
        $this->assertSame('Bank Baru', $method->fresh()->provider);
        $owner = User::factory()->create();
        $this->actingAs($owner)->put(route('payment-methods.update', $method), $payload)->assertForbidden();
        $this->post(route('admin.payment-methods.store'), $payload)->assertForbidden();
        $this->delete(route('payment-methods.destroy', $method))->assertForbidden();
    }

    public function test_new_booking_uses_admin_destination_freezes_owner_destination_and_charges_five_percent_of_rent_only(): void
    {
        [$booking, $owner, $renter, $admin, $platform] = $this->booking(delivery: true);
        $this->actingAs($owner)->get(route('incoming-bookings.show', $booking))->assertOk()->assertSee('Komisi 5%');
        $this->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 20000])->assertSessionHasNoErrors();
        $this->actingAs($renter)->patch(route('my-bookings.confirm-delivery', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('320000.00', $booking->total_amount);
        $this->assertSame('15000.00', $booking->payout->commission_amount);
        $this->assertSame('305000.00', $booking->payout->amount);
        $this->assertSame('111222333', $booking->payment_methods_snapshot[0]['account_identifier']);
        $this->assertSame('999888777', $booking->payout->destination_snapshot['account_identifier']);
        $platform->update(['account_identifier' => 'changed-admin']);
        $owner->paymentMethods()->first()->update(['account_identifier' => 'changed-owner']);
        $this->assertSame('111222333', $booking->fresh()->payment_methods_snapshot[0]['account_identifier']);
        $this->assertSame('999888777', $booking->fresh()->payout->destination_snapshot['account_identifier']);
        $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertOk()->assertSee('111222333')->assertDontSee('999888777')->assertDontSee('value="deposit_cash"', false);
    }

    public function test_admin_verifies_full_payment_then_pays_owner_after_return_once(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $payment = $this->proof($booking, $renter);
        $this->actingAs($owner)->patch(route('incoming-bookings.payments.review', [$booking, $payment]), ['decision' => 'verify'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'verify'])->assertSessionHasErrors('transaction_reference');
        $this->verify($booking, $payment, $admin, 'BANK-IN-001');
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasErrors('payout');
        $this->returnItem($booking, $owner);
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasNoErrors();
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasErrors('payout');
        $payload = $this->payoutPayload();
        $this->patch(route('bookings.payout', $booking), $payload)->assertSessionHasNoErrors();
        $payout = $booking->fresh()->payout;
        $this->assertSame('paid', $payout->status);
        $this->assertSame('285000.00', $payout->amount);
        Storage::disk('local')->assertExists($payout->proof_path);
        $this->patch(route('bookings.payout', $booking), $this->payoutPayload())->assertSessionHasErrors('payout');
        $this->assertCount(1, Storage::disk('local')->allFiles('payout-proofs'));
        $this->get(route('admin.finance'))->assertOk()->assertSee('Rp 15.000');
        $this->actingAs($owner)->get(route('bookings.payout-receipt', $booking))->assertOk()->assertDownload('OUT-'.$booking->booking_code.'.pdf');
        $this->get(route('bookings.payout-proof', $booking))->assertOk();
        $this->patch(route('bookings.payout', $booking), ['action' => 'receive'])->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->payout->received_at);
        $this->actingAs($renter)->get(route('bookings.payout-proof', $booking))->assertForbidden();
        $this->get(route('bookings.payout-receipt', $booking))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.finance'))->assertForbidden();
    }

    public function test_deposit_and_balance_go_to_admin_and_cash_is_rejected(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $this->actingAs($renter)->post(route('my-bookings.payments.store', $booking), $this->proofPayload('deposit_cash'))->assertSessionHasErrors('payment_plan');
        $deposit = $this->proof($booking, $renter, 'deposit_transfer');
        $this->assertSame('50000.00', $deposit->amount);
        $this->verify($booking, $deposit, $admin, 'BANK-DP-001');
        $this->assertSame(BookingStatus::PartiallyPaid, $booking->fresh()->status);
        $this->assertSame(250000.0, $booking->fresh()->remainingAmount());
        $page = $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $verified = (new \DOMXPath($document))->query('//dt[text()="Terverifikasi"]/following-sibling::dd')->item(0);
        $this->assertSame('Rp 50.000', trim($verified->textContent));
        $this->actingAs($owner)->patch(route('incoming-bookings.hand-over', $booking))->assertForbidden();
        $balance = $this->proof($booking, $renter);
        $this->assertSame('250000.00', $balance->amount);
        $this->verify($booking, $balance, $admin, 'BANK-BALANCE-001');
        $this->assertSame(0.0, $booking->fresh()->remainingAmount());
        $this->actingAs($renter)->get(route('bookings.payment-receipt', $booking))->assertOk();
    }

    public function test_admin_rejects_with_comment_and_renter_can_resubmit(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $payment = $this->proof($booking, $renter);
        $this->actingAs($admin)->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'reject'])->assertSessionHasErrors('owner_comment');
        $this->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'reject', 'owner_comment' => 'Dana belum masuk.'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
        $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertSee('Dana belum masuk.');
        $newPayment = $this->proof($booking, $renter);
        $this->verify($booking, $newPayment, $admin, 'RETRY-001');
        $this->assertSame(2, $booking->payments()->count());
    }

    public function test_same_bank_transaction_cannot_pay_two_bookings(): void
    {
        [$first, $owner, $renter, $admin] = $this->booking();
        $this->accept($first, $owner);
        $payment = $this->proof($first, $renter);
        $this->verify($first, $payment, $admin, 'SAME-REF');
        $item = Item::factory()->for($owner)->create(['quantity' => 1]);
        $second = app(BookingService::class)->create($renter, $item, ['start_date' => '2026-11-03', 'end_date' => '2026-11-05', 'quantity' => 1, 'requirements_accepted' => true]);
        $this->accept($second, $owner);
        $secondPayment = $this->proof($second, $renter);
        $this->actingAs($admin)->patch(route('admin.payments.review', [$second, $secondPayment]), ['decision' => 'verify', 'transaction_reference' => 'same-ref'])->assertSessionHasErrors('transaction_reference');
        $this->assertSame(BookingPaymentStatus::Submitted, $secondPayment->fresh()->status);
    }

    public function test_cancelled_paid_booking_is_refunded_by_admin_and_never_paid_to_owner(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $payment = $this->proof($booking, $renter, 'deposit_transfer');
        $this->verify($booking, $payment, $admin, 'REFUND-DP');
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $booking->fresh()->payout->status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($booking->item, $booking->start_date, $booking->end_date));
        $payload = ['refunded_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('refund.png')];
        $this->actingAs($owner)->post(route('incoming-bookings.refund', $booking), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.refund', $booking), $payload)->assertSessionHasErrors('refund');
        $this->actingAs($renter)->put(route('my-bookings.refund-destination', $booking), ['provider' => 'BCA', 'account_name' => 'Penyewa', 'account_identifier' => '444555666'])->assertSessionHasNoErrors();
        $this->put(route('my-bookings.refund-destination', $booking), ['provider' => 'BCA', 'account_name' => 'Other', 'account_identifier' => 'changed'])->assertSessionHasErrors('refund');
        $this->actingAs($admin)->post(route('admin.claim-refund', $booking))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.refund', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame('50000.00', $booking->fresh()->refund->amount);
        $this->assertSame('444555666', $booking->fresh()->refund->destination_snapshot['account_identifier']);
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasErrors('payout');
        $this->actingAs($renter)->get(route('bookings.refund-proof', $booking))->assertOk();
    }

    public function test_report_holds_payout_until_admin_resolves_it_and_second_admin_cannot_transfer(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $this->verify($booking, $this->proof($booking, $renter), $admin, 'HOLD-IN');
        $this->returnItem($booking, $owner);
        $this->actingAs($renter)->patch(route('bookings.payout', $booking), ['action' => 'report', 'note' => 'Pengembalian perlu diperiksa.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasErrors('payout');
        $this->patch(route('bookings.payout', $booking), ['action' => 'release', 'note' => 'Kedua pihak sudah menyetujui pengembalian.'])->assertSessionHasNoErrors();
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->admin()->create())->patch(route('bookings.payout', $booking), $this->payoutPayload())->assertSessionHasErrors('payout');
        $this->assertSame('processing', $booking->fresh()->payout->status);
        $this->assertCount(0, Storage::disk('local')->allFiles('payout-proofs'));
    }

    public function test_missing_platform_method_or_owner_payout_destination_blocks_acceptance(): void
    {
        [$booking, $owner, , , $method] = $this->booking();
        $method->update(['is_active' => false]);
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasErrors('payment_method');
        $method->update(['is_active' => true]);
        $owner->paymentMethods()->update(['is_active' => false]);
        $this->patch(route('incoming-bookings.approve', $booking))->assertSessionHasErrors('payment_method');
        $this->assertNull($booking->fresh()->payout);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_expiry_after_rejected_first_payment_releases_stock_and_cancels_payout(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $payment = $this->proof($booking, $renter);
        $this->travelTo($booking->fresh()->payment_due_at->addMinute());
        $this->artisan('bookings:expire-quotes')->assertSuccessful();
        $this->assertSame(BookingStatus::PaymentReview, $booking->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'reject', 'owner_comment' => 'Tidak ditemukan di mutasi.'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->payout->status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($booking->item, $booking->start_date, $booking->end_date));
    }

    public function test_unrelated_accounts_cannot_manage_payout_or_inspect_payment_files(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->accept($booking, $owner);
        $payment = $this->proof($booking, $renter);
        $this->actingAs(User::factory()->create())->get(route('bookings.payment-proof', [$booking, $payment]))->assertForbidden();
        $this->get(route('bookings.invoice', $booking))->assertForbidden();
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertForbidden();
        $this->patch(route('bookings.payout', $booking), ['action' => 'report', 'note' => 'Not mine'])->assertForbidden();
        $this->put(route('my-bookings.refund-destination', $booking), ['provider' => 'BCA', 'account_name' => 'Other', 'account_identifier' => '000'])->assertForbidden();
        $this->actingAs($owner)->patch(route('bookings.payout', $booking), ['action' => 'paid'] + $this->payoutPayload())->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->payout->status);
    }

    public function test_refund_can_only_be_recorded_by_the_admin_who_claimed_it(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $this->verify($booking, $this->proof($booking, $renter), $admin, 'CLAIM-REFUND-IN');
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertSessionHasNoErrors();
        $this->put(route('my-bookings.refund-destination', $booking), ['provider' => 'BCA', 'account_name' => 'Penyewa', 'account_identifier' => '444555666'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.claim-refund', $booking))->assertSessionHasNoErrors();
        $other = User::factory()->admin()->create();
        $this->actingAs($other)->post(route('admin.claim-refund', $booking))->assertSessionHasErrors('refund');
        $this->post(route('admin.refund', $booking), ['refunded_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('refund.jpg')])->assertSessionHasErrors('refund');
        $this->assertNull($booking->fresh()->refund);
        $this->assertCount(0, Storage::disk('local')->allFiles('refund-proofs'));
    }

    public function test_private_qris_snapshot_survives_admin_replacing_original(): void
    {
        [$booking, $owner, $renter, $admin, $platform] = $this->booking();
        $this->actingAs($admin)->put(route('admin.payment-methods.update', $platform), [
            'type' => 'qris', 'account_name' => 'Merchant Pinjemin', 'is_active' => 1,
            'qris_image' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasNoErrors();
        $this->accept($booking, $owner);
        $snapshot = $booking->fresh()->payment_methods_snapshot;
        Storage::disk('local')->assertExists($snapshot[0]['qris_path']);
        $this->actingAs($admin)->delete(route('admin.payment-methods.destroy', $platform))->assertSessionHasNoErrors();
        $this->actingAs($renter)->get(route('bookings.payment-method-image', [$booking, 0]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('bookings.payment-method-image', [$booking, 0]))->assertForbidden();
    }

    public function test_platform_method_limit_and_primary_are_shared_across_admin_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        UserPaymentMethod::factory()->for($admin)->count(10)->sequence(fn ($sequence) => ['is_platform' => true, 'is_primary' => $sequence->index === 0, 'sort_order' => $sequence->index])->create();
        $payload = ['type' => 'dana', 'account_name' => 'Pinjemin', 'account_identifier' => '08123456789', 'is_active' => 1];
        $this->actingAs($other)->post(route('admin.payment-methods.store'), $payload)->assertSessionHasErrors('type', errorBag: 'method-new');
        $primary = UserPaymentMethod::query()->where('is_platform', true)->where('is_primary', true)->firstOrFail();
        $this->delete(route('admin.payment-methods.destroy', $primary))->assertSessionHasNoErrors();
        $this->assertSame(1, UserPaymentMethod::query()->where('is_platform', true)->where('is_primary', true)->count());
        $this->assertSame(9, UserPaymentMethod::query()->where('is_platform', true)->count());
        $this->post(route('admin.payment-methods.store'), $payload)->assertSessionHasNoErrors();
    }

    public function test_failed_or_invalid_payout_proof_does_not_mark_transfer_successful(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $this->verify($booking, $this->proof($booking, $renter), $admin, 'PROOF-IN');
        $this->returnItem($booking, $owner);
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasNoErrors();
        $this->patch(route('bookings.payout', $booking), array_replace($this->payoutPayload(), ['proof' => UploadedFile::fake()->create('proof.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('proof');
        $this->patch(route('bookings.payout', $booking), array_replace($this->payoutPayload(), ['paid_at' => now(Booking::RentalTimezone)->addDay()->format('Y-m-d H:i')]))->assertSessionHasErrors('paid_at');
        $disk = \Mockery::mock(FilesystemAdapter::class)->makePartial();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->patch(route('bookings.payout', $booking), $this->payoutPayload())->assertSessionHasErrors('proof');
        $this->assertSame('processing', $booking->fresh()->payout->status);
        $this->assertNull($booking->fresh()->payout->paid_at);
    }

    public function test_finance_and_booking_forms_render_for_each_party_without_javascript(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/platform-no-hot'));
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->accept($booking, $owner);
        $renterPage = $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertOk()->assertSee('Nominal yang harus ditransfer')->assertDontSee('999888777');
        $document = new \DOMDocument;
        $document->loadHTML($renterPage->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $verified = (new \DOMXPath($document))->query('//dt[text()="Terverifikasi"]/following-sibling::dd')->item(0);
        $this->assertSame('Rp 0', trim($verified->textContent));
        $this->capture('renter-payment', $renterPage->getContent());
        $this->proof($booking, $renter);
        $adminPage = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Referensi transaksi masuk')->assertSee('Verifikasi Pembayaran');
        $this->capture('admin-review', $adminPage->getContent());
        $methodsPage = $this->get(route('admin.payment-methods.index'))->assertOk()->assertSee('Rekening Penerimaan Pinjemin');
        $this->capture('admin-methods', $methodsPage->getContent());
        $financePage = $this->get(route('admin.finance', ['tab' => 'review']))->assertOk()->assertSee($booking->booking_code);
        $this->capture('admin-finance', $financePage->getContent());
        $ownerPage = $this->actingAs($owner)->get(route('owner.payouts'))->assertOk()->assertSee($booking->booking_code);
        $this->capture('owner-payouts', $ownerPage->getContent());
        $this->actingAs(User::factory()->create())->get(route('owner.payouts'))->assertOk()->assertDontSee($booking->booking_code);
        $this->actingAs($admin)->get(route('admin.finance', ['tab' => 'ready']))->assertOk()->assertDontSee($booking->booking_code);
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('CAPTURE_PLATFORM_UI') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing/platform-ui'));
            file_put_contents(storage_path('framework/testing/platform-ui/'.$name.'.html'), $html);
        }
    }

    /** @return array{Booking, User, User, User, UserPaymentMethod} */
    private function booking(bool $delivery = false): array
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $platform = UserPaymentMethod::factory()->for($admin)->create(['type' => 'bank', 'is_platform' => true, 'is_primary' => true, 'provider' => 'Bank Pinjemin', 'account_name' => 'Pinjemin', 'account_identifier' => '111222333']);
        UserPaymentMethod::factory()->for($owner)->create(['type' => 'bank', 'is_primary' => true, 'account_identifier' => '999888777']);
        $item = Item::factory()->for($owner)->create(['daily_price' => 100000, 'deposit_amount' => 50000, 'quantity' => 1, 'allow_deposit_payment' => true, 'allow_cash_balance' => true, 'delivery_enabled' => $delivery, 'free_delivery' => false]);
        $booking = app(BookingService::class)->create($renter, $item, [
            'requirements_accepted' => true,
            'start_date' => '2026-11-03', 'end_date' => '2026-11-05', 'quantity' => 1,
            'fulfillment_method' => $delivery ? Booking::Delivery : Booking::Pickup,
            'recipient_name' => 'Penyewa', 'recipient_phone' => '08123456789', 'recipient_city' => 'Medan', 'recipient_address' => 'Jalan Penyewa 1',
        ]);

        $snapshot = $booking->fulfillment_snapshot;
        unset($snapshot['inspection_policy']);
        $booking->update(['fulfillment_snapshot' => $snapshot]);

        return [$booking, $owner, $renter, $admin, $platform];
    }

    private function accept(Booking $booking, User $owner): void
    {
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
    }

    private function proofPayload(string $plan = 'full_transfer'): array
    {
        return ['payment_plan' => $plan, 'method_index' => 0, 'transferred_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('payment.jpg')];
    }

    private function proof(Booking $booking, User $renter, string $plan = 'full_transfer'): BookingPayment
    {
        $this->actingAs($renter)->post(route('my-bookings.payments.store', $booking), $this->proofPayload($plan))->assertSessionHasNoErrors();

        return $booking->payments()->reorder()->latest('id')->firstOrFail();
    }

    private function verify(Booking $booking, BookingPayment $payment, User $admin, string $reference): void
    {
        $this->actingAs($admin)->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'verify', 'transaction_reference' => $reference])->assertSessionHasNoErrors();
    }

    private function returnItem(Booking $booking, User $owner): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 09:00:00', Booking::RentalTimezone)->utc());
        $this->actingAs($owner)->patch(route('incoming-bookings.hand-over', $booking))->assertSessionHasNoErrors();
        $this->travel(37)->seconds();
        $this->patch(route('incoming-bookings.complete', $booking))->assertSessionHasNoErrors();
    }

    private function payoutPayload(): array
    {
        return ['action' => 'paid', 'paid_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'transaction_reference' => 'OUT-001', 'proof' => UploadedFile::fake()->image('payout.jpg')];
    }
}
