<?php

namespace Tests\Feature;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_panel_is_closed_without_filters(): void
    {
        $this->get('/jelajahi')
            ->assertSee('<details class="catalog-filter-details" >', false)
            ->assertSee('Temukan barang untuk kebutuhan Anda');
    }

    public function test_filter_chips_use_readable_labels_and_removal_preserves_other_filters_without_page(): void
    {
        $category = Category::factory()->create(['name' => 'Kamera Fotografi', 'slug' => 'kamera-fotografi']);
        Item::factory()->for($category)->create(['city' => 'Medan']);

        $this->get('/jelajahi?category=kamera-fotografi&city=Medan&sort=price_asc&page=2')
            ->assertSee('<details class="catalog-filter-details"  open >', false)
            ->assertSee('Kategori:')
            ->assertSee('Kamera Fotografi')
            ->assertSee('href="'.e(route('browse', ['city' => 'Medan', 'sort' => 'price_asc'])).'"', false)
            ->assertSee('aria-label="Hapus filter Kategori"', false);
    }

    public function test_demo_photo_is_replaced_with_an_explicit_fallback(): void
    {
        ItemPhoto::factory()->primary()->create(['path' => 'items/demo/pinjemin-placeholder.png']);

        $this->get('/jelajahi')
            ->assertSee('Foto belum tersedia')
            ->assertDontSee('items/demo/pinjemin-placeholder.png');
    }

    public function test_uploaded_photo_is_preserved_in_the_catalog(): void
    {
        ItemPhoto::factory()->primary()->create(['path' => 'items/original-camera.jpg']);

        $this->get('/jelajahi')
            ->assertSee(Storage::disk('public')->url('items/original-camera.jpg'))
            ->assertSee('object-contain');
    }

    public function test_catalog_pagination_preserves_search_city_and_sort(): void
    {
        Item::factory()->count(13)->create(['name' => 'Kamera Sewa', 'city' => 'Medan']);

        $response = $this->get('/jelajahi?q=Kamera&city=Medan&sort=price_asc');

        $this->assertSame(12, $response['items']->count());
        $response->assertSee('href="'.e(route('browse', ['q' => 'Kamera', 'city' => 'Medan', 'sort' => 'price_asc', 'page' => 2])).'"', false);
    }

    public function test_period_filter_uses_stock_throughout_the_range_before_pagination(): void
    {
        $today = now(Booking::RentalTimezone);
        $start = $today->copy()->addDays(2)->toDateString();
        $end = $today->copy()->addDays(3)->toDateString();
        $items = Item::factory()->count(13)->create(['quantity' => 2, 'daily_price' => 100000]);
        $rotating = $items->first();
        foreach ([$start, $end] as $day) {
            Booking::factory()->for($rotating)->approved()->create(['start_date' => $day, 'end_date' => $day, 'quantity' => 1]);
        }
        $expired = $items->last();
        Booking::factory()->for($expired)->create(['start_date' => $start, 'end_date' => $end, 'quantity' => 2, 'status' => BookingStatus::AwaitingRenterConfirmation, 'quote_expires_at' => now()->subMinute()]);

        $full = Item::factory()->create(['quantity' => 1]);
        Booking::factory()->for($full)->approved()->create(['start_date' => $end, 'end_date' => $end, 'quantity' => 1]);
        $overdue = Item::factory()->create(['quantity' => 1]);
        Booking::factory()->for($overdue)->approved()->create([
            'start_date' => $today->copy()->subDays(2)->toDateString(), 'end_date' => $today->copy()->subDay()->toDateString(),
            'quantity' => 1, 'handed_over_at' => now()->subDays(2),
        ]);

        $period = ['start_date' => $start, 'end_date' => $end, 'quantity' => 1];
        $response = $this->get(route('browse', ['sort' => 'price_asc', ...$period]))->assertOk();
        $this->assertSame(13, $response['items']->total());
        $this->assertSame(12, $response['items']->count());
        $response->assertSee(route('items.show', ['item' => $expired, ...$period]));
        $second = $this->get(route('browse', ['sort' => 'price_asc', ...$period, 'page' => 2]))->assertOk();
        $this->assertSame([$rotating->id], $second['items']->getCollection()->modelKeys());
        $this->get(route('browse', [...$period, 'quantity' => 2]))->assertViewHas('items', fn ($results) => $results->total() === 12);
    }

    public function test_invalid_period_reopens_the_catalog_filter_with_its_previous_values(): void
    {
        $start = now(Booking::RentalTimezone)->addDay()->toDateString();
        $this->followingRedirects()->get(route('browse', ['start_date' => $start, 'quantity' => 2]))
            ->assertOk()->assertSee('Isi tanggal pengembalian untuk menyaring ketersediaan.')
            ->assertSee('name="start_date" value="'.$start.'"', false)
            ->assertSee('name="quantity" value="2"', false)
            ->assertSee('<details class="catalog-filter-details"  open >', false);
    }

    public function test_catalog_selection_survives_sign_in_and_detail_reports_changed_stock(): void
    {
        $period = ['start_date' => now(Booking::RentalTimezone)->addDay()->toDateString(), 'end_date' => now(Booking::RentalTimezone)->addDay()->toDateString(), 'quantity' => 2];
        $item = Item::factory()->create(['quantity' => 2, 'daily_price' => 75000]);
        $renter = User::factory()->create(['password' => 'test-password-123']);
        $detail = route('items.show', ['item' => $item, ...$period]);
        $continue = route('items.continue-rental', ['item' => $item, ...$period]);
        $this->get($detail)->assertOk()->assertSee($continue);
        $this->get($continue)->assertRedirect(route('login'));

        Booking::factory()->for($item)->approved()->create(['start_date' => $period['start_date'], 'end_date' => $period['end_date'], 'quantity' => 1]);
        $login = $this->post(route('login'), ['email' => $renter->email, 'password' => 'test-password-123', 'terms_accepted' => '1'])->assertRedirectContains(parse_url($continue, PHP_URL_PATH));
        parse_str(parse_url($login->headers->get('Location'), PHP_URL_QUERY), $carried);
        $this->assertEquals($period, $carried);
        $this->get($continue)->assertRedirect($detail);
        $this->get($detail)->assertOk()->assertSee('Stok untuk periode pilihan tidak mencukupi.')
            ->assertSee('name="quantity" value="2"', false)->assertSee('Rp 150.000');
        $this->withSession(['_old_input' => ['quantity' => 1]])->get($detail)->assertOk()
            ->assertSee('name="quantity" value="1"', false)->assertSee('Tersedia 1 unit pada periode ini.');
    }

    public function test_guest_sees_registration_and_supporting_photos_instead_of_owner_inventory(): void
    {
        $this->get('/sewakan-barang')
            ->assertSee('Mulai Sewakan')
            ->assertSee(asset('images/landing/hero-camera.jpg'))
            ->assertDontSee('id="barang-saya"', false);
    }

    public function test_owner_inventory_precedes_the_guide_and_does_not_include_other_users_items(): void
    {
        $user = User::factory()->create();
        Item::factory()->for($user)->create(['name' => 'Barang Milik Saya']);
        Item::factory()->create(['name' => 'Barang Milik Orang Lain']);

        $this->actingAs($user)->get('/sewakan-barang')
            ->assertSeeInOrder(['Barang yang Anda sewakan', 'Barang Milik Saya', 'Cara menyewakan barang'])
            ->assertDontSee('Barang Milik Orang Lain');
    }

    public function test_owner_without_items_sees_add_item_action_before_the_guide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sewakan-barang')
            ->assertSeeInOrder(['Belum ada barang yang Anda sewakan.', 'Tambah Barang', 'Cara menyewakan barang'])
            ->assertSee(route('my-items.create'));
    }

    public function test_owner_inventory_remains_paginated_with_its_existing_parameter(): void
    {
        $user = User::factory()->create();
        Item::factory()->count(7)->for($user)->create();

        $response = $this->actingAs($user)->get('/sewakan-barang');

        $this->assertSame(6, $response['ownedItems']->count());
        $response->assertSee(route('rent-out', ['barang_page' => 2]));
    }

    public function test_owner_workspace_highlights_inventory_and_exposes_sidebar_controls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/my-items')
            ->assertSee('data-owner-collapse', false)
            ->assertSee('data-owner-open', false)
            ->assertSee('aria-label="Barang Saya" title="Barang Saya"  aria-current="page"', false)
            ->assertSee('Kembali ke Marketplace');
    }

    public function test_create_item_form_exposes_photo_preview_with_no_existing_photos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/my-items/create')
            ->assertSee('data-photo-upload data-existing-count="0"', false)
            ->assertSee('data-photo-preview', false)
            ->assertSee('data-photo-error', false);
    }

    public function test_edit_form_exposes_existing_photo_count_and_displays_uncropped_photo(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        ItemPhoto::factory()->count(2)->for($item)->create();

        $response = $this->actingAs($user)->get(route('my-items.edit', $item))
            ->assertSee('data-photo-upload data-existing-count="2"', false)
            ->assertSee('item-photo-natural');
        $this->assertProductPhotosAreNotCropped($response->getContent());
    }

    public function test_public_item_gallery_displays_natural_photo_and_uncropped_thumbnails(): void
    {
        $photo = ItemPhoto::factory()->primary()->create();

        $response = $this->get(route('items.show', $photo->item))
            ->assertSee('item-photo-natural')
            ->assertSee('object-contain');
        $this->assertProductPhotosAreNotCropped($response->getContent());
    }

    public function test_owner_detail_displays_uncropped_main_photo_and_thumbnails(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        ItemPhoto::factory()->for($item)->primary()->create();

        $response = $this->actingAs($user)->get(route('my-items.show', $item))
            ->assertSee('owner-detail-photo')
            ->assertSee('object-contain');
        $this->assertProductPhotosAreNotCropped($response->getContent());
    }

    public function test_detail_gallery_uses_the_catalog_primary_photo_and_exposes_failure_placeholders(): void
    {
        $item = Item::factory()->create();
        ItemPhoto::factory()->for($item)->create(['path' => 'items/secondary.jpg', 'sort_order' => 0]);
        ItemPhoto::factory()->for($item)->primary()->create(['path' => 'items/primary.jpg', 'sort_order' => 1]);

        $catalog = $this->get(route('browse'))->assertOk();
        $detail = $this->get(route('items.show', $item))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($detail->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $main = $xpath->query('//img[@data-gallery-main]')->item(0);

        $this->assertNotNull($main);
        $catalog->assertSee($main->getAttribute('src'));
        $this->assertStringEndsWith('/storage/items/primary.jpg', $main->getAttribute('src'));
        $this->assertSame(3, $xpath->query('//*[@data-item-image]/*[@data-photo-fallback]')->length);
        $this->assertSame(2, $xpath->query('//button[@data-gallery-thumb]/img[@data-item-photo]')->length);

        if (getenv('CAPTURE_GALLERY_UI') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing/gallery-ui'));
            file_put_contents(storage_path('framework/testing/gallery-ui/detail.html'), $detail->getContent());
        }
    }

    private function assertProductPhotosAreNotCropped(string $html): void
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $images = (new \DOMXPath($document))->query('//img[@data-item-photo or @data-gallery-main or contains(@class, "item-photo-natural")]');
        $this->assertGreaterThan(0, $images->length);
        foreach ($images as $image) {
            $this->assertStringNotContainsString('object-cover', $image->getAttribute('class'));
        }
    }
}
