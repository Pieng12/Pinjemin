@php
    $isOwner = auth()->id() === $chatItem->user_id;
    $replies = $isOwner ? [
        'Tanya periode sewa' => "Halo, kapan Anda ingin mulai menyewa {$chatItem->name} dan kapan rencana pengembaliannya?",
        'Tanya kebutuhan' => 'Untuk keperluan apa barang ini akan digunakan?',
        'Atur pengambilan' => "Pengambilan barang dilakukan di {$chatItem->city}. Kapan Anda berencana mengambilnya?",
    ] : [
        'Cek ketersediaan' => "Halo, apakah {$chatItem->name} masih tersedia untuk disewa?",
        'Tanya kondisi' => "Bagaimana kondisi {$chatItem->name} saat ini?",
        'Tanya kelengkapan' => 'Apa saja perlengkapan yang termasuk dalam sewa barang ini?',
        'Lokasi pengambilan' => 'Di area mana barang ini bisa diambil?',
    ];
    if ($chatItem->delivery_enabled) {
        $replies['Opsi pengiriman'] = $isOwner
            ? 'Apakah Anda ingin mengambil barang langsung atau menggunakan pengiriman?'
            : 'Apakah barang ini bisa dikirim ke kota saya?';
    }
@endphp

<details class="group mb-2" data-chat-quick-replies hidden @if($showReplies ?? false) open @endif>
    <summary class="flex min-h-9 w-fit cursor-pointer list-none items-center gap-1.5 rounded-md px-1 text-xs font-semibold text-brand-700 hover:underline [&::-webkit-details-marker]:hidden">
        <i data-lucide="message-circle" class="size-4" aria-hidden="true"></i> Pesan cepat
        <i data-lucide="chevron-down" class="size-3 group-open:rotate-180" aria-hidden="true"></i>
    </summary>
    <div class="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto px-1 pt-1 pb-1">
        @foreach($replies as $label => $reply)
            <button type="button" data-quick-reply="{{ $reply }}" class="min-h-10 rounded-md border border-brand-200 bg-brand-50 px-2.5 text-xs font-semibold text-brand-700 hover:border-brand-700 disabled:opacity-50">{{ $label }}</button>
        @endforeach
    </div>
</details>
