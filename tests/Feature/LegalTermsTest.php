<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Item;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalTermsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_terms_and_privacy_remain_public_when_marketplace_is_disabled(): void
    {
        config(['features.marketplace_enabled' => false]);

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('Syarat &amp; Ketentuan', false)->assertSee(config('legal.terms_version'))
            ->assertDontSee('Syarat &amp;amp; Ketentuan', false)
            ->assertSee('5% dari subtotal sewa')->assertSee('tidak mengenakan denda atau biaya tambahan otomatis')
            ->assertSee('id="masalah-barang"', false)->assertSee('id="pembatalan"', false);
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Kebijakan Privasi')->assertSee('hanya dapat diakses pemilik akun tersebut dan admin berwenang')
            ->assertSee('ditinjau setiap 30 hari')->assertSee(config('legal.privacy_version'));
        $this->assertGuest();
    }

    public function test_registration_without_explicit_consent_does_not_create_an_account(): void
    {
        $this->post(route('register'), $this->registration())->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseMissing('users', ['email' => 'legal@example.test']);
        $this->assertGuest();
    }

    public function test_registration_records_server_version_and_time_instead_of_client_claims(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->post(route('register'), $this->registration() + [
            'terms_accepted' => '1', 'terms_version' => 'forged', 'terms_accepted_at' => '2000-01-01',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'legal@example.test')->sole();
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertSame(config('legal.privacy_version'), $user->privacy_version);
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertAuthenticatedAs($user);
    }

    public function test_booking_requires_consent_even_when_photos_and_identity_are_optional(): void
    {
        $item = Item::factory()->create(['requires_condition_photos' => false, 'requires_identity_verification' => false]);
        $renter = User::factory()->create();
        $payload = $this->bookingPayload();

        $this->actingAs($renter)->post(route('items.bookings.store', $item), $payload)->assertSessionHasErrors('requirements_accepted');
        $this->assertDatabaseCount('bookings', 0);
        $this->post(route('items.bookings.store', $item), $payload + ['requirements_accepted' => '1'])->assertSessionHasNoErrors();
        $booking = $item->bookings()->sole();
        $this->assertSame(config('legal.terms_version'), $booking->fulfillment_snapshot['inspection_policy']['terms_version']);
        $this->assertNotEmpty($booking->fulfillment_snapshot['inspection_policy']['accepted_at']);
        $this->assertSame($item->rules, $booking->fulfillment_snapshot['inspection_policy']['item_rules']);
    }

    public function test_internal_booking_creation_cannot_accept_a_false_string_as_consent(): void
    {
        $item = Item::factory()->create();
        $renter = User::factory()->create();
        try {
            app(BookingService::class)->create($renter, $item, $this->bookingPayload() + ['requirements_accepted' => 'false']);
            $this->fail('Explicit consent is required for new bookings.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('requirements_accepted', $exception->errors());
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_legacy_account_records_agreement_only_after_explicit_login_consent(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('terms_accepted');
        $this->assertNull($user->fresh()->terms_version);
        $this->assertNull($user->fresh()->terms_accepted_at);
        $this->assertGuest();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123', 'terms_accepted' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertSame(config('legal.terms_version'), $user->fresh()->terms_version);
        $this->assertSame(config('legal.privacy_version'), $user->fresh()->privacy_version);
        $this->assertNotNull($user->fresh()->terms_accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_false_consent_even_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123', 'terms_accepted' => '0'])
            ->assertSessionHasErrors(['terms_accepted' => 'Setujui Syarat & Ketentuan dan Kebijakan Privasi sebelum masuk.']);
        $this->assertGuest();
        $this->assertNull($user->fresh()->terms_accepted_at);
    }

    public function test_new_version_is_recorded_on_login_and_same_version_keeps_its_original_acceptance_time(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create(['password' => 'password123', 'terms_version' => 'older', 'privacy_version' => 'older', 'terms_accepted_at' => now()->subDay()]);
        $credentials = ['email' => $user->email, 'password' => 'password123', 'terms_accepted' => '1'];
        $this->post(route('login'), $credentials)->assertRedirect(route('dashboard'));
        $this->assertSame(config('legal.terms_version'), $user->fresh()->terms_version);
        $this->assertSame(config('legal.privacy_version'), $user->fresh()->privacy_version);
        $this->assertTrue($user->fresh()->terms_accepted_at->equalTo(now()));
        $acceptedAt = $user->fresh()->terms_accepted_at;
        $this->post(route('logout'));
        $this->travel(10)->minutes();
        $this->post(route('login'), $credentials)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->terms_accepted_at->equalTo($acceptedAt));
    }

    public function test_legal_links_and_native_consent_are_available_from_relevant_forms(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/legal-no-hot'));
        foreach (['terms' => 'legal.terms', 'privacy' => 'legal.privacy', 'login' => 'login', 'register' => 'register'] as $name => $route) {
            $response = $this->get(route($route))->assertOk()->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
            $this->capture($name, $response->getContent());
        }
        $this->get(route('register'))->assertSee('name="terms_accepted" value="1" required', false);
        $this->get(route('login'))->assertSee('name="terms_accepted" value="1" required', false)
            ->assertSee('data-legal-modal', false)->assertSee('data-legal-panel="privacy"', false);

        $user = User::factory()->create();
        $item = Item::factory()->create(['requires_condition_photos' => false, 'requires_identity_verification' => false]);
        $this->actingAs($user);
        foreach (['item' => route('items.show', $item), 'owner' => route('my-items.create'), 'identity' => route('identity.index')] as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
            $this->capture($name, $response->getContent());
        }
        $this->get(route('items.show', $item))->assertSee('name="requirements_accepted" value="1" required', false);
    }

    /** @return array<string, string> */
    private function registration(): array
    {
        return ['name' => 'Legal User', 'email' => 'legal@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
    }

    /** @return array<string, mixed> */
    private function bookingPayload(): array
    {
        return ['start_date' => now(Booking::RentalTimezone)->addDay()->toDateString(), 'end_date' => now(Booking::RentalTimezone)->addDays(2)->toDateString(), 'quantity' => 1];
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('CAPTURE_LEGAL_UI') !== '1') {
            return;
        }
        File::ensureDirectoryExists(storage_path('framework/testing/legal-ui'));
        File::put(storage_path('framework/testing/legal-ui/'.$name.'.html'), $html);
    }
}
