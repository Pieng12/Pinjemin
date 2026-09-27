@php
    $verifiedAmount = $booking->verifiedAmount();
    $remainingAmount = $booking->remainingAmount();
    $paymentPolicy = $booking->fulfillment_snapshot['payment_policy'] ?? [];
    $methods = array_values($booking->payment_methods_snapshot ?? []);
@endphp

@if($booking->accepted_at || $booking->payment_legacy || $booking->payments->isNotEmpty() || $booking->refund_status)
    <section class="mt-7 border-t border-zinc-200 pt-6" aria-labelledby="payment-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="payment-title" class="text-lg font-semibold text-zinc-950">Pembayaran</h2>
                <p class="mt-1 text-sm text-zinc-600">Diverifikasi manual oleh pemilik, bukan oleh bank atau payment gateway.</p>
            </div>
            @if($booking->payment_legacy)<span class="badge bg-zinc-100 text-zinc-600">Data lama</span>@endif
        </div>

        <dl class="mt-5 grid gap-3 rounded-md bg-zinc-50 p-4 text-sm sm:grid-cols-3">
            <div><dt class="text-zinc-500">Total tagihan</dt><dd class="mt-1 font-semibold text-zinc-950"><x-price :amount="$booking->total_amount" suffix="" /></dd></div>
            <div><dt class="text-zinc-500">Terverifikasi</dt><dd class="mt-1 font-semibold text-emerald-700"><x-price :amount="$verifiedAmount" suffix="" /></dd></div>
            <div><dt class="text-zinc-500">Sisa pembayaran</dt><dd class="mt-1 font-semibold text-blue-700"><x-price :amount="$remainingAmount" suffix="" /></dd></div>
        </dl>

        @if($booking->effectiveStatus() === \App\BookingStatus::AwaitingPayment && $booking->payment_due_at)
            <p class="mt-4 border-l-2 border-amber-500 bg-amber-50 p-3 text-sm text-amber-900">Kirim bukti sebelum {{ $booking->payment_due_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB. Bukti yang dikirim tepat waktu tidak kedaluwarsa saat diperiksa.</p>
        @endif

        @if($methods && in_array($viewer, ['renter', 'owner', 'admin'], true))
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach($methods as $index => $method)
                    <div class="rounded-md border border-zinc-200 p-4 text-sm">
                        <p class="font-semibold text-zinc-950">{{ $method['label'] }}</p>
                        <p class="mt-1 text-zinc-600">{{ $method['account_name'] }}</p>
                        @if($method['account_identifier'] ?? null)<p class="mt-2 break-all font-mono text-zinc-950">{{ $method['account_identifier'] }}</p>@endif
                        @if($method['qris_path'] ?? null)<img src="{{ route('bookings.payment-method-image', [$booking, $index]) }}" alt="QRIS {{ $method['account_name'] }}" class="mt-3 max-h-56 w-full rounded-md border border-zinc-200 bg-white object-contain p-2">@endif
                        @if($method['instructions'] ?? null)<p class="mt-3 whitespace-pre-line leading-6 text-zinc-600">{{ $method['instructions'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        @endif

        @can('submitPayment', $booking)
            <form method="POST" action="{{ route('my-bookings.payments.store', $booking) }}" enctype="multipart/form-data" class="mt-6 grid gap-4 rounded-md border border-blue-100 bg-blue-50/50 p-4" data-private-image-upload>
                @csrf
                <h3 class="font-semibold text-zinc-950">{{ $verifiedAmount > 0 ? 'Kirim Bukti Pelunasan' : 'Kirim Bukti Pembayaran' }}</h3>
                @if(!$booking->payment_plan)
                    <fieldset class="grid gap-2"><legend class="field-label">Cara pembayaran</legend>
                        <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="full_transfer" checked class="mt-0.5 accent-blue-700"><span><strong>Bayar lunas</strong><span class="mt-1 block text-zinc-500"><x-price :amount="$booking->total_amount" suffix="" /></span></span></label>
                        @if(($paymentPolicy['allow_deposit'] ?? false) && (float)$booking->deposit_amount > 0 && (float)$booking->deposit_amount < (float)$booking->total_amount)
                            <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_transfer" class="mt-0.5 accent-blue-700"><span><strong>Bayar DP, pelunasan transfer</strong><span class="mt-1 block text-zinc-500">DP <x-price :amount="$booking->deposit_amount" suffix="" /></span></span></label>
                            @if(($paymentPolicy['allow_cash_balance'] ?? false) && $booking->fulfillment_method === \App\Models\Booking::Pickup)
                                <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_cash" class="mt-0.5 accent-blue-700"><span><strong>Bayar DP, pelunasan tunai</strong><span class="mt-1 block text-zinc-500">Sisa dibayar saat barang diambil.</span></span></label>
                            @endif
                        @endif
                    </fieldset>
                @endif
                <label class="block"><span class="field-label">Rekening tujuan</span><select name="method_index" required class="input">@foreach($methods as $index => $method)<option value="{{ $index }}">{{ $method['label'] }} / {{ $method['account_name'] }}</option>@endforeach</select></label>
                <label class="block"><span class="field-label">Waktu transfer</span><input type="datetime-local" name="transferred_at" value="{{ old('transferred_at', now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i')) }}" required class="input"></label>
                <label class="block"><span class="field-label">Foto bukti transaksi</span><input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="photo-file-input" data-private-image-input><span class="field-help">JPG, PNG, atau WebP maksimal 5 MB.</span></label>
                <div data-private-image-preview hidden><img alt="Preview bukti pembayaran" class="max-h-80 w-full rounded-md border border-zinc-200 bg-white object-contain p-2"></div>
                <label class="block"><span class="field-label">Catatan (opsional)</span><textarea name="renter_note" rows="3" maxlength="1000" class="input">{{ old('renter_note') }}</textarea></label>
                @error('payment')<p class="field-error">{{ $message }}</p>@enderror
                @error('proof')<p class="field-error">{{ $message }}</p>@enderror
                <x-button type="submit" class="w-fit">Kirim Bukti</x-button>
            </form>
        @endcan

        @if($booking->payments->isNotEmpty())
            <div class="mt-6">
                <h3 class="font-semibold text-zinc-950">Riwayat Pembayaran</h3>
                <div class="mt-3 grid gap-4">
                    @foreach($booking->payments->sortByDesc('id') as $payment)
                        <article class="rounded-md border border-zinc-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div><p class="font-semibold text-zinc-950">{{ match($payment->kind) {'deposit' => 'Pembayaran DP', 'balance' => 'Pelunasan', 'cash_balance' => 'Pelunasan tunai', default => 'Pembayaran lunas'} }}</p><p class="mt-1 text-sm text-zinc-600"><x-price :amount="$payment->amount" suffix="" /> / {{ $payment->method_snapshot['label'] ?? ucfirst($payment->channel) }}</p></div>
                                <span class="badge {{ $payment->status === \App\BookingPaymentStatus::Verified ? 'bg-emerald-50 text-emerald-700' : ($payment->status === \App\BookingPaymentStatus::Rejected ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">{{ $payment->status->label() }}</span>
                            </div>
                            @if($payment->proof_path)<a href="{{ route('bookings.payment-proof', [$booking, $payment]) }}" target="_blank" rel="noopener" class="mt-3 block"><img src="{{ route('bookings.payment-proof', [$booking, $payment]) }}" alt="Bukti {{ $payment->kind }}" class="max-h-96 w-full rounded-md border border-zinc-200 bg-zinc-50 object-contain"></a>@endif
                            @if($payment->owner_comment)<p class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-800"><strong>Komentar pemilik:</strong> {{ $payment->owner_comment }}</p>@endif
                            @if($viewer === 'owner' && $payment->status === \App\BookingPaymentStatus::Submitted)
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <form method="POST" action="{{ route('incoming-bookings.payments.review', [$booking, $payment]) }}" data-confirm data-confirm-title="Verifikasi pembayaran?" data-confirm-message="Nominal ini akan dicatat sebagai pembayaran terverifikasi." data-confirm-button="Verifikasi">
                                        @csrf @method('PATCH')<input type="hidden" name="decision" value="verify"><x-button type="submit">Verifikasi Pembayaran</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('incoming-bookings.payments.review', [$booking, $payment]) }}" class="grid gap-2">
                                        @csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><textarea name="owner_comment" required maxlength="1000" rows="2" class="input" placeholder="Jelaskan masalah pada bukti"></textarea><x-button type="submit" variant="danger">Tolak Bukti</x-button>
                                    </form>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        @if($viewer === 'owner' && $booking->status === \App\BookingStatus::PartiallyPaid && $booking->payment_plan === \App\PaymentPlan::DepositCash)
            <form method="POST" action="{{ route('incoming-bookings.cash-and-hand-over', $booking) }}" class="mt-5" data-confirm data-confirm-title="Terima tunai dan serahkan barang?" data-confirm-message="Pastikan sisa pembayaran sudah diterima. Pembayaran lunas dan penyerahan akan dicatat bersamaan." data-confirm-button="Konfirmasi dan Serahkan">
                @csrf @method('PATCH')<x-button type="submit">Konfirmasi Tunai &amp; Serahkan Barang</x-button>
            </form>
        @endif

        @if($booking->refund_status)
            <div class="mt-6 rounded-md {{ $booking->refund_status === 'refunded' ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }} p-4 text-sm">
                <p class="font-semibold">{{ $booking->refund_status === 'refunded' ? 'Refund sudah dicatat' : 'Menunggu refund dari pemilik' }}</p>
                <p class="mt-1">Nominal refund: <x-price :amount="$verifiedAmount" suffix="" /></p>
                @if($booking->refund)<a href="{{ route('bookings.refund-proof', $booking) }}" target="_blank" rel="noopener" class="mt-2 inline-block font-semibold underline">Lihat bukti refund</a>@endif
            </div>
            @if($viewer === 'owner' && $booking->refund_status === 'pending')
                <form method="POST" action="{{ route('incoming-bookings.refund', $booking) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-md border border-amber-200 p-4" data-private-image-upload>
                    @csrf
                    <h3 class="font-semibold text-zinc-950">Catat Refund Penuh</h3>
                    <label><span class="field-label">Waktu refund</span><input type="datetime-local" name="refunded_at" value="{{ now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i') }}" required class="input"></label>
                    <label><span class="field-label">Bukti refund</span><input type="file" name="proof" required accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-private-image-input></label>
                    <div data-private-image-preview hidden><img alt="Preview bukti refund" class="max-h-80 w-full rounded-md border border-zinc-200 object-contain"></div>
                    <label><span class="field-label">Catatan</span><textarea name="note" rows="3" maxlength="1000" class="input"></textarea></label>
                    <x-button type="submit" class="w-fit">Simpan Refund</x-button>
                </form>
            @endif
        @endif
    </section>
@endif
