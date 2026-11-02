@php
    $verifiedAmount = $booking->verifiedAmount();
    $remainingAmount = $booking->remainingAmount();
    $paymentPolicy = $booking->fulfillment_snapshot['payment_policy'] ?? [];
    $methods = array_values($booking->payment_methods_snapshot ?? []);
    $depositAvailable = ($paymentPolicy['allow_deposit'] ?? false) && (float)$booking->deposit_amount > 0 && (float)$booking->deposit_amount < (float)$booking->total_amount;
    $selectedPlan = $booking->payment_plan?->value ?? old('payment_plan', 'full_transfer');
    if (!$booking->payment_plan && (!$depositAvailable || !in_array($selectedPlan, ['full_transfer', 'deposit_transfer', 'deposit_cash'], true) || ($selectedPlan === 'deposit_cash' && (!($paymentPolicy['allow_cash_balance'] ?? false) || $booking->fulfillment_method !== \App\Models\Booking::Pickup)))) {
        $selectedPlan = 'full_transfer';
    }
    $amountDue = $verifiedAmount > 0 ? $remainingAmount : ($selectedPlan === 'full_transfer' ? $booking->total_amount : $booking->deposit_amount);
@endphp

@if($booking->accepted_at || $booking->payment_legacy || $booking->payments->isNotEmpty() || $booking->refund_status)
    <section class="mt-7 border-t border-zinc-200 pt-6" aria-labelledby="payment-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="payment-title" class="text-lg font-semibold text-zinc-950">Pembayaran</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ $booking->collectsViaPlatform() ? 'Pembayaran ke rekening Pinjemin, diverifikasi manual oleh admin setelah dana masuk.' : 'Diverifikasi manual oleh pemilik, bukan oleh bank atau payment gateway.' }}</p>
            </div>
            @if($booking->payment_legacy)<span class="badge bg-zinc-100 text-zinc-600">Lunas - data lama</span>@endif
        </div>

        <dl class="mt-5 grid gap-3 rounded-md bg-zinc-50 p-4 text-sm sm:grid-cols-3">
            <div><dt class="text-zinc-500">Total tagihan</dt><dd class="mt-1 font-semibold text-zinc-950"><x-price :amount="$booking->total_amount" suffix="" /></dd></div>
            <div><dt class="text-zinc-500">Terverifikasi</dt><dd class="mt-1 font-semibold text-emerald-700">
                @if($booking->payment_legacy && !$verifiedAmount)
                    Tidak tercatat
                @else
                    <x-price :amount="$verifiedAmount" suffix="" />
                @endif
            </dd></div>
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
            <form method="POST" action="{{ route('my-bookings.payments.store', $booking) }}" enctype="multipart/form-data" class="mt-6 grid gap-4 rounded-md border border-blue-100 bg-blue-50/50 p-4" data-private-image-upload data-payment-form data-total="{{ $booking->total_amount }}" data-deposit="{{ $booking->deposit_amount }}" data-remaining="{{ $remainingAmount }}" data-verified="{{ $verifiedAmount }}" data-plan="{{ $booking->payment_plan?->value }}">
                @csrf
                <h3 class="font-semibold text-zinc-950">{{ $verifiedAmount > 0 ? 'Kirim Bukti Pelunasan' : 'Kirim Bukti Pembayaran' }}</h3>
                @if(!$booking->payment_plan)
                    <fieldset class="grid gap-2"><legend class="field-label">Cara pembayaran</legend>
                        <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="full_transfer" @checked($selectedPlan === 'full_transfer') class="mt-0.5 accent-blue-700"><span><strong>Bayar lunas</strong><span class="mt-1 block text-zinc-500"><x-price :amount="$booking->total_amount" suffix="" /></span></span></label>
                        @if($depositAvailable)
                            <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_transfer" @checked($selectedPlan === 'deposit_transfer') class="mt-0.5 accent-blue-700"><span><strong>Bayar DP, pelunasan transfer</strong><span class="mt-1 block text-zinc-500">DP <x-price :amount="$booking->deposit_amount" suffix="" /></span></span></label>
                            @if(($paymentPolicy['allow_cash_balance'] ?? false) && $booking->fulfillment_method === \App\Models\Booking::Pickup)
                                <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_cash" @checked($selectedPlan === 'deposit_cash') class="mt-0.5 accent-blue-700"><span><strong>Bayar DP, pelunasan tunai</strong><span class="mt-1 block text-zinc-500">Sisa dibayar saat barang diambil.</span></span></label>
                            @endif
                        @else
                            <p class="field-help">Pembayaran DP tidak tersedia untuk nominal dan ketentuan booking ini. Gunakan pembayaran lunas.</p>
                        @endif
                    </fieldset>
                @endif
                <p class="rounded-md bg-white p-3 text-sm font-semibold" aria-live="polite">Nominal yang harus ditransfer: <span data-payment-amount><x-price :amount="$amountDue" suffix="" /></span></p>
                <label class="block"><span class="field-label">Rekening tujuan</span><select name="method_index" required class="input">@foreach($methods as $index => $method)<option value="{{ $index }}" @selected((string)old('method_index', '0') === (string)$index)>{{ $method['label'] }} / {{ $method['account_name'] }}</option>@endforeach</select>@error('method_index')<span class="field-error">{{ $message }}</span>@enderror</label>
                @error('payment_plan')<p class="field-error">{{ $message }}</p>@enderror
                @error('transferred_at')<p class="field-error">{{ $message }}</p>@enderror
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
                            <p class="mt-3 text-xs text-zinc-500">Transfer {{ $payment->transferred_at?->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB @if($payment->reviewer) · Diperiksa {{ $payment->reviewer->name }} @endif @if($payment->transaction_reference) · Ref: {{ $payment->transaction_reference }} @endif</p>
                            @if($payment->owner_comment)<p class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-800"><strong>Komentar {{ $booking->collectsViaPlatform() ? 'admin' : 'pemilik' }}:</strong> {{ $payment->owner_comment }}</p>@endif
                            @if(auth()->user()->can('reviewPayment', $booking) && $payment->status === \App\BookingPaymentStatus::Submitted)
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.payments.review' : 'incoming-bookings.payments.review', [$booking, $payment]) }}" class="grid gap-3" data-confirm data-confirm-title="Verifikasi pembayaran?" data-confirm-message="Pastikan nominal dan transaksi sudah cocok dengan mutasi rekening. Screenshot saja tidak cukup." data-confirm-button="Verifikasi">
                                        @csrf @method('PATCH')<input type="hidden" name="decision" value="verify">
                                        @if($booking->collectsViaPlatform())<label><span class="field-label">Referensi transaksi masuk</span><input name="transaction_reference" required maxlength="150" class="input" placeholder="Referensi dari mutasi rekening">@error('transaction_reference')<span class="field-error">{{ $message }}</span>@enderror</label>@endif
                                        <x-button type="submit">Verifikasi Pembayaran</x-button>
                                    </form>
                                    <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.payments.review' : 'incoming-bookings.payments.review', [$booking, $payment]) }}" class="grid gap-2">
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

        @if($viewer === 'renter' && $booking->status === \App\BookingStatus::PartiallyPaid && $booking->payment_plan === \App\PaymentPlan::DepositCash)
            <p class="mt-5 rounded-md bg-blue-50 p-4 text-sm leading-6 text-blue-900">DP sudah diterima. Sisa <x-price :amount="$remainingAmount" suffix="" /> dibayar tunai saat pengambilan. Pemilik mencatat pelunasan bersamaan dengan penyerahan barang.</p>
        @endif

        @if($booking->refund_status)
            <div class="mt-6 rounded-md {{ $booking->refund_status === 'refunded' ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }} p-4 text-sm">
                <p class="font-semibold">{{ $booking->refund_status === 'refunded' ? 'Refund sudah dicatat' : ($booking->collectsViaPlatform() ? 'Menunggu refund dari admin Pinjemin' : 'Menunggu refund dari pemilik') }}</p>
                <p class="mt-1">Nominal refund: <x-price :amount="$verifiedAmount" suffix="" /></p>
                @if($booking->refund)<a href="{{ route('bookings.refund-proof', $booking) }}" target="_blank" rel="noopener" class="mt-2 inline-block font-semibold underline">Lihat bukti refund</a>@endif
            </div>
            @if($booking->collectsViaPlatform() && $booking->refund_destination)
                <p class="mt-3 rounded-md border border-zinc-200 p-3 text-sm">Tujuan refund: {{ $booking->refund_destination['provider'] }} / {{ $booking->refund_destination['account_name'] }} / {{ $booking->refund_destination['account_identifier'] }}. Tujuan dikunci setelah disimpan.</p>
            @elseif($booking->collectsViaPlatform() && $viewer === 'renter' && $booking->refund_status === 'pending')
                <form method="POST" action="{{ route('my-bookings.refund-destination', $booking) }}" class="mt-4 grid gap-3 rounded-md border border-zinc-200 p-4">
                    @csrf @method('PUT')<h3 class="font-semibold">Rekening Tujuan Refund</h3><p class="text-sm text-zinc-600">Periksa nomor dan nama penerima. Tujuan dikunci setelah disimpan untuk mencegah perubahan saat admin mentransfer.</p>
                    <label><span class="field-label">Bank / e-wallet</span><input name="provider" value="{{ old('provider') }}" required maxlength="100" class="input"></label>
                    <label><span class="field-label">Nama penerima</span><input name="account_name" value="{{ old('account_name', auth()->user()->name) }}" required maxlength="255" class="input"></label>
                    <label><span class="field-label">Nomor rekening / akun</span><input name="account_identifier" value="{{ old('account_identifier') }}" required maxlength="255" class="input"></label>
                    <x-button type="submit" class="w-fit">Simpan Tujuan Refund</x-button>
                </form>
            @endif
            @if(auth()->user()->can('recordRefund', $booking) && $booking->collectsViaPlatform() && $booking->refund_destination)
                @if(!$booking->refund_claimed_by_user_id)
                    <form method="POST" action="{{ route('admin.claim-refund', $booking) }}" class="mt-4">@csrf<x-button type="submit">Mulai Proses Refund</x-button></form>
                @elseif($booking->refund_claimed_by_user_id !== auth()->id())
                    <p class="mt-3 text-sm text-amber-800">Refund sedang ditangani admin #{{ $booking->refund_claimed_by_user_id }}. Jangan mentransfer ulang.</p>
                @endif
            @endif
            @if(auth()->user()->can('recordRefund', $booking) && (!$booking->collectsViaPlatform() || ($booking->refund_destination && $booking->refund_claimed_by_user_id === auth()->id())))
                <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.refund' : 'incoming-bookings.refund', $booking) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-md border border-amber-200 p-4" data-private-image-upload>
                    @csrf
                    <h3 class="font-semibold text-zinc-950">Catat Refund Penuh</h3>
                    <label><span class="field-label">Waktu refund</span><input type="datetime-local" name="refunded_at" value="{{ old('refunded_at', now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i')) }}" required class="input">@error('refunded_at')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label><span class="field-label">Bukti refund</span><input type="file" name="proof" required accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-private-image-input>@error('proof')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <div data-private-image-preview hidden><img alt="Preview bukti refund" class="max-h-80 w-full rounded-md border border-zinc-200 object-contain"></div>
                    <label><span class="field-label">Catatan</span><textarea name="note" rows="3" maxlength="1000" class="input">{{ old('note') }}</textarea></label>
                    <x-button type="submit" class="w-fit">Simpan Refund</x-button>
                </form>
            @endif
        @endif
    </section>
@endif
