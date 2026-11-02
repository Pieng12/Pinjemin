<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_password_controls_do_not_repopulate_passwords_or_advertise_reset(): void
    {
        $this->withSession(['_old_input' => ['password' => 'secret-do-not-render']])->get('/login')
            ->assertSee('data-password-toggle', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('secret-do-not-render')
            ->assertDontSee('href="'.route('password.request').'"', false);
        $this->get('/register')->assertSee('aria-controls="password_confirmation"', false)->assertSee('minimal 8 karakter');
    }

    public function test_login_is_blocked_after_five_failures_and_reopens_after_sixty_seconds(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['email' => 'limit@example.test', 'password' => 'password123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam 60 detik.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_failure_counter(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect();
        $this->post('/logout')->assertRedirect();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_booking_deposit_is_part_of_total_and_delivery_is_charged_once(): void
    {
        UserPaymentMethod::factory()->for(User::factory()->admin())->create(['is_platform' => true]);
        $this->travelTo('2026-10-01 01:00:00');
        $item = Item::factory()->create(['daily_price' => 100000, 'deposit_amount' => 50000, 'delivery_enabled' => true]);
        UserPaymentMethod::factory()->for($item->user)->create();
        $booking = app(BookingService::class)->create(User::factory()->create(), $item, [
            'start_date' => '2026-10-03', 'end_date' => '2026-10-05', 'quantity' => 1,
            'fulfillment_method' => Booking::Delivery, 'recipient_name' => 'Penyewa',
            'recipient_phone' => '08123456789', 'recipient_city' => 'Medan', 'recipient_address' => 'Jalan Tujuan 1',
        ]);
        $this->assertSame('300000.00', $booking->total_amount);
        $this->assertSame('50000.00', $booking->deposit_amount);
        $this->assertTrue($booking->usesAdvancePaymentPricing());
        $booking = app(BookingService::class)->quoteDelivery($booking, 20000);
        $this->assertSame('320000.00', $booking->total_amount);
    }

    public function test_historical_booking_keeps_its_original_pricing_when_delivery_is_quoted(): void
    {
        $this->travelTo('2026-10-01 01:00:00');
        $item = Item::factory()->create(['delivery_enabled' => true]);
        UserPaymentMethod::factory()->for($item->user)->create();
        $booking = Booking::factory()->for($item)->create([
            'start_date' => '2026-10-03', 'end_date' => '2026-10-05', 'quantity' => 1,
            'subtotal' => 300000, 'deposit_amount' => 50000, 'total_amount' => 350000,
            'fulfillment_method' => Booking::Delivery, 'fulfillment_snapshot' => ['free_delivery' => false],
        ]);
        $booking = app(BookingService::class)->quoteDelivery($booking, 20000);
        $this->assertSame('370000.00', $booking->total_amount);
        $this->assertFalse($booking->usesAdvancePaymentPricing());
    }

    public function test_legacy_paid_booking_has_no_balance_or_fabricated_payment(): void
    {
        $booking = Booking::factory()->approved()->create(['total_amount' => 350000]);
        $this->assertSame(0.0, $booking->remainingAmount());
        $this->assertSame(0.0, $booking->verifiedAmount());
        $this->assertSame(0, $booking->payments()->count());
        $this->actingAs($booking->renter)->get(route('my-bookings.show', $booking))->assertSee('Lunas - data lama')->assertSee('Tidak tercatat');
    }

    public function test_currency_input_submits_without_javascript_and_keeps_cents(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-currency-input name="delivery_fee" label="Ongkir" value="20000.50" required />', ['errors' => new ViewErrorBag]);
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $input = (new \DOMXPath($document))->query('//input[@name="delivery_fee"]')->item(0);
        $this->assertSame('number', $input->getAttribute('type'));
        $this->assertSame('20000.50', $input->getAttribute('value'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertSame('0.01', $input->getAttribute('step'));
    }

    public function test_zero_maximum_price_is_applied_and_inverted_range_is_rejected(): void
    {
        Item::factory()->create(['daily_price' => 50000]);
        $this->get('/jelajahi?max_price=0')->assertViewHas('items', fn ($items) => $items->total() === 0);
        $this->get('/jelajahi?min_price=60000&max_price=50000')->assertSessionHasErrors([
            'max_price' => 'Harga maksimum harus sama atau lebih besar dari harga minimum.',
        ]);
    }

    public function test_payment_method_validation_only_repopulates_the_submitted_form(): void
    {
        $owner = User::factory()->create();
        $first = UserPaymentMethod::factory()->for($owner)->create(['account_name' => 'Nama Pertama']);
        $second = UserPaymentMethod::factory()->for($owner)->create(['account_name' => 'Nama Kedua']);
        $this->actingAs($owner)->from(route('payment-methods.index'))->put(route('payment-methods.update', $first), [
            '_payment_method_form' => 'method-'.$first->id,
            'type' => 'bank', 'provider' => 'BCA', 'account_name' => 'Nama Perubahan', 'account_identifier' => '',
        ])->assertSessionHasErrors('account_identifier', errorBag: 'method-'.$first->id)
            ->assertSessionHasInput('_payment_method_form', 'method-'.$first->id);
        $response = $this->get(route('payment-methods.index'))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame('Nama Perubahan', $xpath->query('//form[@action="'.route('payment-methods.update', $first).'"]//input[@name="account_name"]')->item(0)->getAttribute('value'));
        $this->assertSame('Nama Kedua', $xpath->query('//form[@action="'.route('payment-methods.update', $second).'"]//input[@name="account_name"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//details[@open]')->length);
    }

    public function test_removing_or_deactivating_primary_promotes_the_next_active_method(): void
    {
        $owner = User::factory()->create();
        $first = UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        $second = UserPaymentMethod::factory()->for($owner)->create(['sort_order' => 2]);
        $third = UserPaymentMethod::factory()->for($owner)->create(['sort_order' => 1]);
        $this->actingAs($owner)->delete(route('payment-methods.destroy', $first))->assertSessionHasNoErrors();
        $this->assertTrue($third->fresh()->is_primary);
        $this->put(route('payment-methods.update', $third), [
            'type' => 'bank', 'provider' => 'BCA', 'account_name' => 'Pemilik', 'account_identifier' => '123456',
            'is_active' => 0, 'is_primary' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $owner->paymentMethods()->where('is_primary', true)->count());
    }

    public function test_failed_payment_upload_does_not_create_payment_or_change_booking(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment, 'payment_due_at' => now()->addHour()]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->actingAs($booking->renter)->post(route('my-bookings.payments.store', $booking), [
            'method_index' => 0, 'payment_plan' => 'full_transfer', 'transferred_at' => now()->format('Y-m-d H:i'),
            'proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasErrors('proof');
        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
    }

    public function test_photo_files_are_removed_when_item_creation_rolls_back(): void
    {
        $disk = Storage::fake('public');
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        Event::listen('eloquent.creating: '.ItemPhoto::class, function (): void {
            throw new \RuntimeException('Simulated database failure');
        });
        try {
            $this->actingAs($owner)->post(route('my-items.store'), [
                'name' => 'Kamera Test', 'category_id' => $category->id, 'description' => str_repeat('Deskripsi barang. ', 3),
                'condition' => 'good', 'daily_price' => '20000.50', 'quantity' => 1, 'city' => 'Medan',
                'is_active' => 0, 'photos' => [UploadedFile::fake()->image('photo.jpg')],
            ])->assertServerError();
            $this->assertSame([], $disk->allFiles());
            $this->assertSame(0, $owner->items()->count());
        } finally {
            Event::forget('eloquent.creating: '.ItemPhoto::class);
        }
    }

    public function test_qris_snapshot_files_are_removed_when_acceptance_rolls_back(): void
    {
        $disk = Storage::fake('local');
        $booking = Booking::factory()->create(['quantity' => 1]);
        $disk->put('methods/original.png', 'original-image');
        UserPaymentMethod::factory()->for($booking->item->user)->create(['type' => 'qris', 'qris_path' => 'methods/original.png']);
        Event::listen('eloquent.updating: '.Booking::class, function (Booking $model): void {
            if ($model->status === BookingStatus::AwaitingPayment) {
                throw new \RuntimeException('Simulated database failure');
            }
        });
        try {
            $this->actingAs($booking->item->user)->patch(route('incoming-bookings.approve', $booking))->assertServerError();
            $this->assertSame(['methods/original.png'], $disk->allFiles());
            $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        } finally {
            Event::forget('eloquent.updating: '.Booking::class);
        }
    }

    public function test_failed_qris_and_refund_uploads_do_not_record_success(): void
    {
        $owner = User::factory()->create();
        $booking = Booking::factory()->for(Item::factory()->for($owner))->cancelled()->create(['refund_status' => 'pending']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->twice()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($owner)->post(route('payment-methods.store'), [
            'type' => 'qris', 'account_name' => 'Merchant', 'is_active' => 1,
            'qris_image' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasErrors('qris_image', errorBag: 'method-new');
        $this->post(route('incoming-bookings.refund', $booking), [
            'refunded_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('refund.jpg'),
        ])->assertSessionHasErrors('proof');
        $this->assertSame(0, $owner->paymentMethods()->count());
        $this->assertNull($booking->fresh()->refund);
        $this->assertSame('pending', $booking->fresh()->refund_status);
    }

    public function test_native_item_form_keeps_decimal_prices_and_rejects_excess_precision(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $payload = [
            'name' => 'Barang Desimal', 'description' => str_repeat('Deskripsi barang. ', 3), 'category_id' => $category->id,
            'condition' => 'good', 'daily_price' => '20000.50', 'deposit_amount' => '5000.25', 'quantity' => 1, 'city' => 'Medan', 'is_active' => 0,
        ];
        $this->actingAs($owner)->post(route('my-items.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $item = $owner->items()->firstOrFail();
        $this->assertSame('20000.50', $item->daily_price);
        $this->assertSame('5000.25', $item->deposit_amount);
        $this->post(route('my-items.store'), array_replace($payload, ['daily_price' => '20000.501']))->assertSessionHasErrors('daily_price');
        $this->assertSame(1, $owner->items()->count());
    }

    public function test_rejected_deposit_resubmission_shows_deposit_amount_without_javascript(): void
    {
        $booking = Booking::factory()->create([
            'total_amount' => 320000, 'deposit_amount' => 50000, 'status' => BookingStatus::AwaitingPayment,
            'accepted_at' => now(), 'payment_due_at' => now()->addHour(), 'payment_plan' => 'deposit_transfer',
        ]);
        $response = $this->actingAs($booking->renter)->get(route('my-bookings.show', $booking))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $amount = (new \DOMXPath($document))->query('//*[@data-payment-amount]')->item(0);
        $this->assertSame('Rp 50.000', trim($amount->textContent));
    }

    public function test_first_proof_rejected_after_deadline_expires_booking_and_releases_stock(): void
    {
        $this->freezeTime();
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PaymentReview, 'payment_due_at' => now()->subMinute(), 'quantity' => 1,
        ]);
        $payment = $booking->payments()->create([
            'submitted_by_user_id' => $booking->renter_user_id, 'kind' => 'full', 'channel' => 'transfer',
            'amount' => $booking->total_amount, 'method_snapshot' => [], 'submitted_at' => now()->subMinutes(5), 'status' => 'submitted',
        ]);
        $this->actingAs($booking->item->user)->patch(route('incoming-bookings.payments.review', [$booking, $payment]), [
            'decision' => 'reject', 'owner_comment' => 'Dana belum diterima.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame($booking->item->quantity, app(AvailabilityService::class)->getAvailableQuantity($booking->item, $booking->start_date, $booking->end_date));
    }

    public function test_invalid_deposit_amounts_only_allow_full_payment(): void
    {
        foreach ([0, 300000, 350000] as $deposit) {
            $booking = Booking::factory()->create([
                'total_amount' => 300000, 'deposit_amount' => $deposit, 'status' => BookingStatus::AwaitingPayment,
                'accepted_at' => now(), 'payment_due_at' => now()->addHour(),
                'fulfillment_snapshot' => ['pricing_version' => 2, 'payment_policy' => ['allow_deposit' => true]],
            ]);
            $response = $this->actingAs($booking->renter)->get(route('my-bookings.show', $booking));
            $response->assertSee('Pembayaran DP tidak tersedia')->assertDontSee('value="deposit_transfer"', false);
        }
    }

    public function test_cash_balance_does_not_offer_transfer_after_deposit_is_verified(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::PartiallyPaid, 'payment_plan' => 'deposit_cash', 'total_amount' => 320000,
        ]);
        $booking->payments()->create([
            'submitted_by_user_id' => $booking->renter_user_id, 'kind' => 'deposit', 'channel' => 'transfer',
            'amount' => 50000, 'method_snapshot' => [], 'status' => 'verified', 'submitted_at' => now(), 'reviewed_at' => now(),
        ]);
        $this->actingAs($booking->renter)->get(route('my-bookings.show', $booking))
            ->assertSee('dibayar tunai saat pengambilan')
            ->assertDontSee('Kirim Bukti Pelunasan');
        $this->assertFalse($booking->renter->can('submitPayment', $booking));
    }
}
