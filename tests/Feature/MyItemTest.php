<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_create_item_page(): void
    {
        $this->get('/my-items/create')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_item_with_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post('/my-items', $this->validPayload($category))
            ->assertRedirect();

        $item = Item::query()->where('name', 'Canon EOS R50')->firstOrFail();

        $this->assertSame($user->id, $item->user_id);
        $this->assertSame($category->id, $item->category_id);
        $this->assertDatabaseHas('item_photos', [
            'item_id' => $item->id,
            'is_primary' => true,
        ]);

        Storage::disk('public')->assertExists($item->photos()->firstOrFail()->path);
    }

    public function test_user_cannot_create_item_for_another_user(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);

        $payload = $this->validPayload($category);
        $payload['user_id'] = $otherUser->id;

        $this->actingAs($user)
            ->post('/my-items', $payload)
            ->assertRedirect();

        $item = Item::query()->where('name', 'Canon EOS R50')->firstOrFail();

        $this->assertSame($user->id, $item->user_id);
    }

    public function test_user_can_edit_their_own_item(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $item = Item::factory()->for($user)->for($category)->create(['name' => 'Nama Lama']);
        ItemPhoto::factory()->for($item)->primary()->create();

        $payload = $this->validPayload($category);
        $payload['name'] = 'Nama Baru';
        unset($payload['photos']);

        $response = $this->actingAs($user)
            ->put(route('my-items.update', $item), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru', $item->refresh()->name);
        $response->assertRedirect(route('my-items.show', $item));
    }

    public function test_user_cannot_edit_another_users_item(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $item = Item::factory()->for($otherUser)->for($category)->create();
        ItemPhoto::factory()->for($item)->primary()->create();

        $payload = $this->validPayload($category);
        unset($payload['photos']);

        $this->actingAs($user)
            ->put(route('my-items.update', $item), $payload)
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_item(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for(User::factory())->create();

        $this->actingAs($user)
            ->delete(route('my-items.destroy', $item))
            ->assertForbidden();
    }

    public function test_upload_image_is_validated(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $payload = $this->validPayload($category);
        $payload['photos'] = [UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')];

        $this->actingAs($user)
            ->from(route('my-items.create'))
            ->post('/my-items', $payload)
            ->assertRedirect(route('my-items.create'))
            ->assertSessionHasErrors('photos.0');
    }

    /** @return array<string, mixed> */
    private function validPayload(Category $category): array
    {
        return [
            'name' => 'Canon EOS R50',
            'category_id' => $category->id,
            'description' => 'Kamera mirrorless ringkas untuk kebutuhan foto dan video harian.',
            'condition' => 'like_new',
            'daily_price' => 125000,
            'deposit_amount' => 250000,
            'quantity' => 1,
            'city' => 'Medan',
            'address' => 'Jalan Pinjemin Nomor 1',
            'rules' => 'Kembalikan dalam kondisi bersih.',
            'is_active' => '1',
            'photos' => [UploadedFile::fake()->image('kamera.jpg')->size(512)],
        ];
    }
}
