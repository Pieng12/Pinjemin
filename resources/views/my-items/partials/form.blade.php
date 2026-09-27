@php($conditions = \App\ItemCondition::options())

<div class="grid gap-6">
    <section class="card card-pad">
        <h2 class="text-lg font-semibold text-zinc-950">Informasi Barang</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label for="name" class="field-label">Nama Barang <span class="text-red-600">*</span></label>
                <input id="name" name="name" value="{{ old('name', $item?->name) }}" required class="input">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="category_id" class="field-label">Kategori <span class="text-red-600">*</span></label>
                <select id="category_id" name="category_id" required class="input">
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $item?->category_id) === $category->id)>
                            {{ $category->name }}{{ $category->is_active ? '' : ' (Nonaktif)' }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="condition" class="field-label">Kondisi <span class="text-red-600">*</span></label>
                <select id="condition" name="condition" required class="input">
                    @foreach ($conditions as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $item?->condition?->value ?? 'good') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="field-label">Deskripsi <span class="text-red-600">*</span></label>
                <textarea id="description" name="description" rows="5" required class="input">{{ old('description', $item?->description) }}</textarea>
                @error('description')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="card card-pad" data-payment-policy>
        <h2 class="text-lg font-semibold text-zinc-950">Kebijakan Pembayaran</h2>
        <div class="mt-5 grid gap-3">
            <label class="flex items-start gap-3 text-sm font-medium text-zinc-800">
                <input type="hidden" name="allow_deposit_payment" value="0">
                <input type="checkbox" name="allow_deposit_payment" value="1" @checked(old('allow_deposit_payment', $item?->allow_deposit_payment)) class="mt-0.5 h-4 w-4 accent-blue-700" data-deposit-enabled>
                <span>Izinkan penyewa membayar DP lebih dahulu<span class="mt-1 block font-normal text-zinc-500">Nominal DP mengikuti nilai uang muka per unit di bawah.</span></span>
            </label>
            <label class="flex items-start gap-3 text-sm font-medium text-zinc-800">
                <input type="hidden" name="allow_cash_balance" value="0">
                <input type="checkbox" name="allow_cash_balance" value="1" @checked(old('allow_cash_balance', $item?->allow_cash_balance)) class="mt-0.5 h-4 w-4 accent-blue-700" data-cash-enabled>
                <span>Izinkan sisa pembayaran tunai saat pengambilan<span class="mt-1 block font-normal text-zinc-500">Hanya tersedia untuk pengambilan langsung setelah DP terverifikasi.</span></span>
            </label>
            @error('allow_deposit_payment')<p class="field-error">{{ $message }}</p>@enderror
            @error('allow_cash_balance')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="text-lg font-semibold text-zinc-950">Harga & Ketersediaan</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <x-currency-input name="daily_price" label="Harga Sewa per Hari" :value="$item?->daily_price" required help="Harga sewa per unit." />
            <x-currency-input name="deposit_amount" label="Uang Muka (DP) per Unit" :value="$item?->deposit_amount" help="DP menjadi bagian dari total pembayaran, bukan jaminan yang dikembalikan." />
            <div>
                <label for="quantity" class="field-label">Jumlah Unit <span class="text-red-600">*</span></label>
                <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" value="{{ old('quantity', $item?->quantity ?? 1) }}" required class="input">
                @error('quantity')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="text-lg font-semibold text-zinc-950">Lokasi Pengambilan</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div>
                <label for="city" class="field-label">Kota <span class="text-red-600">*</span></label>
                <input id="city" name="city" value="{{ old('city', $item?->city) }}" required class="input">
                @error('city')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="lg:col-span-2">
                <label for="address" class="field-label">Alamat lengkap pengambilan</label>
                <textarea id="address" name="address" rows="3" class="input">{{ old('address', $item?->address) }}</textarea>
                <p class="field-help">Wajib untuk barang aktif. Alamat lengkap hanya tersedia setelah booking disetujui final.</p>
                @error('address')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5 grid gap-3 border-t border-zinc-100 pt-5" data-delivery-settings>
            <label class="flex items-center gap-3 text-sm font-medium text-zinc-800">
                <input type="hidden" name="delivery_enabled" value="0">
                <input type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', $item?->delivery_enabled)) class="h-4 w-4 accent-blue-700" data-delivery-enabled>
                Izinkan Pengiriman
            </label>
            <label class="flex items-center gap-3 text-sm text-zinc-600">
                <input type="hidden" name="free_delivery" value="0">
                <input type="checkbox" name="free_delivery" value="1" @checked(old('free_delivery', $item?->free_delivery)) class="h-4 w-4 accent-blue-700" data-free-delivery>
                Gratis Ongkir
            </label>
            <p class="field-help">Pengambilan langsung selalu tersedia. Ongkir berbayar ditawarkan setelah alamat penyewa diterima.</p>
            @error('delivery_enabled')<p class="field-error">{{ $message }}</p>@enderror
            @error('free_delivery')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="card card-pad" data-photo-upload data-existing-count="{{ $item?->photos->count() ?? 0 }}">
        <h2 class="text-lg font-semibold text-zinc-950">{{ $item ? 'Tambahkan foto' : 'Foto barang' }}</h2>
        <div class="mt-5 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 sm:p-5">
            <label for="photos" class="field-label">Pilih foto barang</label>
            <p id="photos-help" class="mt-1 text-xs leading-6 text-zinc-500">JPG, PNG, WEBP. Total maksimum 5 foto, 5 MB per foto.</p>
            <input id="photos" name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="photos-help photos-status photos-error" class="photo-file-input mt-3" data-photo-input>
        </div>
        <p id="photos-status" class="mt-3 text-sm text-zinc-500" data-photo-status role="status" aria-live="polite"></p>
        <p id="photos-error" class="field-error" data-photo-error role="alert" hidden></p>
        <div class="photo-preview-grid mt-4" data-photo-preview></div>
        @error('photos')<p class="field-error">{{ $message }}</p>@enderror
        @error('photos.*')<p class="field-error">{{ $message }}</p>@enderror
    </section>

    <section class="card card-pad">
        <h2 class="text-lg font-semibold text-zinc-950">Aturan & Publikasi</h2>
        <div class="mt-5 grid gap-5">
            <div>
                <label for="rules" class="field-label">Aturan Penyewaan</label>
                <textarea id="rules" name="rules" rows="3" class="input">{{ old('rules', $item?->rules) }}</textarea>
                @error('rules')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm font-semibold text-zinc-800">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item?->is_active ?? true)) class="rounded border-zinc-300 text-blue-700">
                    Aktifkan barang setelah disimpan
                </label>
            </div>
        </div>
    </section>
</div>
