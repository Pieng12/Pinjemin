<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_panel_is_closed_without_filters(): void
    {
        $this->get('/jelajahi')
            ->assertSee('<details class="catalog-filter-details" >', false)
            ->assertSee('Temukan barang untuk rencanamu');
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
            ->assertSeeInOrder(['Barang yang kamu sewakan', 'Barang Milik Saya', 'Cara menyewakan barang'])
            ->assertDontSee('Barang Milik Orang Lain');
    }

    public function test_owner_without_items_sees_add_item_action_before_the_guide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/sewakan-barang')
            ->assertSeeInOrder(['Belum ada barang yang kamu sewakan.', 'Tambah Barang', 'Cara menyewakan barang'])
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

        $this->actingAs($user)->get(route('my-items.edit', $item))
            ->assertSee('data-photo-upload data-existing-count="2"', false)
            ->assertSee('item-photo-natural')
            ->assertDontSee('object-cover');
    }

    public function test_public_item_gallery_displays_natural_photo_and_uncropped_thumbnails(): void
    {
        $photo = ItemPhoto::factory()->primary()->create();

        $this->get(route('items.show', $photo->item))
            ->assertSee('item-photo-natural')
            ->assertSee('object-contain')
            ->assertDontSee('object-cover');
    }

    public function test_owner_detail_displays_uncropped_main_photo_and_thumbnails(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        ItemPhoto::factory()->for($item)->primary()->create();

        $this->actingAs($user)->get(route('my-items.show', $item))
            ->assertSee('owner-detail-photo')
            ->assertSee('object-contain')
            ->assertDontSee('object-cover');
    }
}
