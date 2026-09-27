@extends('layouts.app')

@section('title', $item->name.' untuk Disewa | Pinjemin')
@section('meta_description', $item->name.' tersedia untuk disewa di '.$item->city.' melalui Pinjemin.')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="['Jelajahi' => route('browse'), $item->name => null]" />

        <div class="mt-6 grid gap-10 lg:grid-cols-[minmax(0,1fr)_390px]">
            <div class="grid min-w-0 gap-8">
                <div data-gallery>
                    @php($galleryPhotos = $item->photos->reject(fn ($photo) => $photo->path === 'items/demo/pinjemin-placeholder.png'))
                    @php($mainPhoto = $galleryPhotos->first())
                    @if ($mainPhoto)
                        <img data-gallery-main src="{{ $mainPhoto->url() }}" alt="{{ $item->name }}" class="item-photo-natural opacity-100 transition duration-200">
                    @else
                        <x-item-image :item="$item" class="h-80 sm:h-[500px]" />
                    @endif

                    <div class="mt-4 grid grid-cols-4 gap-3">
                        @forelse ($galleryPhotos as $photo)
                            <button type="button" data-gallery-thumb data-gallery-src="{{ $photo->url() }}" data-gallery-alt="{{ $item->name }}" class="overflow-hidden rounded-lg border border-zinc-200 transition hover:border-blue-300">
                                <img src="{{ $photo->url() }}" alt="{{ $item->name }}" class="aspect-[4/3] w-full bg-zinc-50 object-contain">
                            </button>
                        @empty
                            <div class="col-span-4 rounded-lg border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">Foto belum tersedia.</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <span class="badge bg-blue-50 text-blue-700">{{ $item->category->name }}</span>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-zinc-950">{{ $item->name }}</h1>
                    <p class="mt-3 text-zinc-600">{{ $item->city }} / {{ $item->conditionLabel() }} / {{ $item->quantity }} unit terdaftar</p>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-zinc-600">
                        <span class="rounded-md border border-zinc-200 px-3 py-2">Ambil sendiri di {{ $item->city }}</span>
                        @if($item->delivery_enabled)
                            <span class="rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-blue-700">{{ $item->free_delivery ? 'Pengiriman gratis ongkir' : 'Pengiriman dengan tawaran ongkir' }}</span>
                        @endif
                        @if($item->allow_deposit_payment)
                            <span class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2 text-emerald-700">Pembayaran DP tersedia</span>
                        @endif
                        @if($item->allow_cash_balance)
                            <span class="rounded-md border border-zinc-200 px-3 py-2">Pelunasan tunai saat ambil</span>
                        @endif
                    </div>

                    <div class="mt-8 grid gap-7 text-sm leading-7 text-zinc-700">
                        <div>
                            <h2 class="font-semibold text-zinc-950">Deskripsi</h2>
                            <p class="mt-2 whitespace-pre-line">{{ $item->description }}</p>
                        </div>
                        @if ($item->rules)
                            <div>
                                <h2 class="font-semibold text-zinc-950">Aturan penyewaan</h2>
                                <p class="mt-2 whitespace-pre-line">{{ $item->rules }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-8 border-t border-zinc-200 pt-6">
                        <h2 class="font-semibold text-zinc-950">Pemilik</h2>
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

            <aside class="h-fit min-w-0 lg:sticky lg:top-24">
                @auth
                    @if (auth()->id() === $item->user_id)
                        <div class="card card-pad">
                            <h2 class="font-semibold text-zinc-950">Barang milikmu</h2>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">Kelola barang ini dari workspace pemilik.</p>
                            <div class="mt-5 flex flex-wrap gap-3">
                                <x-button :href="route('my-items.edit', $item)">Edit Barang</x-button>
                                <x-button :href="route('my-items.show', $item)" variant="outline">Kelola Barang</x-button>
                            </div>
                        </div>
                    @else
                        <div class="card card-pad">
                            <x-price :amount="$item->daily_price" class="text-2xl font-semibold text-blue-700" />

                            <form
                                method="POST"
                                action="{{ route('items.bookings.store', $item) }}"
                                class="mt-6 grid gap-4"
                                data-booking-form
                                data-daily-price="{{ (float) $item->daily_price }}"
                                data-deposit="{{ (float) ($item->deposit_amount ?? 0) }}"
                                data-availability-url="{{ route('items.availability', $item) }}"
                                data-calendar-url="{{ route('items.calendar', $item) }}"
                                data-today="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}"
                            >
                                @csrf
                                <div data-booking-calendar hidden>
                                    <label for="rental-range" class="field-label">Periode sewa</label>
                                    <div class="mb-3 flex items-center gap-2">
                                        <input id="rental-range" type="text" class="input min-w-0 flex-1" data-booking-range readonly>
                                        <button type="button" class="inline-flex h-11 w-10 shrink-0 items-center justify-center rounded-md border border-zinc-200 text-zinc-500 hover:bg-zinc-50" data-booking-clear aria-label="Hapus periode sewa" title="Hapus periode sewa"><i data-lucide="x" class="h-4 w-4"></i></button>
                                    </div>
                                    <div data-calendar-surface></div>
                                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-zinc-500"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-red-200 bg-red-50" aria-hidden="true"></span>Stok tidak cukup</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-blue-700" aria-hidden="true"></span>Pilihanmu</span></div>
                                    <p class="field-error" role="alert" data-calendar-error></p>
                                </div>
                                <label class="block" data-booking-native-date>
                                    <span class="field-label">Tanggal mulai</span>
                                    <input type="date" name="start_date" value="{{ old('start_date') }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input" required>
                                    @error('start_date') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="block" data-booking-native-date>
                                    <span class="field-label">Tanggal pengembalian</span>
                                    <input type="date" name="end_date" value="{{ old('end_date') }}" min="{{ now(\App\Models\Booking::RentalTimezone)->toDateString() }}" class="input" required>
                                    @error('end_date') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <fieldset class="grid gap-3 border-t border-zinc-100 pt-4">
                                    <legend class="field-label pt-4">Cara menerima barang</legend>
                                    <label class="flex items-center gap-2 text-sm text-zinc-700"><input type="radio" name="fulfillment_method" value="pickup" @checked(old('fulfillment_method', 'pickup') === 'pickup') class="h-4 w-4 accent-blue-700">Ambil Sendiri</label>
                                    @if($item->delivery_enabled)
                                        <label class="flex items-center gap-2 text-sm text-zinc-700"><input type="radio" name="fulfillment_method" value="delivery" @checked(old('fulfillment_method') === 'delivery') class="h-4 w-4 accent-blue-700">Dikirim ke Alamat Saya</label>
                                        <p class="text-xs leading-5 text-zinc-500">{{ $item->free_delivery ? 'Gratis ongkir untuk pengiriman ke penyewa.' : 'Ongkir akan ditawarkan pemilik dan perlu persetujuanmu.' }} Pengembalian menjadi tanggung jawab penyewa.</p>
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
                                    <span class="field-label">Jumlah</span>
                                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ $item->quantity }}" inputmode="numeric" class="input">
                                    @error('quantity') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="block">
                                    <span class="field-label">Catatan untuk pemilik</span>
                                    <textarea name="renter_note" rows="3" class="input">{{ old('renter_note') }}</textarea>
                                    @error('renter_note') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                @error('item') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                @error('booking') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                @error('start_date') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                @error('end_date') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                                    <dl class="grid gap-3 text-sm">
                                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Durasi</dt><dd class="font-semibold text-zinc-950" data-rental-days>0 hari</dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-zinc-500" data-price-formula>Rp 0 x 0 hari x 1 unit</dt><dd class="font-semibold text-zinc-950" data-subtotal>Rp 0</dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-zinc-500">Uang muka (DP)</dt><dd class="font-semibold text-zinc-950" data-deposit-total>Rp 0</dd></div>
                                        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-base"><dt class="font-semibold text-zinc-950">Total Estimasi</dt><dd class="text-xl font-semibold text-blue-700" data-total>Rp 0</dd></div>
                                    </dl>
                                    <p class="mt-3 text-sm text-zinc-600" data-availability-message>Pilih tanggal untuk melihat ketersediaan.</p>
                                </div>
                                <x-button type="submit" class="w-full">Ajukan Sewa</x-button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="card card-pad">
                        <x-price :amount="$item->daily_price" class="text-2xl font-semibold text-blue-700" />
                        <p class="mt-3 text-sm leading-6 text-zinc-600">Masuk untuk memilih tanggal dan mengirim permintaan sewa ke pemilik.</p>
                        <x-button :href="route('login')" class="mt-5 w-full">Sewa Barang</x-button>
                    </div>
                @endauth
            </aside>
        </div>
    </section>
@endsection
