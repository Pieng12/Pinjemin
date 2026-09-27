<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => request()->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'phone', 'city', 'address']);
        $oldPhoto = $user->profile_photo;
        $newPhoto = null;
        $photoDirectory = 'profile-photos/'.$user->id;

        if ($request->hasFile('profile_photo')) {
            $newPhoto = $request->file('profile_photo')->store($photoDirectory, 'public');

            if ($newPhoto === false) {
                throw ValidationException::withMessages(['profile_photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
            }

            $data['profile_photo'] = $newPhoto;
        } elseif ($request->boolean('remove_profile_photo')) {
            $data['profile_photo'] = null;
        }

        try {
            $user->update($data);
        } catch (Throwable $exception) {
            if ($newPhoto !== null) {
                Storage::disk('public')->delete($newPhoto);
            }

            throw $exception;
        }

        if (array_key_exists('profile_photo', $data) && $oldPhoto && str_starts_with($oldPhoto, $photoDirectory.'/')) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return back()->with('status', 'Profil berhasil diperbarui.');
    }
}
