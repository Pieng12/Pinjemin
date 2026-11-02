@if($booking->collectsViaPlatform())
    @can('viewPayout', $booking)
        <section class="mt-7 rounded-lg border border-zinc-200 bg-white p-5" aria-labelledby="payout-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="payout-title" class="text-lg font-semibold">Pencairan Pemilik</h2><span class="badge bg-blue-50 text-blue-700">{{ $booking->payout?->statusLabel() ?? 'Belum diterima pemilik' }}</span></div>
            <p class="mt-2 text-sm leading-6 text-zinc-600">Komisi {{ $booking->fulfillment_snapshot['commission_rate'] }}% dari biaya sewa, di luar ongkir. DP bagian dari total. Transfer manual setelah pengembalian terkonfirmasi dan masalah selesai; target maksimal 1×24 jam. Pemilik membiayai ongkir lebih dahulu, lalu menerima penggantiannya bersama pencairan.</p>
            @php($payout = $booking->payout)
            @php($fee = $payout?->commission_amount ?? round((float)$booking->subtotal * (float)$booking->fulfillment_snapshot['commission_rate'] / 100, 2))
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-zinc-500">Total booking</dt><dd class="mt-1 font-semibold"><x-price :amount="$booking->total_amount" suffix="" /></dd></div>
                <div><dt class="text-zinc-500">{{ $payout?->status === 'cancelled' ? 'Komisi dibatalkan' : 'Komisi platform' }}</dt><dd class="mt-1 font-semibold"><x-price :amount="$payout?->status === 'cancelled' ? 0 : $fee" suffix="" /></dd></div>
                <div><dt class="text-zinc-500">Hak bersih pemilik</dt><dd class="mt-1 font-semibold text-brand"><x-price :amount="$payout?->status === 'cancelled' ? 0 : ($payout?->amount ?? (float)$booking->total_amount - $fee)" suffix="" /></dd></div>
            </dl>
            @if($payout)
                <div class="mt-4 rounded-md bg-zinc-50 p-4 text-sm"><p class="font-semibold">Tujuan pencairan saat booking diterima</p><p class="mt-1 break-words">{{ $payout->destination_snapshot['label'] }} / {{ $payout->destination_snapshot['account_name'] }}</p><p class="mt-1 break-all font-mono">{{ $payout->destination_snapshot['account_identifier'] }}</p><p class="mt-2 text-xs text-zinc-500">Perubahan profil tidak mengubah tujuan booking ini. Biaya transfer pencairan ditanggung Pinjemin.</p></div>
                @if($payout->hold_reason)<p class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900"><strong>Dana ditahan:</strong> {{ $payout->hold_reason }}</p>@endif
                @if($payout->paid_at)
                    <p class="mt-4 text-sm">Ditransfer {{ $payout->paid_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB · Ref: {{ $payout->transaction_reference }}</p>
                    <div class="mt-3 flex flex-wrap gap-2"><x-button :href="route('bookings.payout-proof', $booking)" variant="outline" target="_blank">Lihat Bukti Transfer</x-button><x-button :href="route('bookings.payout-receipt', $booking)" variant="outline">Unduh Rincian Pencairan</x-button></div>
                @endif
                @can('managePayout', $booking)
                    <div class="mt-5 grid gap-4">
                        @if($payout->status === 'pending' && $payout->isEligible())
                            <form method="POST" action="{{ route('bookings.payout', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="start"><x-button type="submit">Mulai Proses Pencairan</x-button></form>
                        @endif
                        @if(in_array($payout->status, ['pending', 'on_hold'], true))
                            <form method="POST" action="{{ route('bookings.payout', $booking) }}" class="grid gap-3">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $payout->status === 'on_hold' ? 'release' : 'hold' }}"><label><span class="field-label">{{ $payout->status === 'on_hold' ? 'Alasan penyelesaian masalah' : 'Alasan menahan pencairan' }}</span><textarea name="note" required maxlength="1000" rows="2" class="input"></textarea></label><x-button type="submit" variant="outline" class="w-fit">{{ $payout->status === 'on_hold' ? 'Selesaikan Masalah & Lepas Penahanan' : 'Tahan Pencairan' }}</x-button></form>
                        @endif
                        @if($payout->status === 'processing')
                            @if($payout->processed_by_user_id === auth()->id())
                                <form method="POST" action="{{ route('bookings.payout', $booking) }}" enctype="multipart/form-data" class="grid gap-3" data-private-image-upload data-confirm data-confirm-title="Catat transfer pencairan?" data-confirm-message="Pastikan transfer ke rekening tujuan sudah berhasil dan nominal sesuai hak pemilik. Jangan transfer ulang jika pencatatan gagal." data-confirm-button="Catat Transfer">
                                    @csrf @method('PATCH')<input type="hidden" name="action" value="paid"><p class="text-sm font-semibold">Transfer tepat <x-price :amount="$payout->amount" suffix="" /> ke tujuan di atas, lalu catat buktinya.</p>
                                    <label><span class="field-label">Waktu transfer (WIB)</span><input type="datetime-local" name="paid_at" value="{{ old('paid_at', now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i')) }}" required class="input"></label>
                                    <label><span class="field-label">Referensi transfer</span><input name="transaction_reference" value="{{ old('transaction_reference') }}" required maxlength="150" class="input"></label>
                                    <label><span class="field-label">Bukti transfer</span><input type="file" name="proof" required accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-private-image-input><span class="field-help">JPG, PNG, atau WebP maksimal 5 MB.</span></label><div data-private-image-preview hidden><img alt="Preview bukti pencairan" class="max-h-80 w-full object-contain"></div>
                                    <label><span class="field-label">Catatan</span><textarea name="note" maxlength="1000" rows="2" class="input">{{ old('note') }}</textarea></label><x-button type="submit" class="w-fit">Catat Transfer Berhasil</x-button>
                                </form>
                            @else<p class="text-sm text-amber-800">Sedang ditangani admin #{{ $payout->processed_by_user_id }}. Koordinasikan sebelum melakukan transfer agar tidak terjadi pembayaran ganda.</p>@endif
                        @endif
                    </div>
                @endcan
                @if($viewer === 'owner' && $payout->status === 'paid' && !$payout->received_at)
                    <form method="POST" action="{{ route('bookings.payout', $booking) }}" class="mt-4">@csrf @method('PATCH')<input type="hidden" name="action" value="receive"><x-button type="submit">Konfirmasi Dana Diterima</x-button></form>
                @endif
                @if($payout->events)
                    <details class="mt-5 border-t border-zinc-100 pt-4"><summary class="cursor-pointer text-sm font-semibold">Riwayat pencairan</summary><ol class="mt-3 grid gap-3 text-sm">@foreach($payout->events as $event)<li><strong>{{ match($event['action']) {'hold', 'report' => 'Dana ditahan', 'release' => 'Penahanan dilepas', 'start' => 'Pencairan dimulai', 'paid' => 'Transfer dicatat', 'receive' => 'Dana diterima', default => $event['action']} }}</strong> · {{ $event['actor_name'] }} · {{ \Illuminate\Support\Carbon::parse($event['at'])->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB @if($event['note'])<p class="mt-1 text-zinc-600">{{ $event['note'] }}</p>@endif</li>@endforeach</ol></details>
                @endif
            @endif
        </section>
    @endcan
    @can('reportPaymentIssue', $booking)
        @if($booking->payout && in_array($booking->payout->status, ['pending', 'on_hold'], true))
            <details class="mt-5 rounded-md border border-amber-200 p-4"><summary class="cursor-pointer text-sm font-semibold">Laporkan Masalah Transaksi</summary><form method="POST" action="{{ route('bookings.payout', $booking) }}" class="mt-3 grid gap-3">@csrf @method('PATCH')<input type="hidden" name="action" value="report"><p class="text-sm text-zinc-600">Laporan menahan pencairan sampai admin menyelesaikan pemeriksaan.</p><label><span class="field-label">Jelaskan masalah</span><textarea name="note" required rows="3" maxlength="1000" class="input"></textarea></label><x-button type="submit" variant="outline" class="w-fit">Kirim Laporan</x-button></form></details>
        @endif
    @endcan
@endif
