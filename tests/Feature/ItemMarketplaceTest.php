<?php

namespace Tests\Feature;

use App\ItemCondition;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_marketplace(): void
    {
        $this->get('/jelajahi')
            ->assertOk()
            ->assertSee('Jelajahi Barang');
    }

    public function test_guest_can_view_active_item_detail_by_slug(): void
    {
        Item::factory()->create([
            'name' => 'Canon EOS R50',
            'slug' => 'canon-eos-r50',
        ]);

        $this->get('/items/canon-eos-r50')
            ->assertOk()
            ->assertSee('Canon EOS R50');
    }

    public function test_active_item_appears_and_inactive_item_is_hidden_from_marketplace(): void
    {
        Item::factory()->create(['name' => 'Kamera Aktif', 'slug' => 'kamera-aktif']);
        Item::factory()->inactive()->create(['name' => 'Kamera Nonaktif', 'slug' => 'kamera-nonaktif']);

        $this->get('/jelajahi')
            ->assertOk()
            ->assertSee('Kamera Aktif')
            ->assertDontSee('Kamera Nonaktif');
    }

    public function test_search_filters_items_by_name_or_description(): void
    {
        Item::factory()->create(['name' => 'Kamera Mirrorless', 'description' => 'Cocok untuk foto produk dan video.']);
        Item::factory()->create(['name' => 'Tenda Gunung', 'description' => 'Kapasitas empat orang.']);

        $this->get('/jelajahi?q=kamera')
            ->assertOk()
            ->assertSee('Kamera Mirrorless')
            ->assertDontSee('Tenda Gunung');
    }

    public function test_category_filter_works(): void
    {
        $camera = Category::factory()->create(['name' => 'Kamera', 'slug' => 'kamera']);
        $camping = Category::factory()->create(['name' => 'Camping', 'slug' => 'camping']);
        Item::factory()->for($camera)->create(['name' => 'Lensa Kamera']);
        Item::factory()->for($camping)->create(['name' => 'Tenda Dome']);

        $this->get('/jelajahi?category=kamera')
            ->assertOk()
            ->assertSee('Lensa Kamera')
            ->assertDontSee('Tenda Dome');
    }

    public function test_price_filter_works(): void
    {
        Item::factory()->create(['name' => 'Murah', 'daily_price' => 50000]);
        Item::factory()->create(['name' => 'Mahal', 'daily_price' => 300000]);

        $this->get('/jelajahi?min_price=100000&max_price=400000')
            ->assertOk()
            ->assertSee('Mahal')
            ->assertDontSee('Murah');
    }

    public function test_condition_filter_works(): void
    {
        Item::factory()->create(['name' => 'Barang Baru', 'condition' => ItemCondition::New]);
        Item::factory()->create(['name' => 'Barang Cukup', 'condition' => ItemCondition::Fair]);

        $this->get('/jelajahi?condition=new')
            ->assertOk()
            ->assertSee('Barang Baru')
            ->assertDontSee('Barang Cukup');
    }
}
