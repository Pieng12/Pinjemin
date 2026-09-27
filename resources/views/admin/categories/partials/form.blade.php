<div>
    <label for="name" class="field-label">Nama kategori</label>
    <input id="name" name="name" value="{{ old('name', $category?->name) }}" required class="input mt-1">
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div>
    <label for="icon" class="field-label">Icon label</label>
    <input id="icon" name="icon" value="{{ old('icon', $category?->icon) }}" class="input mt-1">
    @error('icon')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div>
    <label for="description" class="field-label">Deskripsi</label>
    <textarea id="description" name="description" rows="4" class="input mt-1">{{ old('description', $category?->description) }}</textarea>
    @error('description')<p class="field-error">{{ $message }}</p>@enderror
</div>

<label class="flex items-center gap-2 text-sm font-medium text-zinc-700">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) class="rounded border-zinc-300 text-blue-700 focus:ring-blue-600">
    Kategori aktif
</label>
