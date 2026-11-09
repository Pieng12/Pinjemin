<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayout;
use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Models\IdentityVerification;
use App\Models\Item;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\ActivityService;
use App\Services\BookingActionPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BookingActivityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_payment_next_action_uses_effective_deadline_without_changing_booking(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment, 'accepted_at' => now(), 'payment_due_at' => now()->addHour()]);
        $action = app(BookingActionPresenter::class)->describe($booking, $booking->renter, 'renter');
        $this->assertTrue($action['actionable']);
        $this->assertStringEndsWith('#booking-payments', $action['href']);
        $this->assertNotNull($action['deadline']);
        $booking->update(['payment_due_at' => now()->subMinute()]);
        $expired = app(BookingActionPresenter::class)->describe($booking->fresh(), $booking->renter, 'renter');
        $this->assertStringNotContainsString('#booking-payments', $expired['href']);
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
    }

    public function test_platform_payment_review_is_only_a_task_for_admin(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PaymentReview, 'fulfillment_snapshot' => ['payment_collection' => 'platform']]);
        $admin = User::factory()->admin()->create();
        $presenter = app(BookingActionPresenter::class);
        $this->assertFalse($presenter->describe($booking, $booking->item->user, 'owner')['actionable']);
        $this->assertTrue($presenter->describe($booking, $admin, 'admin')['actionable']);
        $this->assertSame(0, app(ActivityService::class)->forUser($booking->item->user, 'owner')['bookingCount']);
        $this->assertSame(1, app(ActivityService::class)->forUser($admin, 'admin')['bookingCount']);
    }

    public function test_legacy_payment_review_remains_an_owner_task(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::PaymentReview]);
        $action = app(BookingActionPresenter::class)->describe($booking, $booking->item->user, 'owner');
        $this->assertTrue($action['actionable']);
        $this->assertStringEndsWith('#booking-payments', $action['href']);
    }

    public function test_expired_reservations_do_not_inflate_dashboard_task_counts(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment, 'payment_due_at' => now()->subMinute()]);
        $this->assertSame(0, app(ActivityService::class)->forUser($booking->renter)['bookingCount']);
    }

    public function test_missing_platform_destination_is_a_waiting_state_for_the_owner(): void
    {
        $booking = Booking::factory()->create(['quantity' => 1, 'fulfillment_snapshot' => ['payment_collection' => 'platform']]);
        UserPaymentMethod::factory()->for($booking->item->user)->create();
        $action = app(BookingActionPresenter::class)->describe($booking, $booking->item->user, 'owner');
        $this->assertFalse($action['actionable']);
        $this->assertStringContainsString('Hubungi admin', $action['description']);
    }

    public function test_identity_expiration_after_acceptance_does_not_block_handover(): void
    {
        $booking = Booking::factory()->approved()->create(['quantity' => 1, 'start_date' => now(Booking::RentalTimezone)->toDateString(), 'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => true]]]);
        $identity = IdentityVerification::factory()->for($booking->renter)->create(['status' => 'verified', 'verified_until' => now()->subMinute()]);
        $booking->update(['identity_verification_id' => $identity->id]);
        $this->assertNull(app(BookingActionPresenter::class)->handoverBlocker($booking->fresh()));
    }

    public function test_tasks_are_scoped_counted_once_sorted_by_deadline_and_limited_to_five(): void
    {
        $renter = User::factory()->create();
        for ($index = 7; $index >= 1; $index--) {
            Booking::factory()->for($renter, 'renter')->create(['status' => BookingStatus::AwaitingPayment, 'payment_due_at' => now()->addHours($index)]);
        }
        $other = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment, 'payment_due_at' => now()->addMinutes(2)]);
        $activity = app(ActivityService::class)->forUser($renter);
        $this->assertSame(7, $activity['bookingCount']);
        $this->assertCount(5, $activity['tasks']);
        $this->assertTrue($activity['tasks'][0]['deadline']->lessThan($activity['tasks'][1]['deadline']));
        $this->assertStringNotContainsString($other->booking_code, json_encode($activity));
        $this->actingAs($renter)->get(route('dashboard'))->assertOk()->assertSee('7 booking membutuhkan tindakanmu.');
    }

    public function test_identity_queue_is_separate_from_booking_count(): void
    {
        $admin = User::factory()->admin()->create();
        $identity = IdentityVerification::factory()->create(['status' => 'pending']);
        Booking::factory()->for($identity->user, 'renter')->create(['fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => true]]]);
        $activity = app(ActivityService::class)->forUser($admin, 'admin');
        $this->assertSame(0, $activity['bookingCount']);
        $this->assertSame(1, $activity['identityCount']);
        $this->assertSame(route('identity.show', $identity), $activity['tasks'][0]['href']);
    }

    public function test_pending_owner_sees_identity_blocker_and_no_accept_form(): void
    {
        $booking = Booking::factory()->create(['fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => true, 'photos_required' => false, 'terms_version' => config('legal.terms_version')]]]);
        $action = app(BookingActionPresenter::class)->describe($booking, $booking->item->user, 'owner');
        $this->assertFalse($action['actionable']);
        $this->actingAs($booking->item->user)->get(route('incoming-bookings.show', $booking))->assertOk()
            ->assertSee('Verifikasi identitas diperlukan')->assertDontSee('action="'.route('incoming-bookings.approve', $booking).'"', false);
    }

    public function test_pending_owner_is_sent_to_missing_payout_settings_instead_of_acceptance(): void
    {
        $booking = Booking::factory()->create(['quantity' => 1, 'fulfillment_snapshot' => ['payment_collection' => 'platform']]);
        $admin = User::factory()->admin()->create();
        UserPaymentMethod::factory()->for($admin)->create(['is_platform' => true]);
        $action = app(BookingActionPresenter::class)->describe($booking, $booking->item->user, 'owner');
        $this->assertSame(route('payment-methods.index'), $action['href']);
        $this->assertStringContainsString('rekening', $action['description']);
    }

    public function test_unrelated_user_cannot_receive_a_booking_action(): void
    {
        $booking = Booking::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(BookingActionPresenter::class)->describe($booking, User::factory()->create(), 'renter');
    }

    public function test_refund_destination_and_claiming_are_assigned_to_correct_party(): void
    {
        $booking = Booking::factory()->cancelled()->create(['refund_status' => 'pending', 'fulfillment_snapshot' => ['payment_collection' => 'platform']]);
        $admin = User::factory()->admin()->create();
        $presenter = app(BookingActionPresenter::class);
        $this->assertTrue($presenter->describe($booking, $booking->renter, 'renter')['actionable']);
        $this->assertFalse($presenter->describe($booking, $admin, 'admin')['actionable']);
        $booking->update(['refund_destination' => ['provider' => 'Bank Uji', 'account_name' => 'Penyewa', 'account_identifier' => '123']]);
        $this->assertTrue($presenter->describe($booking->fresh(), $admin, 'admin')['actionable']);
        $booking->update(['refund_claimed_by_user_id' => User::factory()->admin()->create()->id]);
        $this->assertFalse($presenter->describe($booking->fresh(), $admin, 'admin')['actionable']);
    }

    public function test_payout_cannot_be_the_next_action_before_condition_inspections_are_settled(): void
    {
        $booking = Booking::factory()->approved()->create(['status' => BookingStatus::Completed, 'handed_over_at' => now()->subDay(), 'returned_at' => now(), 'payment_legacy' => false, 'total_amount' => 0, 'fulfillment_snapshot' => ['payment_collection' => 'platform', 'inspection_policy' => ['version' => 1]]]);
        BookingPayout::factory()->for($booking)->create(['status' => 'pending']);
        $admin = User::factory()->admin()->create();
        $this->assertFalse(app(BookingActionPresenter::class)->describe($booking->fresh(), $admin, 'admin')['actionable']);
        ConditionSession::factory()->for($booking)->create(['stage' => 'handover', 'status' => 'accepted']);
        ConditionSession::factory()->for($booking)->create(['stage' => 'return', 'status' => 'accepted']);
        $this->assertStringEndsWith('#booking-payout', app(BookingActionPresenter::class)->describe($booking->fresh(), $admin, 'admin')['href']);
    }

    public function test_submitted_condition_evidence_waits_for_other_party_and_does_not_request_upload_again(): void
    {
        $booking = Booking::factory()->approved()->create(['quantity' => 1, 'start_date' => now(Booking::RentalTimezone)->toDateString(), 'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1]]]);
        $session = ConditionSession::factory()->for($booking)->create(['status' => 'active', 'expires_at' => now()->addMinutes(20)]);
        ConditionReport::factory()->for($session, 'session')->create(['user_id' => $booking->renter_user_id, 'role' => 'renter', 'kind' => 'initial', 'submitted_at' => now()]);
        $action = app(BookingActionPresenter::class)->describe($booking->fresh(), $booking->renter, 'renter');
        $this->assertFalse($action['actionable']);
        $this->assertSame('Menunggu bukti pihak lain', $action['title']);
    }

    public function test_future_handover_and_revoked_identity_do_not_offer_inspection_controls(): void
    {
        $booking = Booking::factory()->approved()->create(['start_date' => now(Booking::RentalTimezone)->addDay()->toDateString(), 'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => false, 'photos_required' => false, 'terms_version' => config('legal.terms_version')]]]);
        $this->actingAs($booking->item->user)->get(route('incoming-bookings.show', $booking))->assertOk()->assertDontSee('name="action" value="ready"', false);
        $identity = IdentityVerification::factory()->for($booking->renter)->create(['status' => 'revoked']);
        $booking->update(['start_date' => now(Booking::RentalTimezone)->toDateString(), 'identity_verification_id' => $identity->id, 'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => true, 'photos_required' => false, 'terms_version' => config('legal.terms_version')]]]);
        $action = app(BookingActionPresenter::class)->describe($booking->fresh(), $booking->item->user, 'owner');
        $this->assertFalse($action['actionable']);
        $this->assertStringContainsString('dicabut', $action['description']);
    }

    public function test_booking_forms_keep_errors_and_restored_values_in_the_submitted_form_without_javascript(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/ux-no-hot'));
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->approved()->create(['start_date' => now(Booking::RentalTimezone)->subDay()->toDateString(), 'handed_over_at' => now()->subHour(), 'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => false, 'photos_required' => false, 'terms_version' => config('legal.terms_version')]]]);
        $key = 'inspection-form-7-handover';
        $this->actingAs($admin)->from(route('admin.bookings.show', $booking))
            ->post(route('inspections.store', [$booking, 'handover']), ['_ui_form' => $key, 'action' => 'resolve', 'outcome' => 'invalid', 'note' => 'Catatan hanya untuk penyerahan'])
            ->assertRedirect(route('admin.bookings.show', $booking));
        $response = $this->get(route('admin.bookings.show', $booking))->assertOk();
        $html = $response->getContent();
        $this->capture('form-error', $html);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[.//textarea[@name="note"]]');
        foreach ($forms as $form) {
            $note = $xpath->query('.//textarea[@name="note"]', $form)->item(0);
            if ($form->getAttribute('data-form-id') === $key) {
                $this->assertSame('Catatan hanya untuk penyerahan', $note->textContent);
                $this->assertSame('true', $xpath->query('.//select[@name="outcome"]', $form)->item(0)->getAttribute('aria-invalid'));
            } else {
                $this->assertSame('', $note->textContent);
                $this->assertNotSame('true', $note->getAttribute('aria-invalid'));
            }
        }
    }

    public function test_shared_workspaces_and_operational_pages_render_with_role_specific_navigation(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/ux-no-hot'));
        $owner = User::factory()->create(['name' => 'Pemilik Contoh dengan Nama Panjang']);
        $renter = User::factory()->create(['name' => 'Penyewa Contoh']);
        $admin = User::factory()->admin()->create();
        $item = Item::factory()->for($owner)->create(['name' => 'Kamera dan Perlengkapan Acara untuk Berbagai Kebutuhan', 'quantity' => 3]);
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        UserPaymentMethod::factory()->for($admin)->create(['is_platform' => true]);
        Booking::factory()->for($item)->for($renter, 'renter')->create(['quantity' => 1]);
        foreach ([
            [$owner, ['owner-dashboard' => 'owner.dashboard', 'inventory' => 'my-items.index', 'owner-methods' => 'payment-methods.index', 'incoming-list' => 'incoming-bookings.index']],
            [$admin, ['admin-dashboard' => 'admin.dashboard', 'users' => 'admin.users.index', 'categories' => 'admin.categories.index', 'admin-items' => 'admin.items.index', 'admin-bookings' => 'admin.bookings.index', 'identity-queue' => 'admin.identity.index']],
        ] as [$user, $routes]) {
            foreach ($routes as $name => $route) {
                $response = $this->actingAs($user)->get(route($route))->assertOk()->assertSee('data-workspace', false)->assertSee('data-workspace-collapse', false)->assertSee('aria-current="page"', false)->assertSee('Kembali ke Marketplace');
                $this->capture($name, $response->getContent());
            }
        }
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($owner);
        foreach (['profile' => route('profile.edit'), 'edit-item' => route('my-items.edit', $item)] as $name => $url) {
            $response = $this->get($url)->assertOk();
            $this->capture($name, $response->getContent());
        }
        $response = $this->actingAs($renter)->get(route('dashboard'))->assertOk();
        $this->capture('account-dashboard', $response->getContent());
        $response = $this->get(route('my-bookings.index'))->assertOk();
        $this->capture('renter-list', $response->getContent());
        $payment = Booking::factory()->for($item)->for($renter, 'renter')->create([
            'status' => BookingStatus::AwaitingPayment, 'accepted_at' => now(), 'payment_due_at' => now()->addHours(24),
            'total_amount' => 320000, 'deposit_amount' => 50000,
            'fulfillment_snapshot' => ['payment_collection' => 'platform', 'payment_policy' => ['allow_deposit' => true]],
            'payment_methods_snapshot' => [
                ['label' => 'Bank Pertama', 'account_name' => 'Pinjemin', 'account_identifier' => '111222333'],
                ['label' => 'Bank Kedua', 'account_name' => 'Pinjemin', 'account_identifier' => '444555666'],
            ],
        ]);
        $response = $this->get(route('my-bookings.show', $payment))->assertOk()->assertSee('Langkah selanjutnya')->assertSee('Bank Kedua');
        $this->capture('payment-options', $response->getContent());
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('CAPTURE_UX_UI') !== '1') {
            return;
        }
        File::ensureDirectoryExists(storage_path('framework/testing/workspace-ui'));
        File::put(storage_path('framework/testing/workspace-ui/'.$name.'.html'), $html);
    }
}
