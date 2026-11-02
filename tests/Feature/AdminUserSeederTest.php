<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_configured_admin_can_log_in_without_creating_demo_users_or_items(): void
    {
        config(['admin.email' => ' Admin@Example.Test ', 'admin.password' => 'Example-admin-123!', 'admin.reset_password' => false]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.test')->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('Example-admin-123!', $admin->password));
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Item::query()->count());
        $this->post('/login', ['email' => $admin->email, 'password' => 'Example-admin-123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();
    }

    public function test_rerunning_admin_seeder_preserves_existing_password_and_profile(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test', 'name' => 'Admin Existing', 'password' => 'Existing-admin-123!']);
        $oldPassword = $admin->password;
        config(['admin.email' => $admin->email, 'admin.password' => 'Different-admin-123!', 'admin.reset_password' => false]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame($oldPassword, $admin->fresh()->password);
        $this->assertSame('Admin Existing', $admin->fresh()->name);
        $this->assertSame(1, User::query()->count());
    }

    public function test_explicit_reset_changes_only_admin_password_and_remember_token(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test', 'name' => 'Admin Existing']);
        config(['admin.email' => $admin->email, 'admin.password' => 'Reset-admin-123!', 'admin.reset_password' => true]);

        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('Reset-admin-123!', $admin->fresh()->password));
        $this->assertNull($admin->fresh()->remember_token);
        $this->assertSame('Admin Existing', $admin->fresh()->name);
        $this->post('/login', ['email' => $admin->email, 'password' => 'Reset-admin-123!'])->assertRedirect(route('dashboard'));
    }

    public function test_missing_password_skips_account_creation(): void
    {
        config(['admin.password' => null]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_admin_seeder_does_not_promote_or_reset_a_regular_account(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);
        $oldPassword = $user->password;
        config(['admin.email' => $user->email, 'admin.password' => 'Example-admin-123!', 'admin.reset_password' => true]);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('Seeder must reject an email belonging to a regular account.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ADMIN_EMAIL sudah dipakai akun non-admin. Gunakan email lain untuk akun admin.', $exception->getMessage());
        }

        $this->assertSame(User::RoleUser, $user->fresh()->role);
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame(1, User::query()->count());
    }

    public function test_invalid_credentials_do_not_create_an_admin(): void
    {
        config(['admin.email' => 'invalid-email', 'admin.password' => 'short']);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('Seeder must validate administrator credentials.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertSame(0, User::query()->count());
    }
}
