@props(['user', 'size' => 'h-8 w-8'])
@php($photoUrl = $user->profilePhotoUrl())

<span {{ $attributes->class(['relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-50 font-bold text-blue-700', $size]) }} data-user-avatar>
    <span data-avatar-initial @if($photoUrl) hidden @endif>{{ mb_substr($user->name, 0, 1) }}</span>
    <img data-avatar-photo @if($photoUrl) src="{{ $photoUrl }}" @endif alt="Foto profil {{ $user->name }}" class="h-full w-full object-contain" @if(!$photoUrl) hidden @endif decoding="async">
</span>
