<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_item_list(): void
    {
        $admin = User::factory()->admin()->create();
        Item::factory()->create(['name' => 'Kamera Admin']);

        $this->actingAs($admin)
            ->get('/admin/items')
            ->assertOk()
            ->assertSee('Kamera Admin');
    }
}
