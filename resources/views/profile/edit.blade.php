@extends('layouts.app')

@section('title', 'Profil - Pinjemin')

@section('content')
    <section class="page-shell max-w-4xl">
        <x-breadcrumb :items="['Ringkasan Akun' => route('dashboard'), 'Profil' => null]" />
        <div class="card card-pad">
            <form id="profile-form" data-form-id="profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid gap-5" data-profile-photo-upload>
                @csrf
                @method('PUT')
                <x-validation-summary bag="default" form-id="profile-form" :field-ids="['name' => 'name', 'phone' => 'phone', 'city' => 'city', 'address' => 'address', 'profile_photo' => 'profile_photo', 'remove_profile_photo' => 'remove_profile_photo']" />
                <div class="flex min-w-0 flex-col gap-5 border-b border-zinc-100 pb-6 sm:flex-row sm:items-start">
                    <x-user-avatar :user="$user" size="h-20 w-20" class="text-2xl" data-profile-avatar />
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-bold text-zinc-950">{{ $user->name }}</h1>
                        <p class="mt-1 break-all text-sm text-zinc-600">{{ $user->email }}</p>
                        <label for="profile_photo" class="field-label mt-5">Foto profil</label>
                        <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full min-w-0 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-blue-100 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100" aria-describedby="profile-photo-help profile-photo-error" aria-invalid="{{ $errors->has('profile_photo') ? 'true' : 'false' }}" data-profile-photo-input>
                        <p id="profile-photo-help" class="field-help">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                        <p id="profile-photo-error" class="field-error" role="alert" data-error-field="profile_photo" data-profile-photo-error>@error('profile_photo'){{ $message }}@enderror</p>
                        <p class="field-help" aria-live="polite" data-profile-photo-status></p>
                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <button type="button" class="text-sm font-semibold text-blue-700 hover:underline" data-profile-photo-reset hidden>Batal pilih foto</button>
                            @if($user->profile_photo)
                                <label class="flex items-center gap-2 text-sm text-zinc-600">
                                    <input id="remove_profile_photo" type="checkbox" name="remove_profile_photo" value="1" @checked(old('remove_profile_photo')) class="h-4 w-4 accent-blue-700" aria-invalid="{{ $errors->has('remove_profile_photo') ? 'true' : 'false' }}" @error('remove_profile_photo') aria-describedby="remove-profile-photo-error" @enderror data-profile-photo-remove>
                                    Hapus foto saat disimpan
                                </label>
                                @error('remove_profile_photo')<p id="remove-profile-photo-error" data-error-field="remove_profile_photo" class="field-error">{{ $message }}</p>@enderror
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    <label for="name" class="field-label">Nama</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="input" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
                    @error('name')<p id="name-error" data-error-field="name" class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" type="email" autocomplete="email" value="{{ $user->email }}" disabled class="input bg-zinc-100 text-zinc-500" aria-describedby="email-help">
                    <p id="email-help" class="field-help">Email menjadi identitas akun dan tidak dapat diubah dari halaman ini.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="field-label">Telepon</label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone', $user->phone) }}" class="input" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @error('phone') aria-describedby="phone-error" @enderror>
                        @error('phone')<p id="phone-error" data-error-field="phone" class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="city" class="field-label">Kota</label>
                        <input id="city" name="city" autocomplete="address-level2" maxlength="255" value="{{ old('city', $user->city) }}" class="input" aria-invalid="{{ $errors->has('city') ? 'true' : 'false' }}" @error('city') aria-describedby="city-error" @enderror>
                        @error('city')<p id="city-error" data-error-field="city" class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="address" class="field-label">Alamat</label>
                    <textarea id="address" name="address" rows="4" maxlength="1000" autocomplete="street-address" class="input" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" @error('address') aria-describedby="address-error" @enderror>{{ old('address', $user->address) }}</textarea>
                    @error('address')<p id="address-error" data-error-field="address" class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-button type="submit">Simpan Profil</x-button>
                    <x-button :href="route('payment-methods.index')" variant="outline" class="ml-2">Metode Pembayaran</x-button>
                    <x-button :href="route('identity.index')" variant="outline" class="ml-2">Verifikasi Identitas</x-button>
                </div>
            </form>
            <div class="mt-6 border-t border-zinc-100 pt-4 text-xs leading-6 text-zinc-500">
                <x-legal-links />
                @if($user->terms_accepted_at)
                    <p class="mt-2">Persetujuan akun: {{ $user->terms_version }}, {{ $user->terms_accepted_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
