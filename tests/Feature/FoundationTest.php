<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_can_be_opened(): void
    {
        Category::factory()->create(['name' => 'Kamera & Fotografi', 'slug' => 'kamera-fotografi']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pinjam seperlunya. Bagikan manfaatnya.')
            ->assertSee('Cara Pinjemin akan bekerja');
    }

    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'name' => 'Dina Pinjemin',
            'email' => 'dina@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::RoleAdmin,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'dina@example.test',
            'role' => User::RoleUser,
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'login@example.test',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_open_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Admin Dashboard');
    }

    public function test_user_cannot_update_their_own_role_from_profile(): void
    {
        $user = User::factory()->create(['role' => User::RoleUser]);

        $this->actingAs($user)
            ->put('/profile', [
                'name' => 'Updated User',
                'phone' => '08123456789',
                'city' => 'Bandung',
                'address' => 'Jalan Pinjemin',
                'role' => User::RoleAdmin,
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Updated User', $user->name);
        $this->assertSame(User::RoleUser, $user->role);
    }

    public function test_categories_can_be_loaded(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Kategori');
    }
}
