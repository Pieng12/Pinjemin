@extends('layouts.app')

@section('title', $item->name.' untuk Disewa | Pinjemin')
@section('meta_description', $item->name.' tersedia untuk disewa di '.$item->city.' melalui Pinjemin.')

@section('content')
    @php
        $isOwner = auth()->id() === $item->user_id;
        $canContactOwner = config('features.chat_enabled') && ! $isOwner && $item->is_active && $item->status === \App\ItemStatus::Available;
        $galleryPhotos = $item->photos->reject(fn ($photo) => $photo->path === 'items/demo/pinjemin-placeholder.png');
        $showDeposit = $item->allow_deposit_payment && ($estimate['deposit_total'] ?? 0) > 0 && $estimate['deposit_total'] < $estimate['subtotal'];
        $mainPhoto = $galleryPhotos->firstWhere('is_primary', true) ?? $galleryPhotos->first();
    @endphp
    <section class="page-shell">
        <x-breadcrumb :items="['Jelajahi' => route('browse'), $item->name => null]" />
        <header class="mt-4 border-b border-brand-100 pb-4 sm:mt-5 sm:pb-5">
            <span class="badge badge-compact bg-brand-50 text-brand-700">{{ $item->category->name }}</span>
            <h1 class="mt-2 wrap-anywhere text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl">{{ $item->name }}</h1>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600">
                <li class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="size-4 shrink-0 text-brand-700" aria-hidden="true"></i>{{ $item->city }}</li>
                <li>{{ $item->conditionLabel() }}</li>
                <li>{{ $item->quantity }} unit terdaftar</li>
            </ul>
            <div class="mt-4 lg:hidden">
                <x-price :amount="$item->daily_price" class="block text-xl font-semibold text-brand-700" />
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <x-button href="#rental-options">{{ $isOwner ? 'Kelola Barang' : (auth()->check() ? 'Pilih Tanggal Sewa' : 'Opsi Sewa') }}</x-button>
                    @if($canContactOwner)
                        <x-button :href="route('chat.items.create', $item)" variant="outline"><i data-lucide="message-circle" class="size-4" aria-hidden="true"></i> Hubungi Pemilik</x-button>
                    @endif
                </div>
            </div>
        </header>

        <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-x-6 xl:grid-cols-[minmax(0,1fr)_408px]">
            <div class="grid min-w-0 gap-5">
                <div data-gallery class="card min-w-0 p-3 sm:p-4">
                    @if ($mainPhoto)
                        <div class="relative flex h-[clamp(215px,55vw,420px)] items-center justify-center overflow-hidden rounded-lg bg-zinc-50 lg:h-[clamp(315px,40vh,440px)]" data-item-image>
                            <img data-gallery-main data-item-photo src="{{ $mainPhoto->url() }}" alt="{{ $item->name }}" class="item-photo-natural item-detail-photo">
                            <span data-photo-fallback hidden role="status" class="px-5 py-10 text-center text-sm text-zinc-500">Foto belum tersedia</span>
                        </div>
                    @else
                        <x-item-image :item="$item" class="h-[clamp(215px,55vw,420px)] lg:h-[clamp(315px,40vh,440px)]" />
                    @endif

                    @if($galleryPhotos->count() > 1)
                        <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Foto barang">
                            @foreach ($galleryPhotos as $photo)
                                <button type="button" data-item-image data-gallery-thumb data-gallery-src="{{ $photo->url() }}" data-gallery-alt="{{ $item->name }} (foto {{ $loop->iteration }})" aria-label="Tampilkan foto {{ $loop->iteration }} {{ $item->name }}" aria-pressed="{{ $photo->is($mainPhoto) ? 'true' : 'false' }}" class="relative flex aspect-[4/3] w-16 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 transition-colors hover:border-brand-500 aria-pressed:border-brand-700 aria-pressed:ring-1 aria-pressed:ring-brand-700 sm:w-20">
                                    <img data-item-photo src="{{ $photo->url() }}" alt="{{ $item->name }}" class="aspect-[4/3] w-full object-contain">
                                    <span data-photo-fallback hidden class="px-2 text-center text-xs leading-4 text-zinc-500">Foto belum tersedia</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card card-pad min-w-0">
                    <h2 class="text-lg font-semibold text-zinc-950">Informasi barang</h2>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium text-zinc-600">
                        <span class="rounded-md border border-zinc-200 px-3 py-2">Ambil sendiri di {{ $item->city }}</span>
                        @if($item->delivery_enabled)
                            <span class="rounded-md border border-brand-100 bg-brand-50 px-3 py-2 text-brand-700">{{ $item->free_delivery ? 'Pengiriman gratis ke penyewa' : 'Biaya pengiriman sesuai penawaran pemilik' }}</span>
                        @endif
                        @if($item->allow_deposit_payment)
                            <span class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2 text-emerald-700">Pembayaran DP tersedia</span>
                        @endif
                        @if($item->allow_cash_balance)
                            <span class="rounded-md border border-zinc-200 px-3 py-2">Pelunasan tunai saat ambil</span>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-4 text-sm leading-7 text-zinc-700">
                        <div>
                            <h3 class="font-semibold text-zinc-950">Deskripsi</h3>
                            <p class="mt-2 wrap-anywhere whitespace-pre-line">{{ $item->description }}</p>
                        </div>
                        <div id="item-requirements" tabindex="-1" class="scroll-mt-28 border-t border-zinc-100 pt-4">
                            <h3 class="font-semibold text-zinc-950">Persyaratan penyewa</h3>
                            <ul class="mt-1.5 list-disc space-y-1 pl-4">
                                <li>{{ $item->requires_condition_photos ? 'Pemilik dan penyewa wajib mengunggah foto kondisi saat penyerahan dan pengembalian.' : 'Pemilik dan penyewa dapat mengunggah foto kondisi secara sukarela.' }}</li>
                                <li>{{ $item->requires_identity_verification ? 'Sebelum pemesanan diterima, administrator harus menyetujui verifikasi KTP atau SIM penyewa. Pemilik hanya melihat status.' : 'Verifikasi identitas bersifat opsional untuk barang ini.' }}</li>
                                <li>Kedua pihak memeriksa dan mengonfirmasi kondisi barang saat penyerahan dan pengembalian.</li>
                            </ul>
                        </div>
                        @if ($item->rules)
                            <div>
                                <h3 class="font-semibold text-zinc-950">Aturan penyewaan</h3>
                                <p class="mt-2 wrap-anywhere whitespace-pre-line">{{ $item->rules }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-5 border-t border-zinc-100 pt-4">
                        <h3 class="font-semibold text-zinc-950">Pemilik</h3>
                        <div class="mt-4 flex items-center gap-3">
                            <x-user-avatar :user="$item->user" size="h-12 w-12" />
                            <div>
                                <p class="font-semibold text-zinc-950">{{ $item->user->name }}</p>
                                <p class="text-sm text-zinc-500">{{ $item->user->city ?? $item->city }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <aside id="rental-options" tabindex="-1" class="min-w-0 scroll-mt-28">
                @auth
                    @if ($isOwner)
                        <div class="card card-pad">
                            <h2 class="font-semibold text-zinc-950">Barang milik Anda</h2>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">Kelola barang ini dari ruang kerja pemilik.</p>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <x-button :href="route('my-items.edit', $item)">Edit Barang</x-button>
                                <x-button :href="route('my-items.show', $item)" variant="outline">Kelola Barang</x-button>
                            </div>
                        </div>
                    @else
                        <div class="card min-w-0 overflow-hidden">
                            <div class="border-b border-brand-100 bg-brand-50/60 p-4 sm:px-5">
                                <h2 class="text-sm font-semibold text-zinc-700">Ajukan Sewa</h2>
                                <x-price :amount="$item->daily_price" class="mt-1 block text-2xl font-semibold text-brand-700" />
                                @if($canContactOwner)
                                    <div class="mt-3 hidden lg:block">
                                        <x-button :href="route('chat.items.create', $item)" variant="outline" class="w-full"><i data-lucide="message-circle" class="size-4" aria-hidden="true"></i> Hubungi Pemilik</x-button>
                                    </div>
                                @endif
                                @if($item->requires_condition_photos || $item->requires_identity_verification)
                                    <a href="#item-requirements" class="mt-2 inline-block text-xs font-semibold text-brand-700 underline underline-offset-2">
                                        {{ $item->requires_condition_photos && $item->requires_identity_verification ? 'Foto kondisi dan verifikasi identitas wajib' : ($item->requires_condition_photos ? 'Foto kondisi wajib' : 'Verifikasi identitas wajib') }}
                                    </a>
                                @endif
                            </div>

                            <form
                                method="POST"
                                action="{{ route('items.bookings.store', $item) }}"
                                class="grid gap-4 p-4 sm:p-5"
                                data-booking-form
                                data-daily-price="{{ (float) $item->daily_price }}"
                                data-deposit="{{ $item->allow_deposit_payment ? (float) $item->deposit_amount : 0 }}"
                                data-free-delivery="{{ $item->free_delivery ? 'true' : 'false' }}"
                                data-availability-url="{{ route('items.availability', $item) }}"
                                data-calendar-url="{{ route('items.calendar', $item) }}"
                                data-today="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}"
                            >
                                @csrf
                                <div data-booking-calendar hidden>
                                    <label for="rental-range" class="field-label">Periode sewa</label>
                                    <div class="mb-3 flex items-center gap-2">
                                        <input id="rental-range" type="text" class="input mt-0 min-w-0 flex-1" placeholder="Pilih tanggal mulai dan akhir" data-booking-range readonly>
                                        <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-zinc-200 text-zinc-500 hover:bg-zinc-50" data-booking-clear aria-label="Hapus periode sewa" title="Hapus periode sewa"><i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i></button>
                                    </div>
                                    <div data-calendar-surface></div>
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-600"><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm border border-red-300 bg-red-50" aria-hidden="true"></span>Stok tidak cukup</span><span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-700" aria-hidden="true"></span>Tanggal dipilih</span></div>
                                    <p class="field-error" role="alert" data-calendar-error></p>
                                    @error('start_date') <p class="field-error">{{ $message }}</p> @enderror
                                    @error('end_date') <p class="field-error">{{ $message }}</p> @enderror
                                </div>
                                <label class="block" data-booking-native-date>
                                    <span class="field-label">Tanggal mulai</span>
                                    <input type="date" name="start_date" value="{{ $selection['start_date'] }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input" required>
                                    @error('start_date') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="block" data-booking-native-date>
                                    <span class="field-label">Tanggal pengembalian</span>
                                    <input type="date" name="end_date" value="{{ $selection['end_date'] }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input" required>
                                    @error('end_date') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <div class="flex items-start justify-between gap-4 border-b border-zinc-100 pb-4">
                                    <label for="rental-quantity" class="pt-1 text-sm text-zinc-700">
                                        <span class="font-semibold">Jumlah unit</span>
                                        <span class="mt-1 block text-xs text-zinc-500">{{ $item->quantity }} unit terdaftar</span>
                                    </label>
                                    <div class="w-20 shrink-0">
                                        <input id="rental-quantity" type="number" name="quantity" value="{{ $selection['quantity'] }}" min="1" max="{{ $item->quantity }}" inputmode="numeric" class="input mt-0" required>
                                    </div>
                                </div>
                                @error('quantity') <p class="field-error">{{ $message }}</p> @enderror
                                <fieldset class="min-w-0 space-y-2">
                                    <legend class="field-label">Cara menerima barang</legend>
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-zinc-200 px-3 text-sm text-zinc-700 has-checked:border-brand-300 has-checked:bg-brand-50"><input type="radio" name="fulfillment_method" value="pickup" @checked(old('fulfillment_method', 'pickup') === 'pickup') class="size-4 shrink-0 accent-brand-700">Ambil sendiri di {{ $item->city }}</label>
                                    @if($item->delivery_enabled)
                                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-zinc-200 px-3 text-sm text-zinc-700 has-checked:border-brand-300 has-checked:bg-brand-50"><input type="radio" name="fulfillment_method" value="delivery" @checked(old('fulfillment_method') === 'delivery') class="size-4 shrink-0 accent-brand-700">Dikirim ke alamat saya</label>
                                        <p class="text-xs leading-5 text-zinc-500">{{ $item->free_delivery ? 'Pengiriman kepada penyewa tidak dikenai biaya.' : 'Biaya pengiriman akan ditawarkan pemilik dan perlu persetujuan Anda.' }} Pengembalian menjadi tanggung jawab penyewa.</p>
                                        <div class="grid gap-3" data-recipient-fields>
                                            <label class="block"><span class="field-label">Nama penerima</span><input name="recipient_name" value="{{ old('recipient_name', auth()->user()->name) }}" class="input" maxlength="255">@error('recipient_name')<span class="field-error">{{ $message }}</span>@enderror</label>
                                            <label class="block"><span class="field-label">Telepon penerima</span><input name="recipient_phone" value="{{ old('recipient_phone', auth()->user()->phone) }}" class="input" maxlength="30">@error('recipient_phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                                            <label class="block"><span class="field-label">Kota tujuan</span><input name="recipient_city" value="{{ old('recipient_city', auth()->user()->city) }}" class="input" maxlength="255">@error('recipient_city')<span class="field-error">{{ $message }}</span>@enderror</label>
                                            <label class="block"><span class="field-label">Alamat lengkap tujuan</span><textarea name="recipient_address" rows="3" class="input" maxlength="1000">{{ old('recipient_address', auth()->user()->address) }}</textarea>@error('recipient_address')<span class="field-error">{{ $message }}</span>@enderror</label>
                                        </div>
                                    @endif
                                    @error('fulfillment_method')<p class="field-error">{{ $message }}</p>@enderror
                                </fieldset>
                                <label class="block">
                                    <span class="field-label">Catatan untuk pemilik</span>
                                    <textarea name="renter_note" rows="3" class="input">{{ old('renter_note') }}</textarea>
                                    @error('renter_note') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                @error('item') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                @error('booking') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                                    <dl class="grid gap-3 text-sm">
                                        <div class="flex justify-between gap-4"><dt class="text-zinc-600">Durasi</dt><dd class="font-semibold text-zinc-950" data-rental-days>{{ $estimate ? $estimate['rental_days'].' hari' : '—' }}</dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-zinc-600">Subtotal sewa <span class="mt-0.5 block text-xs" data-price-formula>@if($estimate)<x-price :amount="$item->daily_price" suffix="" /> × {{ $estimate['rental_days'] }} hari × {{ $selection['quantity'] }} unit @endif</span></dt><dd class="shrink-0 font-semibold text-zinc-950" data-subtotal>@if($estimate)<x-price :amount="$estimate['subtotal']" suffix="" />@else — @endif</dd></div>
                                        <div class="flex justify-between gap-4" data-shipping-row><dt class="text-zinc-600">Biaya pengiriman</dt><dd class="text-right font-medium text-zinc-950">{{ $item->free_delivery ? 'Gratis' : 'Menunggu penawaran pemilik' }}</dd></div>
                                        <div class="flex justify-between gap-4" data-deposit-row @if(!$showDeposit) hidden @endif><dt class="text-zinc-600">Uang muka (DP), bagian dari total</dt><dd class="shrink-0 font-semibold text-zinc-950" data-deposit-total>@if($showDeposit)<x-price :amount="$estimate['deposit_total']" suffix="" />@endif</dd></div>
                                        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-base" data-total-row><dt class="font-semibold text-zinc-950">Total estimasi</dt><dd class="shrink-0 font-semibold text-brand-700" data-total>@if($estimate)<x-price :amount="$estimate['total_amount']" suffix="" />@else — @endif</dd></div>
                                    </dl>
                                    <p class="mt-3 text-sm text-zinc-600" aria-live="polite" data-availability-message>@if($availableUnits === null)Pilih tanggal untuk melihat ketersediaan.@elseif($availableUnits < $selection['quantity'])Stok untuk periode pilihan tidak mencukupi. Pilih periode lain atau kurangi jumlah unit.@else Tersedia {{ $availableUnits }} unit pada periode ini.@endif</p>
                                    <noscript><p class="mt-2 text-xs text-zinc-600">Subtotal dihitung dari tarif per hari × durasi × jumlah unit. Perubahan pilihan dan ketersediaan diperiksa saat pengajuan. Jika memilih pengiriman berbayar, total ditampilkan setelah pemilik menawarkan biaya pengiriman.</p></noscript>
                                </div>
                                <div class="border-t border-zinc-100 pt-3 text-sm leading-6 text-zinc-600">
                                    <p>Pembayaran diverifikasi administrator. Barang dapat diserahkan setelah pembayaran lunas.</p>
                                    <label class="mt-2 flex min-h-11 items-start gap-3 py-2"><input type="checkbox" name="requirements_accepted" value="1" required @checked(old('requirements_accepted')) class="mt-1 size-4 shrink-0 accent-brand-700" aria-describedby="booking-terms-error"><span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-700 underline underline-offset-2">aturan penyewaan Pinjemin</a> dan <a href="#item-requirements" class="font-semibold text-brand-700 underline underline-offset-2">persyaratan barang ini</a>.</span></label>
                                    <div id="booking-terms-error">@error('requirements_accepted')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
                                </div>
                                <x-button type="submit" class="w-full">Ajukan Sewa</x-button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="card card-pad">
                        <x-price :amount="$item->daily_price" class="text-2xl font-semibold text-blue-700" />
                        @if($period !== [])
                            <p class="mt-3 text-sm font-semibold text-zinc-950">Periode pilihan · {{ $period['quantity'] }} unit</p>
                            <p class="text-sm text-zinc-600">{{ \Illuminate\Support\Carbon::parse($period['start_date'])->locale('id')->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($period['end_date'])->locale('id')->translatedFormat('d M Y') }}</p>
                            @if($availableUnits !== null && $availableUnits < $period['quantity'])<p class="mt-2 text-sm text-amber-800">Stok berubah. Pilih periode lain atau kurangi jumlah unit setelah masuk.</p>@endif
                        @endif
                        <p class="mt-3 text-sm leading-6 text-zinc-600">Masuk untuk melanjutkan pengajuan sewa kepada pemilik barang.</p>
                        <x-button :href="route('items.continue-rental', ['item' => $item, ...$period])" class="mt-5 w-full">Masuk untuk Menyewa</x-button>
                        @if($canContactOwner)
                            <div class="mt-2 hidden lg:block">
                                <x-button :href="route('chat.items.create', $item)" variant="outline" class="w-full"><i data-lucide="message-circle" class="size-4" aria-hidden="true"></i> Hubungi Pemilik</x-button>
                            </div>
                        @endif
                    </div>
                @endauth
            </aside>
        </div>
    </section>
@endsection
