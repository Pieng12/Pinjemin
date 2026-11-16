@php($conditions = \App\ItemCondition::options())

<div class="grid gap-6" data-item-form-fields data-form-id="item-form">
    <x-validation-summary bag="default" form-id="item-form" :field-ids="collect(['name', 'category_id', 'condition', 'description', 'daily_price', 'deposit_amount', 'quantity', 'city', 'address', 'photos', 'rules', 'is_active', 'allow_deposit_payment', 'delivery_enabled', 'free_delivery', 'requires_condition_photos', 'requires_identity_verification'])->mapWithKeys(fn ($name) => [$name => $name])->all()" />
    <p class="text-sm text-zinc-600">Tanda * berarti wajib. Persyaratan tambahan mengikuti pilihan publikasi dan pembayaran.</p>
    <aside class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-zinc-600">
        <p class="font-semibold text-blue-950">Tanggung jawab pemilik barang</p>
        <p class="mt-2">Pastikan Anda berhak menyewakan barang, jelaskan kondisi dan aksesori dengan jujur, serta tulis aturan penggunaannya. Komisi 5% dari biaya sewa dipotong saat pencairan; biaya pengiriman tidak dikenai komisi.</p>
        <a href="{{ route('legal.terms') }}#pemilik" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block font-semibold text-blue-700 underline underline-offset-2">Baca aturan untuk pemilik</a>
    </aside>
    <fieldset class="card card-pad min-w-0">
        <legend class="px-1 text-lg font-semibold text-zinc-950">Informasi Barang</legend>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <label for="name" class="field-label">Nama Barang <span class="text-red-600">*</span></label>
                <input id="name" maxlength="255" name="name" value="{{ old('name', $item?->name) }}" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror class="input">
                @error('name')<p id="name-error" data-error-field="name" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="category_id" class="field-label">Kategori <span class="text-red-600">*</span></label>
                <select id="category_id" name="category_id" required class="input" aria-invalid="{{ $errors->has('category_id') ? 'true' : 'false' }}" @error('category_id') aria-describedby="category_id-error" @enderror>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $item?->category_id) === $category->id)>
                            {{ $category->name }}{{ $category->is_active ? '' : ' (Nonaktif)' }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<p id="category_id-error" data-error-field="category_id" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="condition" class="field-label">Kondisi <span class="text-red-600">*</span></label>
                <select id="condition" name="condition" required class="input" aria-invalid="{{ $errors->has('condition') ? 'true' : 'false' }}" @error('condition') aria-describedby="condition-error" @enderror>
                    @foreach ($conditions as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $item?->condition?->value ?? 'good') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition')<p id="condition-error" data-error-field="condition" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <label for="description" class="field-label">Deskripsi <span class="text-red-600">*</span></label>
                <textarea id="description" name="description" minlength="20" maxlength="5000" rows="5" required class="input" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="description-error" @enderror>{{ old('description', $item?->description) }}</textarea>
                @error('description')<p id="description-error" data-error-field="description" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    <fieldset class="card card-pad min-w-0">
        <legend class="px-1 text-lg font-semibold text-zinc-950">Harga & Ketersediaan</legend>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <div>
                <label for="daily_price" class="field-label">Harga Sewa per Hari <span class="text-red-600">*</span></label>
                <input id="daily_price" name="daily_price" type="number" min="0.01" max="999999999.99" step="0.01" inputmode="decimal" autocomplete="off" data-currency-input value="{{ old('daily_price', $item?->daily_price) }}" required aria-invalid="{{ $errors->has('daily_price') ? 'true' : 'false' }}" class="input" aria-describedby="daily_price-help @error('daily_price') daily_price-error @enderror">
                <p id="daily_price-help" class="field-help">Harga sewa per unit, lebih dari Rp 0.</p>
                @error('daily_price')<p id="daily_price-error" data-error-field="daily_price" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="deposit_amount" class="field-label">Uang Muka (DP) per Unit</label>
                <input id="deposit_amount" name="deposit_amount" type="number" min="0" max="999999999.99" step="0.01" inputmode="decimal" autocomplete="off" data-currency-input value="{{ old('deposit_amount', $item?->deposit_amount) }}" aria-invalid="{{ $errors->has('deposit_amount') ? 'true' : 'false' }}" class="input" aria-describedby="deposit_amount-help @error('deposit_amount') deposit_amount-error @enderror">
                <p id="deposit_amount-help" class="field-help">Wajib lebih dari Rp 0 jika pembayaran DP diizinkan. DP menjadi bagian dari total pembayaran, bukan jaminan yang dikembalikan.</p>
                @error('deposit_amount')<p id="deposit_amount-error" data-error-field="deposit_amount" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="quantity" class="field-label">Jumlah Unit <span class="text-red-600">*</span></label>
                <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="9999" value="{{ old('quantity', $item?->quantity ?? 1) }}" required aria-invalid="{{ $errors->has('quantity') ? 'true' : 'false' }}" @error('quantity') aria-describedby="quantity-error" @enderror class="input">
                @error('quantity')<p id="quantity-error" data-error-field="quantity" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    <fieldset class="card card-pad min-w-0">
        <legend class="px-1 text-lg font-semibold text-zinc-950">Lokasi Pengambilan</legend>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div>
                <label for="city" class="field-label">Kota <span class="text-red-600">*</span></label>
                <input id="city" name="city" autocomplete="address-level2" maxlength="255" value="{{ old('city', $item?->city) }}" required aria-invalid="{{ $errors->has('city') ? 'true' : 'false' }}" @error('city') aria-describedby="city-error" @enderror class="input">
                @error('city')<p id="city-error" data-error-field="city" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="lg:col-span-2">
                <label for="address" class="field-label">Alamat lengkap pengambilan</label>
                <textarea id="address" name="address" autocomplete="street-address" maxlength="1000" aria-describedby="address-help @error('address') address-error @enderror" rows="3" class="input" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}">{{ old('address', $item?->address) }}</textarea>
                <p id="address-help" class="field-help">Wajib untuk barang aktif. Alamat lengkap hanya tersedia setelah pemesanan disetujui final.</p>
                @error('address')<p id="address-error" data-error-field="address" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5 grid gap-3 border-t border-zinc-100 pt-5" data-delivery-settings>
            <label class="flex items-center gap-3 text-sm font-medium text-zinc-800">
                <input type="hidden" name="delivery_enabled" value="0">
                <input id="delivery_enabled" type="checkbox" name="delivery_enabled" value="1" @checked(old('delivery_enabled', $item?->delivery_enabled)) aria-invalid="{{ $errors->has('delivery_enabled') ? 'true' : 'false' }}" @error('delivery_enabled') aria-describedby="delivery_enabled-error" @enderror class="h-4 w-4 accent-blue-700" data-delivery-enabled>
                Izinkan Pengiriman
            </label>
            <label class="flex items-center gap-3 text-sm text-zinc-600">
                <input type="hidden" name="free_delivery" value="0">
                <input id="free_delivery" type="checkbox" name="free_delivery" value="1" @checked(old('free_delivery', $item?->free_delivery)) aria-invalid="{{ $errors->has('free_delivery') ? 'true' : 'false' }}" @error('free_delivery') aria-describedby="free_delivery-error" @enderror class="h-4 w-4 accent-blue-700" data-free-delivery>
                Gratis Ongkir
            </label>
            <p class="field-help">Pengambilan langsung selalu tersedia. Biaya pengiriman ditawarkan setelah alamat penyewa diterima.</p>
            @error('delivery_enabled')<p id="delivery_enabled-error" data-error-field="delivery_enabled" class="field-error">{{ $message }}</p>@enderror
            @error('free_delivery')<p id="free_delivery-error" data-error-field="free_delivery" class="field-error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <fieldset class="card card-pad min-w-0" data-photo-upload data-existing-count="{{ $item?->photos->count() ?? 0 }}">
        <legend class="px-1 text-lg font-semibold text-zinc-950">{{ $item ? 'Tambahkan foto' : 'Foto barang' }}</legend>
        <div class="mt-5 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 sm:p-5">
            <label for="photos" class="field-label">Pilih foto barang</label>
            <p id="photos-help" class="mt-1 text-xs leading-6 text-zinc-500">JPG, PNG, WEBP. Total maksimum 5 foto, 5 MB per foto. Barang aktif wajib memiliki minimal satu foto; foto tersimpan tetap dihitung.</p>
            <input id="photos" name="photos[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="photos-help photos-status photos-error @if($errors->has('photos') || $errors->has('photos.*')) photos-server-error @endif" aria-invalid="{{ $errors->has('photos') || $errors->has('photos.*') ? 'true' : 'false' }}" class="photo-file-input mt-3" data-photo-input>
        </div>
        <p id="photos-status" class="mt-3 text-sm text-zinc-500" data-photo-status role="status" aria-live="polite"></p>
        <p id="photos-error" class="field-error" data-photo-error role="alert" hidden></p>
        <div class="photo-preview-grid mt-4" data-photo-preview></div>
        @if($errors->has('photos') || $errors->has('photos.*'))
            <div id="photos-server-error" class="field-error">
                @foreach($errors->getMessages() as $field => $messages)
                    @if($field === 'photos' || str_starts_with($field, 'photos.'))
                        @foreach($messages as $message)<p data-error-field="{{ $field }}">{{ $message }}</p>@endforeach
                    @endif
                @endforeach
            </div>
        @endif
    </fieldset>

    <fieldset class="card card-pad min-w-0">
        <legend class="px-1 text-lg font-semibold text-zinc-950">Aturan & Publikasi</legend>
        <div data-payment-policy>
            <h3 class="text-lg font-semibold text-zinc-950">Persyaratan Penyewa</h3>
            <p class="mt-2 text-sm leading-6 text-zinc-600">Berlaku untuk pemesanan berikutnya. Foto katalog tetap wajib untuk barang aktif. Foto kondisi sukarela tetap diperbolehkan jika kewajiban dimatikan.</p>
            <div class="my-5 grid gap-4">
                <input type="hidden" name="requires_condition_photos" value="0"><label for="requires_condition_photos" class="flex items-start gap-3 text-sm"><input id="requires_condition_photos" aria-invalid="{{ $errors->has('requires_condition_photos') ? 'true' : 'false' }}" @error('requires_condition_photos') aria-describedby="requires_condition_photos-error" @enderror type="checkbox" name="requires_condition_photos" value="1" @checked(old('requires_condition_photos', $item?->requires_condition_photos)) class="mt-1 h-4 w-4 accent-brand"><span>Wajib Foto Kondisi Saat Penyerahan &amp; Pengembalian</span></label>
                @error('requires_condition_photos')<p id="requires_condition_photos-error" data-error-field="requires_condition_photos" class="field-error">{{ $message }}</p>@enderror
                <input type="hidden" name="requires_identity_verification" value="0"><label for="requires_identity_verification" class="flex items-start gap-3 text-sm"><input id="requires_identity_verification" aria-invalid="{{ $errors->has('requires_identity_verification') ? 'true' : 'false' }}" @error('requires_identity_verification') aria-describedby="requires_identity_verification-error" @enderror type="checkbox" name="requires_identity_verification" value="1" @checked(old('requires_identity_verification', $item?->requires_identity_verification)) class="mt-1 h-4 w-4 accent-brand"><span>Wajib Verifikasi Identitas Penyewa melalui Administrator<span class="mt-1 block text-zinc-500">Pemilik hanya melihat status, tanpa salinan KTP/SIM.</span></span></label>
                @error('requires_identity_verification')<p id="requires_identity_verification-error" data-error-field="requires_identity_verification" class="field-error">{{ $message }}</p>@enderror
            </div>
            <h3 class="text-lg font-semibold text-zinc-950">Kebijakan Pembayaran</h3>
            <div class="mt-5 grid gap-3">
                <label class="flex items-start gap-3 text-sm font-medium text-zinc-800">
                    <input type="hidden" name="allow_deposit_payment" value="0">
                    <input id="allow_deposit_payment" type="checkbox" name="allow_deposit_payment" value="1" @checked(old('allow_deposit_payment', $item?->allow_deposit_payment)) aria-invalid="{{ $errors->has('allow_deposit_payment') ? 'true' : 'false' }}" @error('allow_deposit_payment') aria-describedby="allow_deposit_payment-error" @enderror class="mt-0.5 h-4 w-4 accent-blue-700" data-deposit-enabled>
                    <span>Izinkan penyewa membayar DP lebih dahulu<span class="mt-1 block font-normal text-zinc-500">Nominal DP mengikuti nilai uang muka per unit pada bagian Harga &amp; Ketersediaan.</span></span>
                </label>
                <input type="hidden" name="allow_cash_balance" value="{{ $item?->allow_cash_balance ? 1 : 0 }}">
                <p class="text-sm leading-6 text-zinc-500">Pemesanan baru dibayar ke Pinjemin dan diperiksa administrator. DP serta pelunasan melalui transfer; penyerahan setelah lunas. Komisi 5% dari biaya sewa dipotong saat pencairan, di luar biaya pengiriman. Ketentuan pemesanan lama tetap berlaku.</p>
                @error('allow_deposit_payment')<p id="allow_deposit_payment-error" data-error-field="allow_deposit_payment" class="field-error">{{ $message }}</p>@enderror
                @error('allow_cash_balance')<p id="allow_cash_balance-error" data-error-field="allow_cash_balance" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-5 grid gap-5">
            <div>
                <label for="rules" class="field-label">Aturan Penyewaan</label>
                <textarea id="rules" name="rules" maxlength="2000" rows="3" class="input" aria-invalid="{{ $errors->has('rules') ? 'true' : 'false' }}" @error('rules') aria-describedby="rules-error" @enderror>{{ old('rules', $item?->rules) }}</textarea>
                @error('rules')<p id="rules-error" data-error-field="rules" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm font-semibold text-zinc-800">
                    <input id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item?->is_active ?? true)) aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}" @error('is_active') aria-describedby="is_active-error" @enderror class="rounded border-zinc-300 text-blue-700">
                    Aktifkan barang setelah disimpan
                </label>
                @error('is_active')<p id="is_active-error" data-error-field="is_active" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>
</div>
