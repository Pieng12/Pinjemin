@extends($viewer === 'owner' ? 'layouts.owner' : 'layouts.app')

@section('title', 'Pemesanan '.$booking->booking_code.' - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            $viewer === 'renter' ? 'Penyewaan Saya' : 'Pemesanan Masuk' => $viewer === 'renter' ? route('my-bookings.index') : route('incoming-bookings.index'),
            'Detail Pemesanan' => null,
        ]" />

        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold wrap-anywhere text-zinc-950 sm:text-3xl">Pemesanan {{ $booking->booking_code }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <div><x-booking-status-badge :status="$booking->status" :booking="$booking" class="min-h-7" /></div>
                    <p class="text-sm text-zinc-600">Diajukan {{ $booking->created_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->locale('id')->translatedFormat('d M Y, H:i') }} WIB</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($chatAvailable ?? false)
                    <x-button :href="route('chat.bookings.create', $booking)" variant="outline"><i data-lucide="message-circle" class="size-4" aria-hidden="true"></i> Percakapan</x-button>
                @endif
                @can('downloadInvoice', $booking)
                    <x-button :href="route('bookings.invoice', $booking)" variant="outline"><i data-lucide="download" class="h-4 w-4"></i>Unduh Tagihan</x-button>
                @endcan
                @can('downloadPaymentReceipt', $booking)<x-button :href="route('bookings.payment-receipt', $booking)" variant="outline"><i data-lucide="receipt-text" class="h-4 w-4"></i>Unduh Konfirmasi Pembayaran</x-button>@endcan
                @if ($viewer === 'renter')
                    <x-button href="{{ route('my-bookings.index') }}" variant="outline">Kembali</x-button>
                    @if ($booking->canBeCancelledByRenter())
                        @php
                            $bookingFormKey = 'booking-form-1';
                            $bookingFormErrors = $errors;
                            $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                            $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                            $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                        @endphp
                        <form
                            method="POST"
                            action="{{ route('my-bookings.cancel', $booking) }}"
                            data-confirm
                            data-confirm-title="Batalkan pemesanan?"
                            data-confirm-message="Permintaan sewa ini akan dibatalkan dan pemilik akan melihat status terbarunya."
                            data-confirm-button="Batalkan Pemesanan"
                         data-form-id="booking-form-1">
                            <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                            <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="danger">Batalkan Pemesanan</x-button>
                        </form>
                        @php
                            $errors = $bookingFormErrors;
                        @endphp

                    @endif
                @endif
            </div>
        </div>

        <x-booking-next-action :action="$nextAction" />

        @if($stockConflict ?? false)
            <p class="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800" role="alert">Ada konflik stok pada periode pemesanan ini, termasuk kemungkinan barang terlambat kembali. Periksa pengembalian sebelum menyerahkan barang kepada penyewa.</p>
        @endif

        <aside class="mt-4 text-sm leading-6 text-zinc-600" aria-label="Ketentuan dan bantuan pemesanan">
            @if ($viewer !== 'owner')<p class="mb-1.5">Ketentuan yang tersimpan saat pengajuan berlaku untuk pemesanan ini.</p>@endif
            <div class="flex flex-wrap gap-x-4 gap-y-1 font-medium text-brand-700">
                <a href="{{ route('legal.terms') }}#pembatalan" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2">Baca aturan pembatalan dan pengaduan</a>
                @can('reportPaymentIssue', $booking)
                    @if(in_array($booking->payout?->status, ['pending', 'on_hold'], true))
                        <a href="#booking-report-issue" data-action-link class="underline underline-offset-2">Laporkan masalah transaksi</a>
                    @endif
                @endcan
                <a href="mailto:{{ config('assistant.email') }}?subject={{ rawurlencode('Bantuan pemesanan '.$booking->booking_code) }}" class="underline underline-offset-2">Hubungi Pinjemin</a>
            </div>
        </aside>

        <div class="mt-5 grid items-start gap-4 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <aside class="min-w-0 space-y-5">
                <div class="card card-pad">
                    <h2 class="text-lg font-bold text-zinc-950">Barang</h2>
                    <div class="mt-4 flex gap-4">
                        <x-item-image :item="$booking->item" class="h-28 max-w-36" />
                        <div>
                            <p class="font-semibold text-zinc-950">{{ $booking->item->name }}</p>
                            <p class="mt-1 text-sm text-zinc-600">{{ $booking->item->category->name }} / {{ $booking->item->city }}</p>
                            <x-price :amount="$booking->daily_price" class="mt-3 block font-bold text-blue-700" />
                        </div>
                    </div>

                    <dl class="mt-6 grid gap-4 text-sm">
                        <div>
                            <dt class="font-medium text-zinc-500">Pemilik</dt>
                            <dd class="mt-1 text-zinc-950">{{ $booking->item->user->name }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-zinc-500">Penyewa</dt>
                            <dd class="mt-1 text-zinc-950">{{ $booking->renter->name }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-zinc-500">Kota penyewa</dt>
                            <dd class="mt-1 text-zinc-950">{{ $booking->renter->city ?? 'Belum diisi' }}</dd>
                        </div>
                    </dl>
                </div>

            </aside>

            <div class="card card-pad">
                <h2 class="text-lg font-bold text-zinc-950">Periode Sewa</h2>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-zinc-500">Tanggal mulai</dt><dd class="mt-1 text-zinc-950">{{ $booking->start_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Tanggal Pengembalian</dt><dd class="mt-1 font-semibold text-zinc-950">{{ $booking->end_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-zinc-500">Durasi</dt><dd class="mt-1 text-zinc-950">{{ $booking->rental_days }} hari</dd></div>
                    <div><dt class="font-medium text-zinc-500">Jumlah unit</dt><dd class="mt-1 text-zinc-950">{{ $booking->quantity }} unit</dd></div>
                </dl>
                @include('bookings.partials.fulfillment', ['booking' => $booking, 'showPickup' => $booking->invoice_snapshot !== null])

                @if($booking->effectiveStatus() === \App\BookingStatus::AwaitingRenterConfirmation)
                    <div id="booking-delivery" tabindex="-1" class="mt-6 scroll-mt-24 border-l-2 border-blue-600 bg-blue-50 p-4 text-sm text-blue-950">
                        <p class="font-semibold">{{ $viewer === 'renter' ? 'Tawaran biaya pengiriman menunggu persetujuan Anda' : 'Menunggu persetujuan total dari penyewa' }}</p>
                        <p class="mt-2 leading-6">Stok ditahan sampai {{ $booking->quote_expires_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB. Setelah batas ini, tawaran kedaluwarsa dan stok dibebaskan.</p>
                        @if($viewer === 'renter')
                            <div class="mt-4 flex flex-wrap gap-2">
                                @php
                                    $bookingFormKey = 'booking-form-2';
                                    $bookingFormErrors = $errors;
                                    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                                @endphp
                                <form method="POST" action="{{ route('my-bookings.confirm-delivery', $booking) }}" data-form-id="booking-form-2">
                                    <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                    <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />
@csrf @method('PATCH')<x-button type="submit">Setujui Total</x-button></form>
                                @php
                                    $errors = $bookingFormErrors;
                                @endphp

                                @php
                                    $bookingFormKey = 'booking-form-3';
                                    $bookingFormErrors = $errors;
                                    $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                    $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                    $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                                @endphp
                                <form method="POST" action="{{ route('my-bookings.decline-delivery', $booking) }}" data-confirm data-confirm-title="Tolak tawaran biaya pengiriman?" data-confirm-message="Pemesanan akan dibatalkan dan stok tidak lagi ditahan untuk Anda." data-confirm-button="Tolak dan Batalkan" data-form-id="booking-form-3">
                                    <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                    <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />
@csrf @method('PATCH')<x-button type="submit" variant="outline">Tolak dan Batalkan</x-button></form>
                                @php
                                    $errors = $bookingFormErrors;
                                @endphp

                            </div>
                        @endif
                    </div>
                @elseif($booking->effectiveStatus() === \App\BookingStatus::Expired)
                    <p class="mt-6 rounded-md bg-zinc-100 p-4 text-sm text-zinc-600">{{ $booking->payment_due_at ? 'Batas pembayaran sudah berakhir.' : 'Tawaran biaya pengiriman sudah kedaluwarsa.' }} Ajukan pemesanan baru untuk memilih periode yang tersedia.</p>
                @endif

                <div class="mt-6 rounded-md bg-zinc-50 p-4">
                    <h2 class="font-bold text-zinc-950">Rincian Biaya</h2>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-600">
                                <x-price :amount="$booking->daily_price" suffix="" />
                                x {{ $booking->rental_days }} hari x {{ $booking->quantity }}
                            </dt>
                            <dd class="font-medium text-zinc-950"><x-price :amount="$booking->subtotal" suffix="" /></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-600">{{ $booking->usesAdvancePaymentPricing() ? 'Pilihan DP (bagian dari total)' : 'DP sesuai perhitungan pengajuan lama' }}</dt>
                            <dd class="font-medium text-zinc-950"><x-price :amount="$booking->deposit_amount" suffix="" /></dd>
                        </div>
                        @if($booking->fulfillment_method === 'delivery')
                            <div class="flex justify-between gap-4"><dt class="text-zinc-600">Biaya Pengiriman ke penyewa</dt><dd class="font-medium text-zinc-950">
                                @if($booking->status === \App\BookingStatus::Pending && !($booking->fulfillment_snapshot['free_delivery'] ?? false))
                                    Menunggu tawaran
                                @else
                                    <x-price :amount="$booking->delivery_fee" suffix="" />
                                @endif
                            </dd></div>
                        @endif
                        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-base">
                            <dt class="font-bold text-zinc-950">Total</dt>
                            <dd class="font-bold text-blue-700"><x-price :amount="$booking->total_amount" suffix="" /></dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>
        @if($viewer === 'owner')
            <section id="booking-owner-actions" tabindex="-1" class="card card-pad mt-6 scroll-mt-24" aria-label="Tindakan pemilik">
                <h2 class="text-lg font-semibold">Tinjau &amp; Kelola Pemesanan</h2>
                @if($approvalBlocker ?? null)
                    <p class="mt-3 rounded-md bg-amber-50 p-4 text-sm leading-6 text-amber-900">{{ $approvalBlocker['message'] }}</p>
                @else
                    <div class="mt-4 flex flex-wrap gap-3">
                    @if ($booking->status === \App\BookingStatus::Pending && ($booking->fulfillment_method !== 'delivery' || ($booking->fulfillment_snapshot['free_delivery'] ?? false)))
                        @php
                            $bookingFormKey = 'booking-form-4';
                            $bookingFormErrors = $errors;
                            $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                            $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                            $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                        @endphp
                        <form method="POST" action="{{ route('incoming-bookings.approve', $booking) }}" data-form-id="booking-form-4">
                            <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                            <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                            @csrf
                            @method('PATCH')
                            <x-button type="submit">Terima</x-button>
                        </form>
                        @php
                            $errors = $bookingFormErrors;
                        @endphp

                    @endif
                    @can('handOver', $booking)
                    @if(!($handoverBlocker ?? null))
                        @php
                            $bookingFormKey = 'booking-form-5';
                            $bookingFormErrors = $errors;
                            $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                            $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                            $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                        @endphp
                        <form method="POST" action="{{ route('incoming-bookings.hand-over', $booking) }}" data-confirm data-confirm-title="Barang sudah diserahkan?" data-confirm-message="Setelah penyerahan, pemesanan tidak dapat dibatalkan. Stok dibebaskan setelah barang kembali." data-confirm-button="Ya, Sudah Diserahkan" data-form-id="booking-form-5">
                            <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                            <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                            @csrf @method('PATCH')
                            <x-button type="submit">Barang Diserahkan</x-button>
                        </form>
                        @php
                            $errors = $bookingFormErrors;
                        @endphp

                    @endif
                    @endcan
                    @can('complete', $booking)
                        @php
                            $bookingFormKey = 'booking-form-6';
                            $bookingFormErrors = $errors;
                            $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                            $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                            $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                        @endphp
                        <form method="POST" action="{{ route('incoming-bookings.complete', $booking) }}" data-confirm data-confirm-title="Semua barang sudah kembali?" data-confirm-message="Konfirmasi ini berlaku untuk seluruh unit pemesanan dan membebaskan sisa reservasi." data-confirm-button="Konfirmasi Kembali" data-form-id="booking-form-6">
                            <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                            <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                            @csrf @method('PATCH')
                            <x-button type="submit">Konfirmasi Barang Kembali</x-button>
                        </form>
                        @php
                            $errors = $bookingFormErrors;
                        @endphp

                    @endcan
                    </div>
                @endif
                @if($viewer === 'owner' && $booking->status === \App\BookingStatus::Pending && $booking->fulfillment_method === 'delivery' && !($booking->fulfillment_snapshot['free_delivery'] ?? false))
                    <section class="mt-6 border-t border-zinc-200 pt-6" aria-labelledby="delivery-quote-title">
                        <h2 id="delivery-quote-title" class="font-semibold text-zinc-950">Tawaran Biaya Pengiriman</h2>
                        @error('payment_method')
                            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
                        @enderror

                        @if($hasActivePaymentMethod && !($approvalBlocker ?? null))
                            @php
                                $bookingFormKey = 'booking-form-7';
                                $bookingFormErrors = $errors;
                                $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                            @endphp
                            <form method="POST" action="{{ route('incoming-bookings.quote-delivery', $booking) }}" class="mt-2" data-form-id="booking-form-7">
                                <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                <x-validation-summary :form-id="$bookingFormKey" :field-ids="[]" />

                                @csrf @method('PATCH')
                                <p class="text-sm leading-6 text-zinc-600">Biaya sekali untuk pengiriman ke alamat penyewa. Rp 0 diperbolehkan; total tetap perlu disetujui penyewa.</p>
                                <div class="mt-4"><x-currency-input name="delivery_fee" label="Biaya pengiriman" :value="$bookingOld('delivery_fee', 0)" required /></div>
                                <x-button type="submit" class="mt-4">Kirim Tawaran Biaya Pengiriman</x-button>
                            </form>
                            @php
                                $errors = $bookingFormErrors;
                            @endphp

                        @else
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4">
                                <p class="text-sm font-semibold text-amber-950">Metode pembayaran belum tersedia</p>
                                <p class="mt-1 text-sm leading-6 text-amber-900">{{ $booking->collectsViaPlatform() ? 'Tambahkan rekening atau e-wallet aktif sebagai tujuan pencairan. Penyewa membayar ke Pinjemin, dan administrator mencairkan hak pemilik setelah sewa selesai.' : 'Tambahkan dan aktifkan minimal satu rekening atau e-wallet agar penyewa mengetahui tujuan pembayaran setelah menyetujui biaya pengiriman.' }}</p>
                                <x-button :href="route('payment-methods.index')" variant="outline" class="mt-4">Atur Metode Pembayaran</x-button>
                            </div>
                        @endif
                    </section>
                @endif

            </section>
        @endif
        @include('bookings.partials.payment', ['booking' => $booking, 'viewer' => $viewer])
        @include('bookings.partials.inspections', ['booking' => $booking])
        @include('bookings.partials.payout', ['booking' => $booking, 'viewer' => $viewer])
                @php
                    $submittedAt = $booking->payments->sortByDesc('submitted_at')->first()?->submitted_at;
                    $depositPayment = $booking->payments->first(fn ($payment) => $payment->kind === 'deposit' && $payment->status === \App\BookingPaymentStatus::Verified);
                    $showDepositStep = $booking->payment_plan && $booking->payment_plan !== \App\PaymentPlan::FullTransfer;
                    $timeline = [
                        ['Pengajuan dikirim', $booking->created_at, true, $booking->status === \App\BookingStatus::Pending],
                        ['Pemesanan diterima', $booking->accepted_at, (bool) $booking->accepted_at, false],
                        ['Menunggu pembayaran', $booking->accepted_at, $booking->payments->isNotEmpty(), $booking->effectiveStatus() === \App\BookingStatus::AwaitingPayment],
                        ['Bukti pembayaran diperiksa', $submittedAt, (bool) $submittedAt, $booking->effectiveStatus() === \App\BookingStatus::PaymentReview],
                    ];
                    if ($showDepositStep) {
                        $timeline[] = ['DP diterima', $depositPayment?->reviewed_at, (bool) $depositPayment, $booking->status === \App\BookingStatus::PartiallyPaid];
                    }
                    $timeline[] = ['Pembayaran lunas', $booking->payment_verified_at, (bool) $booking->payment_verified_at, $booking->status === \App\BookingStatus::Approved && ! $booking->handed_over_at];
                    $timeline[] = ['Barang diserahkan', $booking->handed_over_at, (bool) $booking->handed_over_at, (bool) $booking->handed_over_at && ! $booking->completed_at];
                    $timeline[] = ['Penyewaan selesai', $booking->completed_at, (bool) $booking->completed_at, $booking->status === \App\BookingStatus::Completed];
                @endphp
                <details class="card card-pad mt-6" id="booking-history">
                    <summary class="cursor-pointer font-semibold">Alur Pemesanan &amp; Riwayat</summary>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-bold text-zinc-950">Alur Pemesanan</h2>
                        <span class="text-xs font-semibold text-zinc-500">{{ $booking->effectiveStatus()->label() }}</span>
                    </div>
                    <ol class="mt-5 grid gap-0 text-sm">
                        @foreach($timeline as [$label, $timestamp, $completed, $current])
                            <li class="relative flex min-h-14 gap-3 pb-4 last:min-h-0 last:pb-0">
                                @unless($loop->last)<span class="absolute left-[5px] top-3 h-full w-px {{ $completed ? 'bg-blue-200' : 'bg-zinc-200' }}" aria-hidden="true"></span>@endunless
                                <span class="relative mt-1 h-3 w-3 shrink-0 rounded-full border-2 {{ $completed ? 'border-blue-700 bg-blue-700' : ($current ? 'border-amber-500 bg-amber-100' : 'border-zinc-300 bg-white') }}" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <p class="font-semibold {{ $completed || $current ? 'text-zinc-950' : 'text-zinc-500' }}">{{ $label }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $timestamp ? $timestamp->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i').' WIB' : ($current ? 'Sedang berlangsung' : 'Belum tercatat') }}</p>
                                </div>
                            </li>
                        @endforeach
                        @if($booking->status === \App\BookingStatus::Cancelled)
                            <li class="mt-3 flex gap-3 border-t border-red-100 pt-4"><span class="mt-1 h-3 w-3 shrink-0 rounded-full bg-red-600"></span><div><p class="font-semibold text-red-700">Pemesanan dibatalkan</p><p class="mt-0.5 text-xs text-zinc-500">{{ $booking->cancelled_at?->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p></div></li>
                        @endif
                    </ol>
                </details>
        <div class="mt-6 grid gap-4">
            <div class="card card-pad">
                @if($booking->handed_over_at)
                    <p class="mt-5 text-sm text-zinc-600">Barang diserahkan: {{ $booking->handed_over_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB.</p>
                @endif
                @if($booking->completed_at)
                    <p class="mt-3 text-sm font-medium text-emerald-700">Pengembalian dikonfirmasi: {{ $booking->completed_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB.</p>
                @endif
                @if($booking->cancellation_reason)
                    <p class="mt-5 text-sm text-zinc-600">Alasan pembatalan: {{ $booking->cancellation_reason }}</p>
                @endif

                @if($viewer === 'owner')
                    @can('cancelAsOwner', $booking)
                        <details class="mt-6 border-t border-zinc-200 pt-5">
                            <summary class="cursor-pointer text-sm font-semibold text-red-600">Batalkan Pemesanan</summary>
                            @php
                                $bookingFormKey = 'booking-form-8';
                                $bookingFormErrors = $errors;
                                $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                                $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                                $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                            @endphp
                            <form method="POST" action="{{ route('incoming-bookings.cancel', $booking) }}" class="mt-4 grid gap-3" data-confirm data-confirm-title="Batalkan pemesanan ini?" data-confirm-message="Penyewa akan melihat alasan pembatalan dan reservasi stok akan dibebaskan." data-confirm-button="Batalkan Pemesanan" data-form-id="booking-form-8">
                                <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                                <x-validation-summary :form-id="$bookingFormKey" :field-ids="['cancellation_reason' => 'booking-form-8-cancellation_reason']" />

                                @csrf @method('PATCH')
                                <label class="block"><span class="field-label">Alasan pembatalan</span><textarea name="cancellation_reason" rows="3" maxlength="1000" required class="input" id="booking-form-8-cancellation_reason" aria-describedby="booking-form-8-cancellation_reason-error" aria-invalid="{{ $errors->has('cancellation_reason') ? 'true' : 'false' }}">{{ $bookingOld('cancellation_reason') }}</textarea>@error('cancellation_reason')<span id="booking-form-8-cancellation_reason-error" data-error-field="cancellation_reason" class="field-error" role="alert">{{ $message }}</span>@enderror</label>
                                <x-button type="submit" variant="danger">Batalkan Pemesanan</x-button>
                            </form>
                            @php
                                $errors = $bookingFormErrors;
                            @endphp

                        </details>
                    @endcan
                @endif

                <div class="mt-6 grid gap-4 text-sm">
                    <div>
                        <h2 class="font-semibold text-zinc-950">Catatan penyewa</h2>
                        <p class="mt-1 whitespace-pre-line text-zinc-700">{{ $booking->renter_note ?: 'Tidak ada catatan.' }}</p>
                    </div>
                    @if ($booking->owner_note)
                        <div>
                            <h2 class="font-semibold text-zinc-950">Catatan pemilik</h2>
                            <p class="mt-1 whitespace-pre-line text-zinc-700">{{ $booking->owner_note }}</p>
                        </div>
                    @endif
                </div>

                @if ($viewer === 'owner' && $booking->status === \App\BookingStatus::Pending)
                    @php
                        $bookingFormKey = 'booking-form-9';
                        $bookingFormErrors = $errors;
                        $bookingFormMatches = !old('_ui_form') || old('_ui_form') === $bookingFormKey;
                        $errors = $bookingFormMatches ? $bookingFormErrors : new \Illuminate\Support\ViewErrorBag;
                        $bookingOld = fn ($name, $default = null) => $bookingFormMatches ? old($name, $default) : $default;
                    @endphp
                    <form method="POST" action="{{ route('incoming-bookings.reject', $booking) }}" class="mt-6 rounded-md border border-red-100 bg-red-50 p-4" data-form-id="booking-form-9">
                        <input type="hidden" name="_ui_form" value="{{ $bookingFormKey }}">
                        <x-validation-summary :form-id="$bookingFormKey" :field-ids="['owner_note' => 'owner_note']" />

                        @csrf
                        @method('PATCH')
                        <label class="field-label text-red-950" for="owner_note">Alasan penolakan</label>
                        <textarea name="owner_note" id="owner_note" rows="3" class="input mt-2 border-red-200 focus:border-red-400 focus:ring-red-200" aria-describedby="owner_note-error" aria-invalid="{{ $errors->has('owner_note') ? 'true' : 'false' }}">{{ $bookingOld('owner_note') }}</textarea>@error('owner_note')<span id="owner_note-error" data-error-field="owner_note" class="field-error" role="alert">{{ $message }}</span>@enderror
                        <x-button type="submit" variant="danger" class="mt-3">Tolak Pemesanan</x-button>
                    </form>
                    @php
                        $errors = $bookingFormErrors;
                    @endphp

                @endif
            </div>
        </div>
    </section>
@endsection
