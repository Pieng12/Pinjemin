@php($prefix = $current ? 'method-'.$current->id : 'method-new')
@php($fieldValue = fn ($name, $default = null) => old('_payment_method_form') === $prefix ? old($name, $default) : $default)
@php($formErrors = $errors->getBag($prefix))
<input type="hidden" name="_payment_method_form" value="{{ $prefix }}">
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block"><span class="field-label">Jenis</span><select name="type" required class="input" data-payment-type>
        @foreach($types as $value => $label)<option value="{{ $value }}" @selected($fieldValue('type', $current?->type?->value ?? 'bank') === $value)>{{ $label }}</option>@endforeach
    </select></label>
    <label class="block"><span class="field-label">Nama bank / metode lainnya</span><input name="provider" value="{{ $fieldValue('provider', $current?->provider) }}" maxlength="100" class="input" placeholder="Contoh: BCA">@if($formErrors->has('provider'))<span class="field-error">{{ $formErrors->first('provider') }}</span>@endif</label>
    <label class="block"><span class="field-label">Nama akun / merchant</span><input name="account_name" value="{{ $fieldValue('account_name', $current?->account_name) }}" required maxlength="255" class="input">@if($formErrors->has('account_name'))<span class="field-error">{{ $formErrors->first('account_name') }}</span>@endif</label>
    <label class="block"><span class="field-label">Nomor akun / rekening</span><input name="account_identifier" value="{{ $fieldValue('account_identifier', $current?->account_identifier) }}" maxlength="255" class="input">@if($formErrors->has('account_identifier'))<span class="field-error">{{ $formErrors->first('account_identifier') }}</span>@endif</label>
    <label class="block sm:col-span-2"><span class="field-label">Gambar QRIS</span><input name="qris_image" type="file" accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-private-image-input><span class="field-help">Wajib untuk jenis QRIS. JPG, PNG, atau WebP maksimal 5 MB.</span></label>
    <div class="sm:col-span-2" data-private-image-preview hidden><img alt="Preview gambar" class="max-h-64 w-full rounded-md border border-zinc-200 bg-zinc-50 object-contain p-2"></div>
    <label class="block sm:col-span-2"><span class="field-label">Instruksi tambahan</span><textarea name="instructions" rows="3" maxlength="1000" class="input">{{ $fieldValue('instructions', $current?->instructions) }}</textarea></label>
    <label class="block"><span class="field-label">Urutan</span><input name="sort_order" type="number" min="0" max="999" value="{{ $fieldValue('sort_order', $current?->sort_order ?? 0) }}" class="input"></label>
</div>
<div class="flex flex-wrap gap-5 text-sm">
    <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($fieldValue('is_active', $current?->is_active ?? true)) class="h-4 w-4 accent-blue-700"> Aktif</label>
    <input type="hidden" name="is_primary" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1" @checked($fieldValue('is_primary', $current?->is_primary ?? false)) class="h-4 w-4 accent-blue-700"> Jadikan utama</label>
</div>
@if($formErrors->any())<ul class="grid gap-1 text-sm text-red-700" role="alert">@foreach($formErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
