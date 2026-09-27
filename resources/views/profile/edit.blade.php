@extends('layouts.app')

@section('title', 'Profil - Pinjemin')

@section('content')
    <section class="page-shell max-w-4xl">
        <x-breadcrumb :items="['Ringkasan Akun' => route('dashboard'), 'Profil' => null]" />
        <div class="card card-pad">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid gap-5" data-profile-photo-upload>
                @csrf
                @method('PUT')
                <div class="flex min-w-0 flex-col gap-5 border-b border-zinc-100 pb-6 sm:flex-row sm:items-start">
                    <x-user-avatar :user="$user" size="h-20 w-20" class="text-2xl" data-profile-avatar />
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-bold text-zinc-950">{{ $user->name }}</h1>
                        <p class="mt-1 break-all text-sm text-zinc-600">{{ $user->email }}</p>
                        <label for="profile_photo" class="field-label mt-5">Foto profil</label>
                        <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full min-w-0 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-blue-100 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100" aria-describedby="profile-photo-help profile-photo-error" data-profile-photo-input>
                        <p id="profile-photo-help" class="field-help">JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                        <p id="profile-photo-error" class="field-error" role="alert" data-profile-photo-error>@error('profile_photo'){{ $message }}@enderror</p>
                        <p class="field-help" aria-live="polite" data-profile-photo-status></p>
                        <div class="mt-3 flex flex-wrap items-center gap-4">
                            <button type="button" class="text-sm font-semibold text-blue-700 hover:underline" data-profile-photo-reset hidden>Batal pilih foto</button>
                            @if($user->profile_photo)
                                <label class="flex items-center gap-2 text-sm text-zinc-600">
                                    <input type="checkbox" name="remove_profile_photo" value="1" @checked(old('remove_profile_photo')) class="h-4 w-4 accent-blue-700" data-profile-photo-remove>
                                    Hapus foto saat disimpan
                                </label>
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    <label for="name" class="field-label">Nama</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="field-label">Email</label>
                    <input id="email" value="{{ $user->email }}" disabled class="input bg-zinc-100 text-zinc-500">
                    <p class="field-help">Email menjadi identitas akun dan tidak dapat diubah dari halaman ini.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="field-label">Telepon</label>
                        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input">
                        @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="city" class="field-label">Kota</label>
                        <input id="city" name="city" value="{{ old('city', $user->city) }}" class="input">
                        @error('city')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label for="address" class="field-label">Alamat</label>
                    <textarea id="address" name="address" rows="4" class="input">{{ old('address', $user->address) }}</textarea>
                    @error('address')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-button type="submit">Simpan Profil</x-button>
                    <x-button :href="route('payment-methods.index')" variant="outline" class="ml-2">Metode Pembayaran</x-button>
                </div>
            </form>
        </div>
    </section>
@endsection
