<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Models\IdentityVerification;
use App\Models\Item;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\IdentityVerificationService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ConditionInspectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-01 09:00:00', Booking::RentalTimezone)->utc());
    }

    public function test_required_photos_are_locked_private_until_both_submit_and_handover_needs_both_agreements(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->act($booking, $owner, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasErrors('inspection');
        $this->act($booking, $renter, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'handover', 'send', $this->report(false))->assertSessionHasErrors('photos');
        $this->act($booking, $owner, 'handover', 'send', $this->report() + ['condition_note' => 'CATATAN-PEMILIK-PRIVAT'])->assertSessionHasNoErrors();
        $session = $this->inspectionSession($booking, 'handover');
        $ownerReport = $session->reports->first();
        $photo = $ownerReport->photos[0];
        $this->actingAs($renter)->get(route('inspections.photo', [$booking, $ownerReport, $photo['id']]))->assertForbidden();
        $this->get(route('my-bookings.show', $booking))->assertOk()->assertDontSee('CATATAN-PEMILIK-PRIVAT');
        $this->actingAs($admin)->get(route('inspections.photo', [$booking, $ownerReport, $photo['id']]))->assertOk();
        $count = count(Storage::disk('local')->allFiles('condition-evidence'));
        $this->act($booking, $owner, 'handover', 'save', $this->report())->assertSessionHasErrors('inspection');
        $this->assertCount($count, Storage::disk('local')->allFiles('condition-evidence'));
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $this->actingAs($renter)->get(route('inspections.photo', [$booking, $ownerReport, $photo['id']]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->act($booking, $owner, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->handed_over_at);
        $this->act($booking, $renter, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->handed_over_at);
        $this->assertSame('accepted', $this->inspectionSession($booking, 'handover')->status);
        $this->actingAs($owner)->patch(route('incoming-bookings.cancel', $booking), ['cancellation_reason' => 'Tidak jadi'])->assertForbidden();
        $this->get(route('inspections.document', [$booking, $session]))->assertOk()->assertDownload('BA-'.$booking->booking_code.'-handover-1.pdf');
    }

    public function test_physical_return_frees_stock_before_condition_agreement_but_payout_waits(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->finishStage($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'return', 'physical', ['physical_confirmation' => 1])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertNotNull($booking->returned_at);
        $this->assertNull($booking->returnNotice());
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($booking->item, '2026-11-01', '2026-11-03'));
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasErrors('payout');
        $this->finishStage($booking, $owner, $renter, 'return');
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasNoErrors();
    }

    public function test_optional_photos_allow_zero_and_voluntary_supplement_without_modifying_original(): void
    {
        [$booking, $owner, $renter] = $this->booking(photos: false);
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', $this->report(false))->assertSessionHasNoErrors();
        $first = $this->inspectionSession($booking, 'handover')->reports->first();
        $this->act($booking, $owner, 'handover', 'supplement', ['note' => 'Foto tambahan sukarela.', 'photos' => [UploadedFile::fake()->image('extra.png')]])->assertSessionHasNoErrors();
        $this->assertSame([], $first->fresh()->photos);
        $this->assertSame(2, $this->inspectionSession($booking, 'handover')->reports->count());
        $this->act($booking, $renter, 'handover', 'send', $this->report(false))->assertSessionHasNoErrors();
    }

    public function test_expired_session_does_not_accept_and_new_attempt_preserves_old_evidence(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $old = $this->inspectionSession($booking, 'handover');
        $this->travel(30)->minutes();
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasErrors('inspection');
        $this->assertNull($booking->fresh()->handed_over_at);
        $this->artisan('rentals:maintain-evidence')->assertSuccessful();
        $this->assertSame('expired', $old->fresh()->status);
        $this->act($booking, $renter, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->assertSame(2, $this->inspectionSession($booking, 'handover')->attempt);
        $this->assertNotNull($old->reports->first()->submitted_at);
        $this->actingAs($renter)->post(route('inspections.store', [$booking, 'handover']), ['action' => 'agree', 'session_id' => $old->id])->assertSessionHasErrors('inspection');
    }

    public function test_fully_submitted_session_remains_reviewable_after_upload_deadline(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $this->travel(31)->minutes();
        $this->artisan('rentals:maintain-evidence')->assertSuccessful();
        $this->act($booking, $owner, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->handed_over_at);
    }

    public function test_dispute_holds_payout_and_admin_must_supply_reason_and_booking_evidence(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->finishStage($booking, $owner, $renter, 'handover');
        $this->readyBoth($booking, $owner, $renter, 'return');
        $this->act($booking, $owner, 'return', 'physical', ['physical_confirmation' => 1])->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'return', 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'return', 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'return', 'dispute')->assertSessionHasErrors('note');
        $this->act($booking, $owner, 'return', 'dispute', ['note' => 'Perlu dibandingkan dengan kondisi awal.'])->assertSessionHasNoErrors();
        $this->assertSame('on_hold', $booking->fresh()->payout->status);
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'release', 'note' => 'Coba melewati pemeriksaan'])->assertSessionHasErrors('payout');
        $this->act($booking, $admin, 'return', 'resolve', ['outcome' => 'confirmed', 'note' => 'Diperiksa admin.'])->assertSessionHasErrors('photos');
        $proof = $this->inspectionSession($booking, 'return')->reports->first();
        $this->act($booking, $admin, 'return', 'resolve', ['outcome' => 'confirmed', 'note' => 'Bukti kedua pihak diperiksa; kondisi awal cocok.', 'evidence_report_id' => $proof->id])->assertSessionHasNoErrors();
        $this->assertSame('dispute', $proof->fresh()->decision);
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertSame('on_hold', $booking->fresh()->payout->status);
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'release', 'note' => 'Pemeriksaan selesai.'])->assertSessionHasNoErrors();
        $this->patch(route('bookings.payout', $booking), ['action' => 'start'])->assertSessionHasNoErrors();
    }

    public function test_delivery_records_sender_and_receiver_at_different_times_and_return_shipping_does_not_free_stock(): void
    {
        [$booking, $owner, $renter] = $this->booking(delivery: true);
        $this->act($booking, $renter, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasErrors('inspection');
        $this->act($booking, $owner, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasErrors('physical_confirmation');
        $this->act($booking, $owner, 'handover', 'send', $this->report() + ['physical_confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->handed_over_at);
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertForbidden();
        $this->travel(2)->hours();
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'handover', 'agree')->assertSessionHasNoErrors();
        $this->assertNull($this->inspectionSession($booking, 'handover')->expires_at);
        $this->act($booking, $renter, 'return', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'return', 'send', $this->report())->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->returned_at);
        $this->assertSame(0, app(AvailabilityService::class)->getAvailableQuantity($booking->item, '2026-11-01', '2026-11-03'));
        $this->travel(2)->hours();
        $this->act($booking, $owner, 'return', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'return', 'send', $this->report() + ['physical_confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($booking->fresh()->returned_at);
        $this->assertSame(1, app(AvailabilityService::class)->getAvailableQuantity($booking->item, '2026-11-01', '2026-11-03'));
        $this->act($booking, $owner, 'return', 'agree')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'return', 'agree')->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_admin_can_resolve_noncooperative_party_without_fabricating_user_agreement(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $proof = $this->inspectionSession($booking, 'handover')->reports->first();
        $this->act($booking, $admin, 'handover', 'resolve', ['outcome' => 'confirmed', 'note' => 'Penyerahan dibuktikan dan pihak dihubungi.', 'evidence_report_id' => $proof->id, 'physical_event' => 'handed_over'])->assertSessionHasNoErrors();
        $this->assertNull($proof->fresh()->decision);
        $this->assertSame(1, $this->inspectionSession($booking, 'handover')->reports->where('role', 'owner')->count());
        $this->act($booking, $admin, 'return', 'resolve', ['outcome' => 'confirmed', 'note' => 'Penerimaan kembali diverifikasi admin.', 'photos' => [UploadedFile::fake()->image('returned.jpg')], 'physical_event' => 'returned'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_draft_replacement_cleans_old_files_and_submission_is_immutable(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'save', $this->report())->assertSessionHasNoErrors();
        $first = $this->inspectionSession($booking, 'handover')->reports->first()->photos[0]['path'];
        $this->act($booking, $owner, 'handover', 'save', $this->report() + ['replace_photos' => 1])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        $this->assertCount(1, Storage::disk('local')->allFiles('condition-evidence'));
        $this->act($booking, $owner, 'handover', 'send', $this->report(false))->assertSessionHasNoErrors();
        $report = $this->inspectionSession($booking, 'handover')->reports->first();
        $this->act($booking, $owner, 'handover', 'send', $this->report() + ['replace_photos' => 1])->assertSessionHasErrors('inspection');
        $this->assertSame($report->photos, $report->fresh()->photos);
    }

    public function test_unauthorized_users_and_old_endpoints_cannot_bypass_inspections(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $stranger = User::factory()->create();
        $this->act($booking, $stranger, 'handover', 'ready')->assertForbidden();
        $this->act($booking, $stranger, 'handover', 'resolve', ['outcome' => 'confirmed', 'note' => 'Other'])->assertForbidden();
        $this->actingAs($stranger)->get(route('inspections.status', $booking))->assertForbidden();
        $this->actingAs($owner)->patch(route('incoming-bookings.hand-over', $booking))->assertForbidden();
        $this->patch(route('incoming-bookings.complete', $booking))->assertForbidden();
        $this->act($booking, $renter, 'handover', 'physical', ['physical_confirmation' => 1])->assertSessionHasErrors('physical_confirmation');
        $this->assertNull($booking->fresh()->handed_over_at);
    }

    public function test_identity_requirement_blocks_acceptance_and_quote_until_admin_verifies(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking(identity: true, delivery: true, pay: false, freeDelivery: false);
        $this->actingAs($owner)->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 10000])->assertSessionHasErrors('identity');
        $verification = IdentityVerification::factory()->verified()->for($renter)->create();
        $this->patch(route('incoming-bookings.quote-delivery', $booking), ['delivery_fee' => 10000])->assertSessionHasNoErrors();
        $this->assertSame($verification->id, $booking->fresh()->identity_verification_id);
        $this->actingAs($renter)->patch(route('my-bookings.confirm-delivery', $booking))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::AwaitingPayment, $booking->fresh()->status);
        $this->actingAs($owner)->get(route('incoming-bookings.show', $booking))->assertOk()->assertDontSee('1234567890123456')->assertDontSee('Dokumen identitas privat');
    }

    public function test_revocation_blocks_handover_but_normal_expiry_after_acceptance_does_not(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking(identity: true, pay: false);
        $verification = IdentityVerification::factory()->verified()->for($renter)->create();
        $this->pay($booking, $owner, $renter, $admin);
        $verification->update(['status' => 'revoked']);
        $this->act($booking, $owner, 'handover', 'ready')->assertSessionHasErrors('identity');
        $verification->update(['status' => 'verified', 'verified_until' => now()->subMinute()]);
        $this->act($booking, $owner, 'handover', 'ready')->assertSessionHasNoErrors();
    }

    public function test_owner_policy_changes_only_affect_future_bookings_and_consent_is_required(): void
    {
        [$booking, $owner, $renter] = $this->booking(pay: false);
        $item = $booking->item;
        $this->actingAs($renter)->post(route('items.bookings.store', $item), ['start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'quantity' => 1])->assertSessionHasErrors('requirements_accepted');
        $this->actingAs($owner)->patch(route('my-items.requirements', $item), ['requires_condition_photos' => 0, 'requires_identity_verification' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($booking->fresh()->fulfillment_snapshot['inspection_policy']['photos_required']);
        $this->assertFalse($booking->fresh()->fulfillment_snapshot['inspection_policy']['identity_required']);
        $this->actingAs($renter)->patch(route('my-items.requirements', $item), ['requires_condition_photos' => 0, 'requires_identity_verification' => 0])->assertForbidden();
    }

    public function test_reverification_preserves_original_identity_reference_for_active_booking(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking(identity: true, pay: false);
        $original = IdentityVerification::factory()->verified()->for($renter)->create();
        $this->pay($booking, $owner, $renter, $admin);
        $original->update(['status' => 'revoked']);
        $replacement = IdentityVerification::factory()->verified()->for($renter)->create();
        $this->act($booking, $owner, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->assertSame($replacement->id, $booking->fresh()->identity_verification_id);
        $this->assertSame([$original->id, $replacement->id], $booking->fresh()->fulfillment_snapshot['identity_verification_ids']);
        $this->assertTrue(app(IdentityVerificationService::class)->hasActiveCase($original->fresh()));
    }

    public function test_pdf_incomplete_and_photo_status_interfaces_do_not_expose_identity(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $session = $this->inspectionSession($booking, 'handover');
        $this->actingAs($owner)->get(route('inspections.document', [$booking, $session]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $html = view('bookings.condition-record', ['booking' => $booking, 'session' => $session, 'reports' => collect()])->render();
        $this->assertStringContainsString('BELUM LENGKAP', $html);
        $this->getJson(route('inspections.status', $booking))->assertOk()->assertJsonStructure(['fingerprint', 'sessions'])->assertDontSee('document_number');
        $this->get(route('incoming-bookings.show', $booking))->assertOk()->assertSee('data-condition-photos', false)->assertSee('Simpan Draf');
    }

    public function test_financial_review_decision_cannot_be_overridden_by_user_agreements(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->finishStage($booking, $owner, $renter, 'handover');
        $this->readyBoth($booking, $owner, $renter, 'return');
        $this->act($booking, $owner, 'return', 'physical', ['physical_confirmation' => 1])->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'return', 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'return', 'send', $this->report())->assertSessionHasNoErrors();
        $evidence = $this->inspectionSession($booking, 'return')->reports->first();
        $this->act($booking, $admin, 'return', 'resolve', ['note' => 'Nominal memerlukan penyelesaian tersendiri.', 'outcome' => 'financial_review', 'evidence_report_id' => $evidence->id])->assertSessionHasNoErrors();
        $this->act($booking, $owner, 'return', 'agree')->assertSessionHasErrors('inspection');
        $this->act($booking, $renter, 'return', 'agree')->assertSessionHasErrors('inspection');
        $this->actingAs($admin)->patch(route('bookings.payout', $booking), ['action' => 'release', 'note' => 'Coba membuka dana'])->assertSessionHasErrors('payout');
        $this->assertSame('on_hold', $booking->fresh()->payout->status);
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
    }

    public function test_noncooperative_party_can_be_reported_with_photos_before_joint_session_opens(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->finishStage($booking, $owner, $renter, 'handover');
        $this->act($booking, $renter, 'return', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'return', 'supplement', ['note' => 'Pemilik belum membuka sesi; barang sudah dibawa ke lokasi.', 'request_admin_review' => 1, 'photos' => [UploadedFile::fake()->image('return-at-location.jpg')]])->assertSessionHasNoErrors();
        $session = $this->inspectionSession($booking, 'return');
        $this->assertSame('disputed', $session->status);
        $this->assertSame('supplement', $session->reports->first()->kind);
        $this->assertSame('on_hold', $booking->fresh()->payout->status);
        $this->act($booking, $admin, 'return', 'resolve', ['outcome' => 'confirmed', 'note' => 'Penerimaan kembali sudah diperiksa dengan kedua pihak.', 'evidence_report_id' => $session->reports->first()->id, 'physical_event' => 'returned'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->returned_at);
    }

    public function test_cancellation_before_physical_handover_closes_disputed_inspection_without_erasing_evidence(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->act($booking, $renter, 'handover', 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $renter, 'handover', 'supplement', ['note' => 'Tidak sesuai rencana, perlu bantuan.', 'request_admin_review' => 1, 'photos' => [UploadedFile::fake()->image('issue.jpg')]])->assertSessionHasNoErrors();
        $session = $this->inspectionSession($booking, 'handover');
        $evidencePath = $session->reports->first()->photos[0]['path'];
        $this->actingAs($renter)->patch(route('my-bookings.cancel', $booking))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertSame('pending', $booking->fresh()->refund_status);
        $this->assertSame('cancelled', $booking->fresh()->payout->status);
        Storage::disk('local')->assertExists($evidencePath);
        $this->assertNotNull($session->fresh()->closed_at);
    }

    public function test_failed_photo_storage_and_invalid_images_do_not_create_false_submission(): void
    {
        [$booking, $owner, $renter] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', ['function_state' => 'working', 'photos' => [UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('photos.0');
        $this->act($booking, $owner, 'handover', 'send', ['function_state' => 'working', 'photos' => [UploadedFile::fake()->image('large.jpg')->size(5121)]])->assertSessionHasErrors('photos.0');
        $disk = \Mockery::mock(FilesystemAdapter::class)->makePartial();
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasErrors('photos');
        $this->assertCount(0, $this->inspectionSession($booking, 'handover')->reports);
    }

    public function test_admin_cannot_use_evidence_from_another_booking_as_resolution_basis(): void
    {
        [$booking, $owner, $renter, $admin] = $this->booking();
        $other = ConditionReport::factory()->create(['photos' => [['id' => 'other', 'path' => 'other.jpg', 'mime' => 'image/jpeg', 'hash' => 'other']]]);
        $this->act($booking, $admin, 'handover', 'resolve', ['outcome' => 'confirmed', 'note' => 'Bukti bukan booking ini.', 'evidence_report_id' => $other->id])->assertSessionHasErrors('photos');
        $this->assertNull($booking->fresh()->handed_over_at);
        $this->assertSame(0, $booking->conditionSessions()->count());
    }

    public function test_render_interfaces_for_participants_admin_and_identity_forms(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/evidence-no-hot'));
        [$booking, $owner, $renter, $admin] = $this->booking();
        $this->readyBoth($booking, $owner, $renter, 'handover');
        $this->act($booking, $owner, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $renterPage = $this->actingAs($renter)->get(route('my-bookings.show', $booking))->assertOk()->assertSee('Kirim &amp; Kunci Bukti', false);
        $this->capture('renter-upload', $renterPage->getContent(), $booking);
        if (getenv('CAPTURE_EVIDENCE_UI') === '1') {
            foreach (['catalog' => 'browse', 'rent-out' => 'rent-out', 'home' => 'home'] as $name => $route) {
                $this->capture($name, $this->get(route($route))->assertOk()->getContent(), $booking);
            }
        }
        $this->act($booking, $renter, 'handover', 'send', $this->report())->assertSessionHasNoErrors();
        $ownerPage = $this->actingAs($owner)->get(route('incoming-bookings.show', $booking))->assertOk()->assertSee('Kondisi Sesuai')
            ->assertDontSee(route('incoming-bookings.hand-over', $booking), false)
            ->assertSee('Bukti kedua pihak sudah terkirim.');
        $this->capture('owner-review', $ownerPage->getContent(), $booking);
        $adminPage = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Pemeriksaan Admin / Pihak Tidak Kooperatif');
        $this->capture('admin-inspection', $adminPage->getContent(), $booking);
        $ownerItems = $this->actingAs($owner)->get(route('my-items.index'))->assertOk()->assertSee('Persyaratan Foto &amp; Identitas', false);
        $this->capture('owner-settings', $ownerItems->getContent(), $booking);
        $identityForm = $this->actingAs($renter)->get(route('identity.index'))->assertOk()->assertSee('Nomor identitas');
        $this->capture('identity-form', $identityForm->getContent(), $booking);
        $this->post(route('identity.store'), ['document_type' => 'ktp', 'legal_name' => 'Nama Uji', 'document_number' => '1234567890123456', 'consent' => 1, 'document' => UploadedFile::fake()->image('identity.jpg')])->assertSessionHasNoErrors();
        $identity = $renter->currentIdentity();
        $adminIdentity = $this->actingAs($admin)->get(route('identity.show', $identity))->assertOk()->assertDontSee('1234567890123456');
        $this->capture('admin-identity', str_replace(route('identity.document', $identity), 'data:image/jpeg;base64,'.base64_encode(Crypt::decryptString(Storage::disk('local')->get($identity->document_path))), $adminIdentity->getContent()), $booking);
    }

    private function capture(string $name, string $html, Booking $booking): void
    {
        if (getenv('CAPTURE_EVIDENCE_UI') !== '1') {
            return;
        }
        foreach ($booking->fresh()->conditionSessions()->with('reports')->get() as $session) {
            foreach ($session->reports as $report) {
                foreach ($report->photos ?? [] as $photo) {
                    $html = str_replace(route('inspections.photo', [$booking, $report, $photo['id']]), 'data:'.$photo['mime'].';base64,'.base64_encode(Storage::disk('local')->get($photo['path'])), $html);
                }
            }
        }
        foreach ($booking->payments as $payment) {
            $html = str_replace(route('bookings.payment-proof', [$booking, $payment]), 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($payment->proof_path)), $html);
        }
        File::ensureDirectoryExists(storage_path('framework/testing/evidence-ui'));
        file_put_contents(storage_path('framework/testing/evidence-ui/'.$name.'.html'), $html);
    }

    private function booking(bool $photos = true, bool $identity = false, bool $delivery = false, bool $pay = true, bool $freeDelivery = true): array
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $admin = User::factory()->admin()->create();
        UserPaymentMethod::factory()->for($owner)->create(['is_primary' => true]);
        UserPaymentMethod::factory()->for($admin)->create(['is_platform' => true]);
        $item = Item::factory()->for($owner)->create(['quantity' => 1, 'daily_price' => 100000, 'requires_condition_photos' => $photos, 'requires_identity_verification' => $identity, 'delivery_enabled' => $delivery, 'free_delivery' => $delivery && $freeDelivery]);
        $booking = app(BookingService::class)->create($renter, $item, ['start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'quantity' => 1, 'requirements_accepted' => true,
            'fulfillment_method' => $delivery ? 'delivery' : 'pickup', 'recipient_name' => 'Penyewa', 'recipient_phone' => '08123456789', 'recipient_city' => 'Medan', 'recipient_address' => 'Jalan Tujuan 1']);
        if ($pay) {
            $this->pay($booking, $owner, $renter, $admin);
        }

        return [$booking, $owner, $renter, $admin];
    }

    private function pay(Booking $booking, User $owner, User $renter, User $admin): void
    {
        $this->actingAs($owner)->patch(route('incoming-bookings.approve', $booking))->assertSessionHasNoErrors();
        $this->actingAs($renter)->post(route('my-bookings.payments.store', $booking), ['payment_plan' => 'full_transfer', 'method_index' => 0, 'transferred_at' => now(Booking::RentalTimezone)->format('Y-m-d H:i'), 'proof' => UploadedFile::fake()->image('payment.jpg')])->assertSessionHasNoErrors();
        $payment = $booking->payments()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.payments.review', [$booking, $payment]), ['decision' => 'verify', 'transaction_reference' => 'IN-'.$booking->booking_code])->assertSessionHasNoErrors();
    }

    private function act(Booking $booking, User $user, string $stage, string $action, array $data = []): TestResponse
    {
        $session = $booking->conditionSessions()->where('stage', $stage)->reorder()->orderByDesc('attempt')->first();

        return $this->actingAs($user)->post(route('inspections.store', [$booking, $stage]), ['action' => $action] + ($session ? ['session_id' => $session->id] : []) + $data);
    }

    private function inspectionSession(Booking $booking, string $stage): ConditionSession
    {
        return $booking->conditionSessions()->where('stage', $stage)->reorder()->orderByDesc('attempt')->firstOrFail()->load('reports');
    }

    private function readyBoth(Booking $booking, User $owner, User $renter, string $stage): void
    {
        $this->act($booking, $owner, $stage, 'ready')->assertSessionHasNoErrors();
        $this->act($booking, $renter, $stage, 'ready')->assertSessionHasNoErrors();
    }

    private function report(bool $photo = true): array
    {
        return ['function_state' => 'working', 'accessories' => 'Tas dan kabel lengkap.', 'serial_number' => 'SN-123'] + ($photo ? ['photos' => [UploadedFile::fake()->image('condition.jpg')]] : []);
    }

    private function finishStage(Booking $booking, User $owner, User $renter, string $stage): void
    {
        $this->readyBoth($booking, $owner, $renter, $stage);
        $this->act($booking, $owner, $stage, 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $renter, $stage, 'send', $this->report())->assertSessionHasNoErrors();
        $this->act($booking, $owner, $stage, 'agree')->assertSessionHasNoErrors();
        $this->act($booking, $renter, $stage, 'agree')->assertSessionHasNoErrors();
    }
}
