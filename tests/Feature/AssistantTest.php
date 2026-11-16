<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_assistant_has_curated_help_and_login_destinations_without_admin_topics(): void
    {
        $response = $this->get(route('home'))->assertSee('data-assistant-launcher', false)
            ->assertSee('Informasi layanan otomatis')->assertSee('Bantuan Pinjemin')
            ->assertSee('images/maskot-pinjemin-assistant.webp', false)
            ->assertSee('data-assistant-tutorial', false)->assertSee('Jalankan Tutorial')
            ->assertSee('Pilih Panduan Layanan')->assertSee('Informasi Layanan')
            ->assertSee('id="assistant-guides-panel"', false)->assertSee('data-guide-list', false);
        $data = $this->assistantData($response->getContent());

        $this->assertSame('guest', $data['context']);
        $this->assertSame('home', $data['page']);
        $this->assertTrue($data['marketplaceEnabled']);
        $this->assertSame('guest', $data['scope']);
        $this->assertSame([], array_filter($data['topics'], fn ($topic) => $topic['admin'] ?? false));
        $payment = collect($data['topics'])->keyBy('id')->get('payment');
        $this->assertContains(['label' => 'Masuk untuk melanjutkan', 'url' => route('login')], $payment['actions']);
        foreach ($data['topics'] as $topic) {
            foreach ($topic['actions'] as $action) {
                $this->assertStringNotContainsString('/admin', $action['url']);
            }
        }
        $this->capture('home', $response->getContent());
    }

    public function test_promotion_assistant_excludes_transaction_topics_and_links(): void
    {
        config(['features.marketplace_enabled' => false]);

        $response = $this->get(route('home'))->assertSee('Bantuan Pinjemin');
        $data = $this->assistantData($response->getContent());

        $this->assertSame('promotion', $data['context']);
        $this->assertFalse($data['marketplaceEnabled']);
        $this->assertSame(['about', 'audience', 'categories', 'benefits', 'rules', 'privacy', 'contact'], array_column($data['topics'], 'id'));
        foreach (['login', 'register', 'browse', 'my-bookings.index', 'owner.dashboard', 'admin.dashboard'] as $route) {
            $this->assertStringNotContainsString(route($route), json_encode($data, JSON_UNESCAPED_SLASHES));
        }
        $this->assertContains(['label' => 'Email Pinjemin', 'url' => 'mailto:pinjemin99@gmail.com'], $data['contacts']);
        $this->capture('promotion', $response->getContent());
    }

    public function test_authentication_pages_share_the_same_help_component(): void
    {
        foreach (['login', 'register'] as $name) {
            $response = $this->get(route($name))->assertSee('data-assistant-dialog', false);
            $this->assertSame('guest', $this->assistantData($response->getContent())['context']);
            $this->capture($name, $response->getContent());
        }
    }

    public function test_legal_pages_keep_help_available(): void
    {
        foreach (['terms', 'privacy'] as $document) {
            $response = $this->get(route('legal.'.$document))->assertSee('data-assistant-dialog', false);
            $this->capture($document, $response->getContent());
        }
    }

    public function test_rental_and_owner_pages_expose_context_for_their_guided_tutorials(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $item = Item::factory()->for($owner)->create();
        $this->actingAs($renter);
        $this->capture('journey-account', $this->get(route('dashboard'))->getContent());
        $this->capture('identity-journey', $this->get(route('identity.index'))->getContent());

        foreach ([['catalog', 'browse', []], ['item', 'items.show', [$item]]] as [$name, $route, $parameters]) {
            $response = $this->get(route($route, $parameters))->assertSee('data-tutorial-dialog', false);
            $this->assertSame($route, $this->assistantData($response->getContent())['page']);
            $this->capture($name, $response->getContent());
        }
        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create([
            'status' => 'awaiting_payment', 'accepted_at' => now(), 'payment_due_at' => now()->addHours(24),
            'payment_legacy' => false, 'total_amount' => 320000, 'deposit_amount' => 50000,
            'fulfillment_snapshot' => ['payment_collection' => 'platform', 'payment_policy' => ['allow_deposit' => true]],
            'payment_methods_snapshot' => [['type' => 'bank', 'label' => 'Bank Contoh', 'account_name' => 'Pinjemin', 'account_identifier' => '123123123']],
        ]);
        $response = $this->get(route('my-bookings.show', $booking))->assertSee('data-tutorial-dialog', false);
        $this->assertSame('my-bookings.show', $this->assistantData($response->getContent())['page']);
        $this->capture('booking', $response->getContent());
        $this->capture('booking-list', $this->get(route('my-bookings.index'))->getContent());
        $this->actingAs($owner);
        $this->capture('owner-account', $this->get(route('dashboard'))->getContent());
        $this->capture('owner-item', $this->get(route('my-items.show', $item))->getContent());
        foreach ([['create-item', 'my-items.create', []], ['edit-item', 'my-items.edit', [$item]], ['rent-out', 'rent-out', []]] as [$name, $route, $parameters]) {
            $response = $this->get(route($route, $parameters))->assertSee('data-tutorial-dialog', false);
            $this->assertSame($route, $this->assistantData($response->getContent())['page']);
            $this->capture($name, $response->getContent());
        }
    }

    public function test_workspace_and_account_priorities_follow_the_viewer_and_page(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => User::RoleAdmin]);

        foreach ([[$user, 'owner.dashboard', 'owner'], [$user, 'profile.edit', 'renter'], [$admin, 'admin.dashboard', 'admin']] as [$viewer, $route, $context]) {
            $response = $this->actingAs($viewer)->get(route($route))->assertSee('data-assistant-launcher', false);
            $data = $this->assistantData($response->getContent());

            $this->assertSame($context, $data['context']);
            $this->assertSame(config('assistant.initial_topics.'.$context), $data['initial']);
            $this->assertSame($context === 'admin' ? 5 : 0, count(array_filter($data['topics'], fn ($topic) => $topic['admin'] ?? false)));
            $this->capture($context, $response->getContent());
        }
    }

    public function test_assistant_exposes_no_account_details_and_changes_scope_between_users(): void
    {
        $one = User::factory()->make(['id' => 401, 'email' => 'private-one@example.test', 'phone' => '081234567890', 'address' => 'Alamat rahasia satu']);
        $two = User::factory()->make(['id' => 402, 'email' => 'private-two@example.test']);

        $first = $this->assistantData($this->actingAs($one)->get(route('home'))->getContent());
        $second = $this->assistantData($this->actingAs($two)->get(route('home'))->getContent());

        $this->assertNotSame($first['scope'], $second['scope']);
        foreach ([$one->email, $one->phone, $one->address, $one->name, $two->email] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, json_encode($first));
        }
        $this->assertStringNotContainsString(route('register'), json_encode($first, JSON_UNESCAPED_SLASHES));
    }

    public function test_admin_identity_detail_keeps_admin_help_without_exposing_identity_data(): void
    {
        $admin = User::factory()->create(['role' => User::RoleAdmin]);
        $identity = IdentityVerification::factory()->create(['document_number' => '9876543210123456', 'legal_name' => 'Nama Dokumen Privat']);

        $response = $this->actingAs($admin)->get(route('identity.show', $identity));
        $data = $this->assistantData($response->getContent());

        $this->assertSame('admin', $data['context']);
        $this->assertStringNotContainsString('9876543210123456', json_encode($data));
        $this->assertStringNotContainsString('Nama Dokumen Privat', json_encode($data));
    }

    public function test_shared_faq_content_is_safely_embedded_in_html_and_json(): void
    {
        $text = 'Pertanyaan contoh </script><img src=x onerror=alert(1)>';
        config(['assistant.topics.about.answer' => $text]);

        $response = $this->get(route('home'))->assertDontSee($text, false)->assertSee($text);

        $data = $this->assistantData($response->getContent());
        $this->assertSame($text, collect($data['topics'])->keyBy('id')->get('about')['answer']);
    }

    public function test_rendered_answers_preserve_payment_and_dispute_rules(): void
    {
        $data = $this->assistantData($this->get(route('home'))->getContent());
        $topics = collect($data['topics'])->keyBy('id');

        $this->assertStringContainsString('DP merupakan bagian dari total, bukan biaya tambahan atau uang jaminan', $topics['down-payment']['answer']);
        $this->assertStringContainsString('sisa Rp270.000', $topics['down-payment']['answer']);
        $this->assertStringContainsString('Pinjemin yang tercantum pada detail pemesanan', $topics['payment']['answer']);
        $this->assertStringContainsString('Pemesanan lama mengikuti metode', $topics['payment']['answer']);
        $this->assertStringContainsString('5% dari subtotal sewa', $topics['commission']['answer']);
        $this->assertStringContainsString('Biaya pengiriman tidak dikenai komisi', $topics['commission']['answer']);
        $this->assertStringContainsString('tanpa denda atau penagihan tambahan otomatis', $topics['late']['answer']);
        $this->assertStringContainsString('tidak ada penentuan kesalahan, tarif ganti rugi, atau pemotongan otomatis', $topics['damage']['answer']);
    }

    public function test_mascot_asset_is_small_and_really_transparent(): void
    {
        $path = public_path('images/maskot-pinjemin-assistant.webp');
        $this->assertFileExists($path);
        $this->assertLessThan(100000, filesize($path));
        $image = imagecreatefromwebp($path);

        $this->assertSame(288, imagesx($image));
        $this->assertSame(127, imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha']);
        imagedestroy($image);
    }

    /** @return array<string, mixed> */
    private function assistantData(string $html): array
    {
        preg_match('/<script type="application\/json" data-assistant-config>(.*?)<\/script>/s', $html, $matches);
        $this->assertArrayHasKey(1, $matches);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function capture(string $name, string $html): void
    {
        if (getenv('CAPTURE_ASSISTANT_UI') !== '1') {
            return;
        }
        File::ensureDirectoryExists(storage_path('framework/testing/assistant-ui'));
        File::put(storage_path('framework/testing/assistant-ui/'.$name.'.html'), $html);
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(Vite::class)->useHotFile(storage_path('framework/testing/assistant-no-hot'));
    }
}
