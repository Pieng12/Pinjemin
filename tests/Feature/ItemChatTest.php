<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.chat_enabled' => true]);
    }

    public function test_chat_menu_is_in_account_navigation_and_new_conversations_start_at_the_catalog(): void
    {
        $viewer = User::factory()->create();
        $item = Item::factory()->create();
        $response = $this->actingAs($viewer)->get(route('chat.index'))->assertOk()
            ->assertSee('Jelajahi Barang')->assertDontSee('Chat Baru');
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new \DOMXPath($document))->query('//nav//a[normalize-space(.)="Chat"]');

        $this->assertCount(2, $links);
        foreach ($links as $link) {
            $this->assertSame(route('chat.index'), $link->getAttribute('href'));
            $this->assertSame('page', $link->getAttribute('aria-current'));
            $this->assertTrue((new \DOMXPath($document))->evaluate('boolean(ancestor::div[@id="account-menu" or @id="mobile-menu"])', $link));
        }
        $this->get(route('browse'))->assertOk()
            ->assertSee(route('items.show', $item))
            ->assertDontSee(route('chat.items.create', $item));
        $this->get(route('items.show', $item))->assertOk()
            ->assertSee(route('chat.items.create', $item))
            ->assertSee('Hubungi Pemilik');
    }

    public function test_direct_contact_routes_are_closed(): void
    {
        $viewer = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($viewer)->get('/pesan/baru')->assertNotFound();
        $this->get('/pesan/baru/'.$recipient->id)->assertNotFound();
        $this->postJson('/pesan/baru/'.$recipient->id, $this->message())->assertNotFound();
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_inbox_search_uses_item_names_and_old_direct_threads_are_read_only(): void
    {
        $item = Item::factory()->for(User::factory()->state(['name' => 'Wira Pribadi Unik']), 'user')->create(['name' => 'Kamera Lipat']);
        $itemThread = ChatConversation::factory()->for($item)->create();
        $legacy = ChatConversation::factory()->create([
            'item_id' => null,
            'item_name' => 'Percakapan langsung',
            'renter_id' => $itemThread->renter_id,
            'owner_id' => $itemThread->owner_id,
            'direct_key' => $itemThread->renter_id.':'.$itemThread->owner_id,
        ]);
        ChatMessage::factory()->for($legacy, 'conversation')->create(['body' => 'Halo dari percakapan lama.']);

        $this->actingAs($itemThread->renter)->get(route('chat.index', ['q' => 'Kamera Lipat']))->assertOk()
            ->assertSee(route('chat.show', $itemThread))->assertDontSee(route('chat.show', $legacy));
        $this->get(route('chat.index', ['q' => 'Wira Pribadi Unik']))->assertOk()->assertDontSee(route('chat.show', $itemThread));
        $this->get(route('chat.show', $legacy))->assertOk()->assertSee('Halo dari percakapan lama.')->assertSee('Riwayat chat lama hanya dapat dibaca.');
        $this->getJson(route('chat.updates', $legacy))->assertOk()->assertJsonPath('read_only', true);
        $this->postJson(route('chat.send', $legacy), $this->message())->assertUnprocessable();
        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_guest_returns_to_item_composer_after_signing_in(): void
    {
        $item = Item::factory()->create();
        $renter = User::factory()->create(['password' => 'password123']);
        $composer = route('chat.items.create', $item);

        $this->get(route('browse'))->assertDontSee($composer);
        $this->get(route('items.show', $item))->assertSee($composer);
        $this->get($composer)->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => $renter->email, 'password' => 'password123', 'terms_accepted' => '1',
        ])->assertRedirect($composer);
        $this->get($composer)->assertOk()->assertSee('Hubungi Pemilik');
        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_item_context_and_quick_replies_follow_the_listing_and_viewer_role(): void
    {
        $owner = User::factory()->create();
        $renter = User::factory()->create();
        $laptop = Item::factory()->for($owner, 'user')->create([
            'name' => 'Laptop Kerja', 'daily_price' => 125000, 'city' => 'Medan', 'delivery_enabled' => true,
        ]);
        $projector = Item::factory()->for($owner, 'user')->create([
            'name' => 'Proyektor Acara', 'daily_price' => 175000, 'delivery_enabled' => false,
        ]);
        $photo = ItemPhoto::factory()->for($laptop)->primary()->create(['path' => 'items/laptop-kerja.jpg']);

        $this->actingAs($renter)->get(route('chat.items.create', $laptop))->assertOk()
            ->assertSeeInOrder(['Barang yang dibahas', 'Laptop Kerja', 'Rp 125.000 / hari', 'Medan'])
            ->assertSee($photo->url())->assertSee(route('items.show', $laptop))
            ->assertSee('Halo, apakah Laptop Kerja masih tersedia untuk disewa?')->assertSee('Opsi pengiriman')
            ->assertDontSee('Tanya periode sewa');
        $this->get(route('chat.items.create', $projector))->assertOk()
            ->assertSee('Rp 175.000 / hari')->assertDontSee('Rp 125.000 / hari')->assertDontSee('Opsi pengiriman');
        $this->assertDatabaseCount('chat_messages', 0);

        $this->post(route('chat.items.store', $laptop), $this->message('Apakah tersedia hari Jumat?'))->assertRedirect();
        $conversation = ChatConversation::query()->sole();
        $this->actingAs($owner)->get(route('chat.show', $conversation))->assertOk()
            ->assertSeeInOrder(['Barang yang dibahas', 'Laptop Kerja', 'Rp 125.000 / hari', 'Apakah tersedia hari Jumat?'])
            ->assertSee('Tanya periode sewa')->assertDontSee('Cek ketersediaan')->assertDontSee('Rp 175.000 / hari');
    }

    public function test_first_message_is_retried_safely_and_both_parties_share_it_from_the_booking(): void
    {
        $item = Item::factory()->create();
        $renter = User::factory()->create();
        $data = $this->message('  Apakah perlengkapannya lengkap?  ');
        $url = route('chat.items.store', $item);

        $this->actingAs($renter)->post($url, $data)->assertRedirect();
        $conversation = ChatConversation::query()->sole();
        $thread = route('chat.show', $conversation);
        $this->postJson($url, $data)->assertOk()->assertJsonPath('redirect', $thread);
        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseHas('chat_messages', ['body' => 'Apakah perlengkapannya lengkap?', 'sender_id' => $renter->id]);

        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create(['status' => BookingStatus::Pending]);
        $this->get(route('my-bookings.show', $booking))->assertOk()->assertSee(route('chat.bookings.create', $booking));
        $this->get(route('chat.bookings.create', $booking))->assertRedirect($thread);

        $this->actingAs($item->user)->get(route('incoming-bookings.show', $booking))->assertOk()->assertSee(route('chat.bookings.create', $booking));
        $this->get(route('chat.bookings.create', $booking))->assertRedirect($thread);
        $this->post($thread, $this->message('Perlengkapan tersedia lengkap.'))->assertRedirect($thread);
        $this->actingAs($renter)->get($thread)->assertOk()->assertSeeInOrder(['Apakah perlengkapannya lengkap?', 'Perlengkapan tersedia lengkap.']);
    }

    public function test_only_participants_can_read_send_block_and_report_including_when_outsider_is_an_admin(): void
    {
        $message = ChatMessage::factory()->create();
        $conversation = $message->conversation;
        $reportUrl = route('chat.reports.create', [$conversation, $message]);

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $outsider) {
            $this->actingAs($outsider)->get(route('chat.show', $conversation))->assertNotFound();
            $this->getJson(route('chat.updates', $conversation))->assertNotFound();
            $this->postJson(route('chat.send', $conversation), [])->assertNotFound();
            $this->postJson(route('chat.read', $conversation), ['through' => $message->id])->assertNotFound();
            $this->post(route('chat.block', $conversation))->assertNotFound();
            $this->get($reportUrl)->assertNotFound();
        }

        $this->actingAs($message->sender)->get($reportUrl)->assertNotFound();
        $foreign = ChatMessage::factory()->create();
        $this->actingAs($conversation->owner)
            ->post(route('chat.reports.store', [$conversation, $foreign]), ['reason' => 'spam'])->assertNotFound();
    }

    public function test_read_position_is_monotonic_and_new_incoming_messages_stay_unread(): void
    {
        $conversation = ChatConversation::factory()->create();
        $received = ChatMessage::factory()->for($conversation, 'conversation')->create(['body' => 'Pesan lama']);
        $next = ChatMessage::factory()->for($conversation, 'conversation')->create(['body' => '<script>alert(1)</script>']);
        $this->actingAs($conversation->owner)->get(route('chat.index'))->assertOk()->assertSee('Belum dibaca');

        $updates = $this->getJson(route('chat.updates', ['conversation' => $conversation, 'after' => $received->id]))
            ->assertOk()->assertJsonPath('incoming_id', $next->id)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertStringContainsString('&lt;script&gt;', $updates->json('html'));
        $this->assertStringNotContainsString('<script>', $updates->json('html'));
        $this->postJson(route('chat.read', $conversation), ['through' => $received->id])->assertNoContent();
        $this->get(route('chat.index'))->assertSee('Belum dibaca');
        $this->postJson(route('chat.read', $conversation), ['through' => $next->id])->assertNoContent();
        $this->postJson(route('chat.read', $conversation), ['through' => $received->id])->assertNoContent();
        $this->get(route('chat.index'))->assertDontSee('Belum dibaca');
    }

    public function test_history_has_thirty_messages_and_older_messages_can_be_loaded(): void
    {
        $conversation = ChatConversation::factory()->create();
        $old = ChatMessage::factory()->for($conversation, 'conversation')->create(['body' => 'Pesan paling awal']);
        $recent = ChatMessage::factory()->count(30)->for($conversation, 'conversation')->create();
        $this->actingAs($conversation->renter)
            ->get(route('chat.show', $conversation))->assertOk()->assertSee('Lihat Pesan Lama')->assertDontSee('Pesan paling awal');

        $this->get(route('chat.show', ['conversation' => $conversation, 'before' => $recent->first()->id]))
            ->assertOk()->assertSee($old->body)->assertSee('Lihat Pesan Terbaru');
    }

    public function test_block_applies_to_both_directions_across_items_and_only_blocker_can_remove_it(): void
    {
        $first = ChatConversation::factory()->create();
        $another = Item::factory()->for($first->owner, 'user')->create();
        $second = ChatConversation::factory()->for($another, 'item')->for($first->renter, 'renter')->create();
        $this->actingAs($first->owner)->post(route('chat.block', $first))->assertRedirect();
        $this->postJson(route('chat.send', $second), $this->message())->assertUnprocessable();
        $this->actingAs($first->renter)->postJson(route('chat.send', $first), $this->message())->assertUnprocessable();
        $this->delete(route('chat.unblock', $first))->assertRedirect();
        $this->get(route('chat.show', $first))->assertOk()->assertSee('Pengiriman pesan antara kedua akun tidak tersedia.');
        $this->postJson(route('chat.send', $second), $this->message())->assertUnprocessable();

        $this->actingAs($first->owner)->delete(route('chat.unblock', $second))->assertRedirect();
        $this->postJson(route('chat.send', $second), $this->message())->assertOk();
        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_admin_sees_only_the_reported_message_and_records_one_decision(): void
    {
        $reported = ChatMessage::factory()->create(['body' => 'Pesan yang dilaporkan']);
        $conversation = $reported->conversation;
        ChatMessage::factory()->for($conversation, 'conversation')->create(['body' => 'Pesan privat lainnya']);
        $path = route('chat.reports.store', [$conversation, $reported]);
        $this->actingAs($conversation->owner)->get(route('chat.reports.create', [$conversation, $reported]))->assertOk();
        $this->post($path, ['reason' => 'other'])->assertSessionHasErrors(['details']);
        $this->post($path, ['reason' => 'fraud'])->assertRedirect();
        $this->post($path, ['reason' => 'spam'])->assertRedirect();
        $this->assertDatabaseCount('chat_reports', 1);
        $this->get(route('admin.chat-reports.index'))->assertForbidden();

        $report = ChatReport::query()->sole();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.chat-reports.index'))
            ->assertOk()->assertSee($reported->body)->assertDontSee('Pesan privat lainnya');
        $resolve = route('admin.chat-reports.resolve', $report);
        $this->patch($resolve, [])->assertSessionHasErrors(['resolution']);
        $this->patch($resolve, ['resolution' => 'Laporan telah diperiksa.'])->assertRedirect();
        $this->patch($resolve, ['resolution' => 'Percobaan mengubah keputusan.'])->assertRedirect();
        $this->get(route('admin.chat-reports.index'))->assertDontSee($reported->body);
        $this->get(route('admin.chat-reports.index', ['status' => 'resolved']))->assertSee('Laporan telah diperiksa.');
        $this->assertDatabaseHas('chat_reports', ['id' => $report->id, 'resolved_by' => $admin->id, 'resolution' => 'Laporan telah diperiksa.']);
    }

    public function test_existing_chat_survives_item_deletion_and_becomes_read_only(): void
    {
        $receipt = ChatMessage::factory()->create(['body' => 'Catatan yang tetap dapat dibaca']);
        $conversation = $receipt->conversation;
        $itemName = $conversation->item->name;
        $conversation->item->delete();

        $this->actingAs($conversation->renter)->get(route('chat.show', $conversation))
            ->assertOk()->assertSee($itemName)->assertSee($receipt->body)->assertSee('Barang telah dihapus.');
        $this->postJson(route('chat.send', $conversation), $this->message())->assertUnprocessable();
    }

    public function test_inactive_item_can_start_chat_from_active_booking_and_self_chat_is_unavailable(): void
    {
        $item = Item::factory()->inactive()->create();
        $renter = User::factory()->create();
        $booking = Booking::factory()->for($item)->for($renter, 'renter')->create(['status' => BookingStatus::Pending]);
        $otherBooking = Booking::factory()->for($item)->create(['status' => BookingStatus::Completed]);

        $this->actingAs($renter)->get(route('chat.items.create', $item))->assertNotFound();
        $this->postJson(route('chat.items.store', $item), $this->message())->assertNotFound();
        $this->actingAs($item->user)->get(route('chat.items.create', $item))->assertNotFound();
        $this->get(route('chat.bookings.create', $otherBooking))->assertNotFound();
        $this->get(route('chat.bookings.create', $booking))->assertOk()->assertSee($renter->name);
        $this->post(route('chat.bookings.store', $booking), $this->message())->assertRedirect();

        $chat = ChatConversation::query()->sole();
        $this->actingAs($renter)->get(route('chat.bookings.create', $booking))->assertRedirect(route('chat.show', $chat));
    }

    public function test_send_validates_text_and_limits_attempts_across_both_entry_points(): void
    {
        $conversation = ChatConversation::factory()->create();
        $this->actingAs($conversation->renter);
        $this->postJson(route('chat.send', $conversation), $this->message('   '))->assertJsonValidationErrors('body');
        $this->postJson(route('chat.send', $conversation), $this->message(str_repeat('a', 2001)))->assertJsonValidationErrors('body');

        for ($attempt = 0; $attempt < 18; $attempt++) {
            $this->postJson(route('chat.send', $conversation), $this->message())->assertOk();
        }

        $this->postJson(route('chat.items.store', $conversation->item), $this->message())->assertTooManyRequests();
        $this->assertDatabaseCount('chat_messages', 18);
    }

    public function test_catalog_category_shortcuts_use_active_inventory_and_keep_the_other_filters(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik', 'slug' => 'elektronik']);
        Item::factory()->for($category)->create(['name' => 'Kamera', 'city' => 'Medan']);
        Category::factory()->create(['name' => 'Kategori Kosong']);

        $response = $this->get(route('browse', ['q' => 'Kamera', 'city' => 'Medan', 'page' => 2]))->assertOk()
            ->assertSee(route('browse', ['q' => 'Kamera', 'city' => 'Medan', 'category' => 'elektronik']))
            ->assertSee('Barang tidak ditemukan.');

        $this->assertStringNotContainsString('Kategori Kosong', Str::between($response->getContent(), 'aria-label="Kategori barang"', '</nav>'));
    }

    public function test_disabled_chat_hides_catalog_entry_points_and_rejects_requests(): void
    {
        config(['features.chat_enabled' => false]);
        $item = Item::factory()->create();
        $this->actingAs(User::factory()->create())->get(route('browse'))->assertOk()->assertDontSee('Hubungi Pemilik');
        $this->get(route('chat.items.create', $item))->assertNotFound();
    }

    /** @return array{body: string, submission_id: string} */
    private function message(string $body = 'Apakah barang masih tersedia?'): array
    {
        return ['body' => $body, 'submission_id' => (string) Str::uuid()];
    }
}
