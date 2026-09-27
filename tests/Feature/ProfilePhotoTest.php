<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_upload_a_profile_photo_and_see_it_in_shared_navigation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'profile_photo' => UploadedFile::fake()->image('portrait.jpg', 300, 600),
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertStringStartsWith('profile-photos/'.$user->id.'/', $user->profile_photo);
        Storage::disk('public')->assertExists($user->profile_photo);
        $this->get(route('profile.edit'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee($user->profilePhotoUrl())
            ->assertSee('data-profile-photo-input', false);
        $this->get(route('owner.dashboard'))->assertOk()->assertSee($user->profilePhotoUrl());
    }

    public function test_replacing_a_photo_removes_the_previous_managed_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = UploadedFile::fake()->image('old.png')->store('profile-photos/'.$user->id, 'public');
        $user->update(['profile_photo' => $oldPath]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'profile_photo' => UploadedFile::fake()->image('new.png'),
            'remove_profile_photo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($oldPath, $user->fresh()->profile_photo);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->profile_photo);
    }

    public function test_updating_other_fields_preserves_photo_and_protected_account_fields(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['profile_photo' => 'profile-photos/existing.jpg']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated', 'city' => 'Medan', 'role' => User::RoleAdmin,
            'email' => 'other@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('profile-photos/existing.jpg', $user->fresh()->profile_photo);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame(User::RoleUser, $user->fresh()->role);
        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_user_can_remove_photo_and_return_to_initials(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = UploadedFile::fake()->image('old.jpg')->store('profile-photos/'.$user->id, 'public');
        $user->update(['profile_photo' => $path]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'remove_profile_photo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->profile_photo);
        Storage::disk('public')->assertMissing($path);
        $this->get(route('profile.edit'))->assertOk()->assertDontSee($path)->assertSee('data-avatar-initial', false);
    }

    public function test_invalid_files_and_arbitrary_paths_are_rejected_without_replacing_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['profile_photo' => 'profile-photos/existing.jpg']);
        $invalidPhotos = [
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('large.jpg')->size(5121),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'another-users/photo.jpg',
        ];

        foreach ($invalidPhotos as $photo) {
            $this->actingAs($user)->put(route('profile.update'), [
                'name' => $user->name, 'profile_photo' => $photo, 'remove_profile_photo' => '1',
            ])->assertSessionHasErrors('profile_photo');
            $this->assertSame('profile-photos/existing.jpg', $user->fresh()->profile_photo);
        }

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }

    public function test_guest_cannot_upload_profile_photo(): void
    {
        Storage::fake('public');

        $this->put(route('profile.update'), [
            'name' => 'Guest', 'profile_photo' => UploadedFile::fake()->image('guest.jpg'),
        ])->assertRedirect(route('login'));

        Storage::disk('public')->assertDirectoryEmpty('profile-photos');
    }
}
