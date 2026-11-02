<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\IdentityVerificationService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-01 09:00:00', Booking::RentalTimezone)->utc());
    }

    public function test_identity_is_encrypted_private_and_admin_access_is_audited(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('identity.jpg');
        $original = $file->get();
        $this->actingAs($user)->post(route('identity.store'), $this->payload(['document' => $file]))->assertSessionHasNoErrors();
        $identity = $user->currentIdentity();
        $row = DB::table('identity_verifications')->find($identity->id);
        $this->assertNotSame('1234567890123456', $row->document_number);
        $this->assertNotSame('Nama Resmi Privat', $row->legal_name);
        $encrypted = Storage::disk('local')->get($identity->document_path);
        $this->assertNotSame($original, $encrypted);
        $this->assertSame($original, Crypt::decryptString($encrypted));
        $this->get(route('identity.document', $identity))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('identity.document', $identity))->assertForbidden();
        $this->get(route('identity.show', $identity))->assertForbidden();
        $this->post(route('admin.identity.review', $identity), ['action' => 'verify'])->assertForbidden();
        $this->actingAs($admin)->get(route('identity.show', $identity))->assertOk()->assertSee('Nama Resmi Privat')->assertDontSee('1234567890123456');
        $this->get(route('identity.document', $identity))->assertOk();
        $this->assertDatabaseHas('identity_verification_events', ['identity_verification_id' => $identity->id, 'action' => 'document_viewed', 'actor_id' => $admin->id]);
        $this->post(route('admin.identity.review', $identity), ['action' => 'verify'])->assertSessionHasNoErrors();
        $this->assertTrue($identity->fresh()->isValid());
        $this->assertSame('2027-11-01 02:00:00', $identity->fresh()->verified_until->format('Y-m-d H:i:s'));
        $this->actingAs($user)->get(route('identity.index'))->assertOk()->assertSee('Terverifikasi');
    }

    public function test_validation_requires_consent_valid_document_and_does_not_flash_identity_fields(): void
    {
        $this->actingAs(User::factory()->create())->post(route('identity.store'), $this->payload(['consent' => 0, 'document_number' => 'short']))->assertSessionHasErrors(['consent', 'document_number']);
        $this->assertNull(session()->getOldInput('document_number'));
        $this->assertNull(session()->getOldInput('legal_name'));
        $this->post(route('identity.store'), $this->payload(['document' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('document');
        $this->post(route('identity.store'), $this->payload(['document_type' => 'sim', 'document_number' => '123456789', 'document_expires_on' => '2026-10-31']))->assertSessionHasErrors('document_expires_on');
        $this->assertDatabaseCount('identity_verifications', 0);
    }

    public function test_sim_verification_expires_at_document_expiry_if_sooner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('identity.store'), $this->payload(['document_type' => 'sim', 'document_number' => '123456789', 'document_expires_on' => '2026-12-01']))->assertSessionHasNoErrors();
        $identity = $user->currentIdentity();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.identity.review', $identity), ['action' => 'verify'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-01 23:59:59', $identity->fresh()->verified_until->timezone(Booking::RentalTimezone)->format('Y-m-d H:i:s'));
        $this->travelTo(Carbon::parse('2026-12-02', Booking::RentalTimezone)->utc());
        $this->assertFalse($identity->fresh()->isValid());
    }

    public function test_rejection_withdrawal_and_reapplication_keep_decision_history(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($user)->post(route('identity.store'), $this->payload())->assertSessionHasNoErrors();
        $first = $user->currentIdentity();
        $this->post(route('identity.store'), $this->payload())->assertSessionHasErrors('identity');
        $this->assertCount(1, Storage::disk('local')->allFiles('identity-documents'));
        $this->actingAs($admin)->post(route('admin.identity.review', $first), ['action' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('admin.identity.review', $first), ['action' => 'reject', 'note' => 'Gambar tidak terbaca.'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('identity.store'), $this->payload())->assertSessionHasNoErrors();
        $second = $user->currentIdentity();
        $this->assertNotSame($first->id, $second->id);
        $this->post(route('identity.withdraw', $second), ['note' => 'Saya ingin memperbaiki foto.'])->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $second->fresh()->status);
        $this->assertDatabaseHas('identity_verification_events', ['identity_verification_id' => $first->id, 'action' => 'reject']);
        $this->travel(31)->days();
        $this->artisan('rentals:maintain-evidence --prune')->assertSuccessful();
        $this->assertNotNull($first->fresh()->purged_at);
        $this->assertNotNull($second->fresh()->purged_at);
        $this->assertNull($second->fresh()->document_number);
        $this->assertCount(0, Storage::disk('local')->allFiles('identity-documents'));
    }

    public function test_active_booking_defers_identity_deletion_until_case_ends(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('identity.store'), $this->payload())->assertSessionHasNoErrors();
        $identity = $user->currentIdentity();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.identity.review', $identity), ['action' => 'verify'])->assertSessionHasNoErrors();
        $booking = Booking::factory()->approved()->for($user, 'renter')->create(['identity_verification_id' => $identity->id]);
        $this->travel(367)->days();
        $this->assertSame(0, app(IdentityVerificationService::class)->prune());
        Storage::disk('local')->assertExists($identity->document_path);
        $this->assertNotNull($identity->fresh()->hold_review_at);
        $booking->update(['status' => 'completed']);
        $this->assertSame(1, app(IdentityVerificationService::class)->prune());
        Storage::disk('local')->assertMissing($identity->document_path);
        $this->assertNull($identity->fresh()->legal_name);
    }

    public function test_admin_retention_hold_and_disclosure_require_reasons_and_are_recorded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('identity.store'), $this->payload())->assertSessionHasNoErrors();
        $identity = $user->currentIdentity();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.identity.review', $identity), ['action' => 'hold'])->assertSessionHasErrors('note');
        $this->post(route('admin.identity.review', $identity), ['action' => 'hold', 'note' => 'Permintaan pemeriksaan kasus X.'])->assertSessionHasNoErrors();
        $this->post(route('admin.identity.review', $identity), ['action' => 'disclosure', 'note' => 'Instansi berwenang, permintaan resmi kasus X.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('identity_verification_events', ['identity_verification_id' => $identity->id, 'action' => 'disclosure', 'actor_id' => $admin->id]);
        $this->get(route('admin.identity.index', ['status' => 'retention']))->assertOk()->assertSee($user->name);
        $this->post(route('admin.identity.review', $identity), ['action' => 'release_hold', 'note' => 'Kasus sudah ditutup.'])->assertSessionHasNoErrors();
        $this->assertNull($identity->fresh()->hold_reason);
    }

    public function test_failed_encrypted_upload_does_not_create_verification(): void
    {
        $disk = \Mockery::mock(FilesystemAdapter::class)->makePartial();
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->actingAs(User::factory()->create())->post(route('identity.store'), $this->payload())->assertSessionHasErrors('document');
        $this->assertDatabaseCount('identity_verifications', 0);
    }

    public function test_rejected_old_document_is_not_retained_for_unrelated_booking_using_new_identity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('identity.store'), $this->payload())->assertSessionHasNoErrors();
        $old = $user->currentIdentity();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.identity.review', $old), ['action' => 'reject', 'note' => 'Perlu unggahan yang terbaca.'])->assertSessionHasNoErrors();
        $this->travel(31)->days();
        $current = IdentityVerification::factory()->verified()->for($user)->create();
        Booking::factory()->approved()->for($user, 'renter')->create(['identity_verification_id' => $current->id,
            'fulfillment_snapshot' => ['inspection_policy' => ['version' => 1, 'identity_required' => true]]]);
        $this->assertSame(1, app(IdentityVerificationService::class)->prune());
        $this->assertNotNull($old->fresh()->purged_at);
        $this->assertNull($current->fresh()->purged_at);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['document_type' => 'ktp', 'legal_name' => 'Nama Resmi Privat', 'document_number' => '1234567890123456', 'consent' => 1, 'document' => UploadedFile::fake()->image('ktp.jpg')], $overrides);
    }
}
