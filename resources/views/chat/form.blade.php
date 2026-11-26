<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-chat-compose>
    @csrf
    <input type="hidden" name="submission_id" value="{{ old('submission_id', (string) \Illuminate\Support\Str::uuid()) }}">
    @if($chatItem)
        @include('chat.quick-replies')
    @endif
    <ul class="mb-2 flex max-h-28 flex-wrap gap-2 overflow-y-auto" data-chat-file-previews hidden aria-label="Pratinjau lampiran"></ul>
    <div class="flex min-w-0 items-end gap-2">
        <label class="btn btn-outline relative size-11 shrink-0 cursor-pointer px-0 focus-within:ring-2 focus-within:ring-brand-600 has-disabled:cursor-not-allowed has-disabled:opacity-50" title="Tambahkan lampiran">
            <i data-lucide="paperclip" class="size-5" aria-hidden="true"></i>
            <span class="sr-only">Tambahkan lampiran</span>
            <input type="file" name="attachments[]" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt" multiple data-chat-files
                data-max-files="{{ \App\Models\ChatMessage::MaxAttachments }}" data-max-bytes="{{ \App\Models\ChatMessage::MaxAttachmentKilobytes * 1024 }}"
                class="absolute inset-0 w-full cursor-pointer opacity-0 disabled:cursor-not-allowed" aria-describedby="chat-attachment-help chat-compose-error" @disabled($readOnly)>
        </label>
        <label for="chat-body" class="sr-only">Pesan</label>
        <textarea id="chat-body" name="body" rows="1" maxlength="2000" class="input mt-0 max-h-44 min-h-11 min-w-0 flex-1 resize-y" placeholder="Tulis pesan" aria-describedby="chat-compose-error" @error('body') aria-invalid="true" @enderror @disabled($readOnly)>{{ old('body') }}</textarea>
        <button type="submit" class="btn btn-primary size-11 shrink-0 px-0" aria-label="Kirim Pesan" title="Kirim Pesan" @disabled($readOnly)><i data-lucide="arrow-right" class="size-5" aria-hidden="true"></i><span class="sr-only">Kirim Pesan</span></button>
    </div>
    <p id="chat-attachment-help" class="mt-1.5 text-xs text-zinc-600">JPG, PNG, WebP, PDF, TXT · Maks. 3 file, 5 MB per file</p>
    <p id="chat-compose-error" class="mt-1 text-sm text-red-700 empty:hidden" role="alert" data-chat-error>{{ $errors->first('body') ?: $errors->first('attachments') ?: $errors->first('attachments.*') ?: $errors->first('submission_id') }}</p>
</form>
