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
    <section id="booking-payments" tabindex="-1" class="card card-pad mt-7 scroll-mt-24" aria-labelledby="payment-title" data-payment-section>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="payment-title" class="text-lg font-semibold text-zinc-950">Pembayaran</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ $booking->collectsViaPlatform() ? 'Administrator Pinjemin memeriksa pembayaran secara manual setelah dana masuk.' : 'Pada pemesanan ini, pemilik barang memeriksa pembayaran secara manual.' }}</p>
                <a href="{{ route('legal.terms') }}#pembayaran" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm font-semibold text-blue-700 underline underline-offset-2">Aturan pembayaran, uang muka, dan pengembalian dana</a>
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

        @if($booking->effectiveStatus() === \App\BookingStatus::PaymentReview)
            <p class="mt-4 rounded-md bg-brand-50 p-3 text-sm text-brand-800">Bukti pembayaran sedang diperiksa oleh {{ $booking->collectsViaPlatform() ? 'administrator Pinjemin' : 'pemilik barang' }}. Pantau hasilnya di riwayat pembayaran.</p>
        @endif

        @can('submitPayment', $booking)
            @php
                $bookingFormKey = 'payment-form-1';
                $bookingFormErrors = $errors;
                $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                $latestPayment = $booking->payments->sortByDesc('id')->first();
            @endphp
            <form method="POST" action="{{ route('my-bookings.payments.store', $booking) }}" enctype="multipart/form-data" class="mt-6 grid gap-4 rounded-md border border-blue-100 bg-blue-50/50 p-4" data-private-image-upload data-payment-form data-total="{{ $booking->total_amount }}" data-deposit="{{ $booking->deposit_amount }}" data-remaining="{{ $remainingAmount }}" data-verified="{{ $verifiedAmount }}" data-plan="{{ $booking->payment_plan?->value }}" data-form-id="payment-form-1">
                <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                <x-validation-summary :form-id="$bookingFormKey" :field-ids="['payment_plan' => 'payment-form-1-payment_plan-full_transfer', 'method_index' => 'payment-form-1-method_index', 'transferred_at' => 'payment-form-1-transferred_at', 'proof' => 'payment-form-1-proof', 'renter_note' => 'payment-form-1-renter_note']" />
                @csrf
                <h3 class="font-semibold text-zinc-950">{{ $verifiedAmount > 0 ? 'Kirim Bukti Pelunasan' : 'Kirim Bukti Pembayaran' }}</h3>
                @if($latestPayment?->status === \App\BookingPaymentStatus::Rejected)
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                        <p class="font-semibold">Bukti sebelumnya ditolak</p>
                        <p class="mt-1 whitespace-pre-line wrap-anywhere">{{ $latestPayment->owner_comment }}</p>
                        <p class="mt-1">Periksa alasan di atas, lalu kirim bukti yang sesuai dengan transaksi Anda.</p>
                    </div>
                @endif
                @if(!$booking->payment_plan)
                    <fieldset class="grid gap-2"><legend class="field-label">Cara pembayaran</legend>
                        <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="full_transfer" @checked($selectedPlan === 'full_transfer') class="mt-0.5 accent-blue-700" id="payment-form-1-payment_plan-full_transfer">@error('payment_plan')<span id="payment-form-1-payment_plan-full_transfer-error" data-error-field="payment_plan" class="field-error" role="alert">{{ $message }}</span>@enderror<span><strong>Bayar lunas</strong><span class="mt-1 block text-zinc-500"><x-price :amount="$booking->total_amount" suffix="" /></span></span></label>
                        @if($depositAvailable)
                            <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_transfer" @checked($selectedPlan === 'deposit_transfer') class="mt-0.5 accent-blue-700" id="payment-form-1-payment_plan-deposit_transfer"><span><strong>Bayar DP, pelunasan transfer</strong><span class="mt-1 block text-zinc-500">DP <x-price :amount="$booking->deposit_amount" suffix="" /></span></span></label>
                            @if(($paymentPolicy['allow_cash_balance'] ?? false) && $booking->fulfillment_method === \App\Models\Booking::Pickup)
                                <label class="flex items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm"><input type="radio" name="payment_plan" value="deposit_cash" @checked($selectedPlan === 'deposit_cash') class="mt-0.5 accent-blue-700" id="payment-form-1-payment_plan-deposit_cash"><span><strong>Bayar DP, pelunasan tunai</strong><span class="mt-1 block text-zinc-500">Sisa dibayar saat barang diambil.</span></span></label>
                            @endif
                        @else
                            <p class="field-help">Pembayaran DP tidak tersedia untuk nominal dan ketentuan pemesanan ini. Gunakan pembayaran lunas.</p>
                        @endif
                    </fieldset>
                @endif
                <noscript><style>[data-live-payment-amount] { display: none; }</style><p class="rounded-md bg-white p-3 text-sm leading-6">@if($verifiedAmount > 0)Nominal pelunasan: <strong><x-price :amount="$remainingAmount" suffix="" /></strong>.@else Lakukan pembayaran sesuai pilihan Anda: lunas <strong><x-price :amount="$booking->total_amount" suffix="" /></strong>@if($depositAvailable), atau DP <strong><x-price :amount="$booking->deposit_amount" suffix="" /></strong>@endif.@endif</p></noscript>
                <div class="rounded-md bg-white p-3 text-sm font-semibold" data-live-payment-amount><p aria-live="polite">Nominal yang harus ditransfer: <span data-payment-amount data-copy-source><x-price :amount="$amountDue" suffix="" /></span></p><button type="button" class="mt-1 min-h-11 text-brand-700" data-copy-amount hidden>Salin nominal</button></div>
                <label class="block"><span class="field-label">Rekening tujuan</span><select name="method_index" required class="input" id="payment-form-1-method_index" aria-describedby="payment-form-1-method_index-error" aria-invalid="{{ $errors->has('method_index') ? 'true' : 'false' }}">@foreach($methods as $index => $method)<option value="{{ $index }}" @selected((string)$bookingOld('method_index', '0') === (string)$index)>{{ $method['label'] }} / {{ $method['account_name'] }}</option>@endforeach</select>@error('method_index')<span id="payment-form-1-method_index-error" data-error-field="method_index" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                @include('bookings.partials.payment-destinations')

                <h4 class="border-t border-brand-100 pt-3 text-sm font-semibold text-zinc-950">Unggah bukti setelah transfer</h4>
                <label class="block"><span class="field-label">Waktu transfer (WIB)</span><input type="datetime-local" name="transferred_at" value="{{ $bookingOld('transferred_at', now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i')) }}" required class="input" id="payment-form-1-transferred_at" aria-describedby="payment-form-1-transferred_at-error" aria-invalid="{{ $errors->has('transferred_at') ? 'true' : 'false' }}">@error('transferred_at')<span id="payment-form-1-transferred_at-error" data-error-field="transferred_at" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="field-label">Foto bukti transaksi</span><input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="photo-file-input" data-private-image-input id="payment-form-1-proof" aria-describedby="payment-form-1-proof-error" aria-invalid="{{ $errors->has('proof') ? 'true' : 'false' }}">@error('proof')<span id="payment-form-1-proof-error" data-error-field="proof" class="field-error" role="alert">{{ $message }}</span>@enderror<span class="field-help">JPG, PNG, atau WebP maksimal 5 MB.</span></label>
                <div data-private-image-preview hidden><img alt="Pratinjau bukti pembayaran" class="max-h-80 w-full rounded-md border border-zinc-200 bg-white object-contain p-2"></div>
                <label class="block"><span class="field-label">Catatan (opsional)</span><textarea name="renter_note" rows="3" maxlength="1000" class="input" id="payment-form-1-renter_note" aria-describedby="payment-form-1-renter_note-error" aria-invalid="{{ $errors->has('renter_note') ? 'true' : 'false' }}">{{ $bookingOld('renter_note') }}</textarea>@error('renter_note')<span id="payment-form-1-renter_note-error" data-error-field="renter_note" class="field-error" role="alert">{{ $message }}</span>@enderror</label>


                <x-button type="submit" class="w-fit">Kirim Bukti</x-button>
            </form>
            @php
                $errors = $bookingFormErrors;
            @endphp

        @elsecan('view', $booking)
            @if($methods && in_array($viewer, ['renter', 'owner', 'admin'], true))
                <details class="mt-5">
                    <summary class="min-h-11 cursor-pointer text-sm font-semibold text-brand-700">Lihat metode pembayaran pada pemesanan</summary>
                    @include('bookings.partials.payment-destinations')
                </details>
            @endif
        @endcan

        @if($booking->payments->isNotEmpty())
            <details class="mt-6" id="payment-history" @if($booking->effectiveStatus() === \App\BookingStatus::PaymentReview || $booking->payments->contains(fn ($payment) => $payment->status === \App\BookingPaymentStatus::Rejected)) open @endif>
                <summary class="min-h-11 cursor-pointer font-semibold text-zinc-950">Riwayat Pembayaran ({{ $booking->payments->count() }})</summary>
                <div class="mt-3 grid gap-4">
                    @foreach($booking->payments->sortByDesc('id') as $payment)
                        <article class="rounded-md border border-zinc-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div><p class="font-semibold text-zinc-950">{{ match($payment->kind) {'deposit' => 'Pembayaran DP', 'balance' => 'Pelunasan', 'cash_balance' => 'Pelunasan tunai', default => 'Pembayaran lunas'} }}</p><p class="mt-1 text-sm text-zinc-600"><x-price :amount="$payment->amount" suffix="" /> / {{ $payment->method_snapshot['label'] ?? ucfirst($payment->channel) }}</p></div>
                                <span class="badge {{ $payment->status === \App\BookingPaymentStatus::Verified ? 'bg-emerald-50 text-emerald-700' : ($payment->status === \App\BookingPaymentStatus::Rejected ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">{{ $payment->status->label() }}</span>
                            </div>
                            @if($payment->proof_path)<a href="{{ route('bookings.payment-proof', [$booking, $payment]) }}" target="_blank" rel="noopener" class="mt-3 block max-w-xs"><img src="{{ route('bookings.payment-proof', [$booking, $payment]) }}" alt="Bukti {{ $payment->kind }}" class="h-40 w-full rounded-md border border-zinc-200 bg-zinc-50 object-contain"><span class="mt-2 block text-xs font-semibold text-brand-700">Lihat bukti ukuran penuh</span></a>@endif
                            <p class="mt-3 text-xs text-zinc-500">Transfer {{ $payment->transferred_at?->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB @if($payment->reviewer) · Diperiksa {{ $payment->reviewer->name }} @endif @if($payment->transaction_reference) · Ref: {{ $payment->transaction_reference }} @endif</p>
                            @if($payment->owner_comment)<p class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-800"><strong>Komentar {{ $booking->collectsViaPlatform() ? 'administrator' : 'pemilik' }}:</strong> {{ $payment->owner_comment }}</p>@endif
                            @if(auth()->user()->can('reviewPayment', $booking) && $payment->status === \App\BookingPaymentStatus::Submitted)
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @php
                                        $bookingFormKey = 'payment-form-2'.'-'.$payment->id;
                                        $bookingFormErrors = $errors;
                                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                                    @endphp
                                    <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.payments.review' : 'incoming-bookings.payments.review', [$booking, $payment]) }}" class="grid gap-3" data-confirm data-confirm-title="Verifikasi pembayaran?" data-confirm-message="Cocokkan nominal dan bukti pembayaran dengan mutasi rekening sebelum mengonfirmasi." data-confirm-button="Verifikasi" data-form-id="payment-form-2-{{ $payment->id }}">
                                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['transaction_reference' => 'payment-form-2-'. $payment->id .'-transaction_reference']" />
                                        @csrf @method('PATCH')<input type="hidden" name="decision" value="verify">
                                        @if($booking->collectsViaPlatform())<label><span class="field-label">Referensi transaksi masuk</span><input name="transaction_reference" required maxlength="150" class="input" placeholder="Referensi dari mutasi rekening" id="payment-form-2-{{ $payment->id }}-transaction_reference" aria-describedby="payment-form-2-{{ $payment->id }}-transaction_reference-error" aria-invalid="{{ $errors->has('transaction_reference') ? 'true' : 'false' }}">@error('transaction_reference')<span id="payment-form-2-{{ $payment->id }}-transaction_reference-error" data-error-field="transaction_reference" class="field-error" role="alert">{{ $message }}</span>@enderror</label>@endif
                                        <x-button type="submit">Verifikasi Pembayaran</x-button>
                                    </form>
                                    @php
                                        $errors = $bookingFormErrors;
                                    @endphp

                                    @php
                                        $bookingFormKey = 'payment-form-3'.'-'.$payment->id;
                                        $bookingFormErrors = $errors;
                                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                                    @endphp
                                    <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.payments.review' : 'incoming-bookings.payments.review', [$booking, $payment]) }}" class="grid gap-2" data-form-id="payment-form-3-{{ $payment->id }}">
                                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['owner_comment' => 'payment-form-3-'. $payment->id .'-owner_comment']" />
                                        @csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><textarea name="owner_comment" required maxlength="1000" rows="2" class="input" placeholder="Jelaskan masalah pada bukti" id="payment-form-3-{{ $payment->id }}-owner_comment" aria-describedby="payment-form-3-{{ $payment->id }}-owner_comment-error" aria-invalid="{{ $errors->has('owner_comment') ? 'true' : 'false' }}">{{ $bookingOld('owner_comment') }}</textarea>@error('owner_comment')<span id="payment-form-3-{{ $payment->id }}-owner_comment-error" data-error-field="owner_comment" class="field-error" role="alert">{{ $message }}</span>@enderror<x-button type="submit" variant="danger">Tolak Bukti</x-button>
                                    </form>
                                    @php
                                        $errors = $bookingFormErrors;
                                    @endphp

                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </details>
        @endif

        @if($viewer === 'owner' && auth()->user()->can('cashAndHandOver', $booking) && $booking->start_date->toDateString() <= now(\App\Models\Booking::RentalTimezone)->toDateString())
            @php
                $bookingFormKey = 'payment-form-4';
                $bookingFormErrors = $errors;
                $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
            @endphp
            <form method="POST" action="{{ route('incoming-bookings.cash-and-hand-over', $booking) }}" class="mt-5" data-confirm data-confirm-title="Terima tunai dan serahkan barang?" data-confirm-message="Pastikan sisa pembayaran sudah diterima. Pembayaran lunas dan penyerahan akan dicatat bersamaan." data-confirm-button="Konfirmasi dan Serahkan" data-form-id="payment-form-4">
                <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                @csrf @method('PATCH')<x-button type="submit">Konfirmasi Tunai &amp; Serahkan Barang</x-button>
            </form>
            @php
                $errors = $bookingFormErrors;
            @endphp

        @endif

        @if($viewer === 'renter' && $booking->status === \App\BookingStatus::PartiallyPaid && $booking->payment_plan === \App\PaymentPlan::DepositCash)
            <p class="mt-5 rounded-md bg-blue-50 p-4 text-sm leading-6 text-blue-900">DP sudah diterima. Sisa <x-price :amount="$remainingAmount" suffix="" /> dibayar tunai saat pengambilan. Pemilik mencatat pelunasan bersamaan dengan penyerahan barang.</p>
        @endif

        @if($booking->refund_status)
            <div class="mt-6 rounded-md {{ $booking->refund_status === 'refunded' ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900' }} p-4 text-sm">
                <p class="font-semibold">{{ $booking->refund_status === 'refunded' ? 'Pengembalian dana sudah dicatat' : ($booking->collectsViaPlatform() ? 'Menunggu pengembalian dana dari administrator Pinjemin' : 'Menunggu pengembalian dana dari pemilik') }}</p>
                <p class="mt-1">Nominal pengembalian dana: <x-price :amount="$verifiedAmount" suffix="" /></p>
                @if($booking->refund)<a href="{{ route('bookings.refund-proof', $booking) }}" target="_blank" rel="noopener" class="mt-2 inline-block font-semibold underline">Lihat bukti pengembalian dana</a>@endif
            </div>
            @if($booking->collectsViaPlatform() && $booking->refund_destination)
                <p class="mt-3 rounded-md border border-zinc-200 p-3 text-sm">Tujuan pengembalian dana: {{ $booking->refund_destination['provider'] }} / {{ $booking->refund_destination['account_name'] }} / {{ $booking->refund_destination['account_identifier'] }}. Tujuan dikunci setelah disimpan.</p>
            @elseif($booking->collectsViaPlatform() && $viewer === 'renter' && $booking->refund_status === 'pending')
                @php
                    $bookingFormKey = 'payment-form-5';
                    $bookingFormErrors = $errors;
                    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                @endphp
                <form method="POST" action="{{ route('my-bookings.refund-destination', $booking) }}" class="mt-4 grid gap-3 rounded-md border border-zinc-200 p-4" data-form-id="payment-form-5">
                    <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                    <x-validation-summary :form-id="$bookingFormKey" :field-ids="['provider' => 'payment-form-5-provider', 'account_name' => 'payment-form-5-account_name', 'account_identifier' => 'payment-form-5-account_identifier']" />

                    @csrf @method('PUT')<h3 class="font-semibold">Rekening Tujuan Pengembalian Dana</h3><p class="text-sm text-zinc-600">Periksa nomor dan nama penerima. Tujuan dikunci setelah disimpan untuk mencegah perubahan saat administrator mentransfer.</p>
                    <label><span class="field-label">Bank / e-wallet</span><input name="provider" value="{{ $bookingOld('provider') }}" required maxlength="100" class="input" id="payment-form-5-provider" aria-describedby="payment-form-5-provider-error" aria-invalid="{{ $errors->has('provider') ? 'true' : 'false' }}">@error('provider')<span id="payment-form-5-provider-error" data-error-field="provider" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <label><span class="field-label">Nama penerima</span><input name="account_name" value="{{ $bookingOld('account_name', auth()->user()->name) }}" required maxlength="255" class="input" id="payment-form-5-account_name" aria-describedby="payment-form-5-account_name-error" aria-invalid="{{ $errors->has('account_name') ? 'true' : 'false' }}">@error('account_name')<span id="payment-form-5-account_name-error" data-error-field="account_name" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <label><span class="field-label">Nomor rekening / akun</span><input name="account_identifier" value="{{ $bookingOld('account_identifier') }}" required maxlength="255" class="input" id="payment-form-5-account_identifier" aria-describedby="payment-form-5-account_identifier-error" aria-invalid="{{ $errors->has('account_identifier') ? 'true' : 'false' }}">@error('account_identifier')<span id="payment-form-5-account_identifier-error" data-error-field="account_identifier" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <x-button type="submit" class="w-fit">Simpan Tujuan Pengembalian Dana</x-button>
                </form>
                @php
                    $errors = $bookingFormErrors;
                @endphp

            @endif
            @if(auth()->user()->can('recordRefund', $booking) && $booking->collectsViaPlatform() && $booking->refund_destination)
                @if(!$booking->refund_claimed_by_user_id)
                    @php
                        $bookingFormKey = 'payment-form-6';
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('admin.claim-refund', $booking) }}" class="mt-4" data-form-id="payment-form-6">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />
@csrf<x-button type="submit">Mulai Proses Pengembalian Dana</x-button></form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                @elseif($booking->refund_claimed_by_user_id !== auth()->id())
                    <p class="mt-3 text-sm text-amber-800">Refund sedang ditangani admin #{{ $booking->refund_claimed_by_user_id }}. Jangan mentransfer ulang.</p>
                @endif
            @endif
            @if(auth()->user()->can('recordRefund', $booking) && (!$booking->collectsViaPlatform() || ($booking->refund_destination && $booking->refund_claimed_by_user_id === auth()->id())))
                @php
                    $bookingFormKey = 'payment-form-7';
                    $bookingFormErrors = $errors;
                    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                @endphp
                <form method="POST" action="{{ route($booking->collectsViaPlatform() ? 'admin.refund' : 'incoming-bookings.refund', $booking) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded-md border border-amber-200 p-4" data-private-image-upload data-form-id="payment-form-7">
                    <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                    <x-validation-summary :form-id="$bookingFormKey" :field-ids="['refunded_at' => 'payment-form-7-refunded_at', 'proof' => 'payment-form-7-proof', 'note' => 'payment-form-7-note']" />
                    @csrf
                    <h3 class="font-semibold text-zinc-950">Catat Pengembalian Dana Penuh</h3>
                    <label><span class="field-label">Waktu pengembalian dana</span><input type="datetime-local" name="refunded_at" value="{{ $bookingOld('refunded_at', now(\App\Models\Booking::RentalTimezone)->format('Y-m-d\TH:i')) }}" required class="input" id="payment-form-7-refunded_at" aria-describedby="payment-form-7-refunded_at-error" aria-invalid="{{ $errors->has('refunded_at') ? 'true' : 'false' }}">@error('refunded_at')<span id="payment-form-7-refunded_at-error" data-error-field="refunded_at" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <label><span class="field-label">Bukti pengembalian dana</span><input type="file" name="proof" required accept="image/jpeg,image/png,image/webp" class="photo-file-input" data-private-image-input id="payment-form-7-proof" aria-describedby="payment-form-7-proof-error" aria-invalid="{{ $errors->has('proof') ? 'true' : 'false' }}">@error('proof')<span id="payment-form-7-proof-error" data-error-field="proof" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <div data-private-image-preview hidden><img alt="Pratinjau bukti pengembalian dana" class="max-h-80 w-full rounded-md border border-zinc-200 object-contain"></div>
                    <label><span class="field-label">Catatan</span><textarea name="note" rows="3" maxlength="1000" class="input" id="payment-form-7-note" aria-describedby="payment-form-7-note-error" aria-invalid="{{ $errors->has('note') ? 'true' : 'false' }}">{{ $bookingOld('note') }}</textarea>@error('note')<span id="payment-form-7-note-error" data-error-field="note" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                    <x-button type="submit" class="w-fit">Simpan Pengembalian Dana</x-button>
                </form>
                @php
                    $errors = $bookingFormErrors;
                @endphp

            @endif
        @endif
    </section>
@endif
