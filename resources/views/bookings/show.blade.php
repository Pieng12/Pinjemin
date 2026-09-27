@extends($viewer === 'owner' ? 'layouts.owner' : 'layouts.app')

@section('title', 'Booking '.$booking->booking_code.' - Pinjemin')

@section('content')
    <section class="page-shell">
        <x-breadcrumb :items="[
            $viewer === 'renter' ? 'Penyewaan Saya' : 'Booking Masuk' => $viewer === 'renter' ? route('my-bookings.index') : route('incoming-bookings.index'),
            'Detail Booking' => null,
        ]" />

        <div class="page-header mt-6">
            <div>
                <x-booking-status-badge :status="$booking->status" :booking="$booking" />
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-950">Booking #{{ $booking->booking_code }}</h1>
                <p class="mt-2 text-zinc-600">Dibuat pada {{ $booking->created_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('downloadInvoice', $booking)
                    <x-button :href="route('bookings.invoice', $booking)" variant="outline"><i data-lucide="download" class="h-4 w-4"></i>Tagihan</x-button>
                @endcan
                @can('downloadPaymentReceipt', $booking)<x-button :href="route('bookings.payment-receipt', $booking)" variant="outline"><i data-lucide="receipt-text" class="h-4 w-4"></i>Konfirmasi Pembayaran</x-button>@endcan
                @if ($viewer === 'renter')
                    <x-button href="{{ route('my-bookings.index') }}" variant="outline">Kembali</x-button>
                    @if ($booking->canBeCancelledByRenter())
                        <form
                            method="POST"
                            action="{{ route('my-bookings.cancel', $booking) }}"
                            data-confirm
                            data-confirm-title="Batalkan booking?"
                            data-confirm-message="Permintaan sewa ini akan dibatalkan dan pemilik akan melihat status terbarunya."
                            data-confirm-button="Batalkan Booking"
                        >
                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="danger">Batalkan Booking</x-button>
                        </form>
                    @endif
                @else
                    <x-button href="{{ route('incoming-bookings.index') }}" variant="outline">Kembali</x-button>
                    @if ($booking->status === \App\BookingStatus::Pending && ($booking->fulfillment_method !== 'delivery' || ($booking->fulfillment_snapshot['free_delivery'] ?? false)))
                        <form method="POST" action="{{ route('incoming-bookings.approve', $booking) }}">
                            @csrf
                            @method('PATCH')
                            <x-button type="submit">Terima</x-button>
                        </form>
                    @endif
                    @if($booking->status === \App\BookingStatus::Approved && !$booking->handed_over_at && $booking->start_date->toDateString() <= now(\App\Models\Booking::RentalTimezone)->toDateString())
                        <form method="POST" action="{{ route('incoming-bookings.hand-over', $booking) }}" data-confirm data-confirm-title="Barang sudah diserahkan?" data-confirm-message="Setelah penyerahan, booking tidak dapat dibatalkan. Stok dibebaskan setelah barang kembali." data-confirm-button="Ya, Sudah Diserahkan">
                            @csrf @method('PATCH')
                            <x-button type="submit">Barang Diserahkan</x-button>
                        </form>
                    @endif
                    @can('complete', $booking)
                        <form method="POST" action="{{ route('incoming-bookings.complete', $booking) }}" data-confirm data-confirm-title="Semua barang sudah kembali?" data-confirm-message="Konfirmasi ini berlaku untuk seluruh unit booking dan membebaskan sisa reservasi." data-confirm-button="Konfirmasi Kembali">
                            @csrf @method('PATCH')
                            <x-button type="submit">Konfirmasi Barang Kembali</x-button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if($stockConflict ?? false)
            <p class="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-800" role="alert">Ada konflik stok pada periode booking ini, termasuk kemungkinan barang terlambat kembali. Periksa pengembalian sebelum menyerahkan barang kepada penyewa.</p>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[0.85fr_1.15fr]">
            <aside class="space-y-5">
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

                @php
                    $reviewedAt = $booking->payments->whereNotNull('reviewed_at')->sortByDesc('reviewed_at')->first()?->reviewed_at;
                    $depositPayment = $booking->payments->first(fn ($payment) => $payment->kind === 'deposit' && $payment->status === \App\BookingPaymentStatus::Verified);
                    $showDepositStep = $booking->payment_plan && $booking->payment_plan !== \App\PaymentPlan::FullTransfer;
                    $timeline = [
                        ['Pengajuan dikirim', $booking->created_at, true, $booking->status === \App\BookingStatus::Pending],
                        ['Booking diterima', $booking->accepted_at, (bool) $booking->accepted_at, false],
                        ['Menunggu pembayaran', $booking->accepted_at, $booking->payments->isNotEmpty(), $booking->status === \App\BookingStatus::AwaitingPayment],
                        ['Bukti pembayaran diperiksa', $reviewedAt, (bool) $reviewedAt, $booking->status === \App\BookingStatus::PaymentReview],
                    ];
                    if ($showDepositStep) {
                        $timeline[] = ['DP diterima', $depositPayment?->reviewed_at, (bool) $depositPayment, $booking->status === \App\BookingStatus::PartiallyPaid];
                    }
                    $timeline[] = ['Pembayaran lunas', $booking->payment_verified_at, (bool) $booking->payment_verified_at, $booking->status === \App\BookingStatus::Approved && ! $booking->handed_over_at];
                    $timeline[] = ['Barang diserahkan', $booking->handed_over_at, (bool) $booking->handed_over_at, (bool) $booking->handed_over_at && ! $booking->completed_at];
                    $timeline[] = ['Penyewaan selesai', $booking->completed_at, (bool) $booking->completed_at, $booking->status === \App\BookingStatus::Completed];
                @endphp
                <div class="soft-card card-pad">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-bold text-zinc-950">Alur Booking</h2>
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
                            <li class="mt-3 flex gap-3 border-t border-red-100 pt-4"><span class="mt-1 h-3 w-3 shrink-0 rounded-full bg-red-600"></span><div><p class="font-semibold text-red-700">Booking dibatalkan</p><p class="mt-0.5 text-xs text-zinc-500">{{ $booking->cancelled_at?->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB</p></div></li>
                        @endif
                    </ol>
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
                    <div class="mt-6 border-l-2 border-blue-600 bg-blue-50 p-4 text-sm text-blue-950">
                        <p class="font-semibold">{{ $viewer === 'renter' ? 'Tawaran ongkir menunggu persetujuanmu' : 'Menunggu persetujuan total dari penyewa' }}</p>
                        <p class="mt-2 leading-6">Stok ditahan sampai {{ $booking->quote_expires_at->copy()->timezone(\App\Models\Booking::RentalTimezone)->format('d M Y H:i') }} WIB. Setelah batas ini, tawaran kedaluwarsa dan stok dibebaskan.</p>
                        @if($viewer === 'renter')
                            <div class="mt-4 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('my-bookings.confirm-delivery', $booking) }}">@csrf @method('PATCH')<x-button type="submit">Setujui Total</x-button></form>
                                <form method="POST" action="{{ route('my-bookings.decline-delivery', $booking) }}" data-confirm data-confirm-title="Tolak tawaran ongkir?" data-confirm-message="Booking akan dibatalkan dan stok tidak lagi ditahan untukmu." data-confirm-button="Tolak dan Batalkan">@csrf @method('PATCH')<x-button type="submit" variant="outline">Tolak dan Batalkan</x-button></form>
                            </div>
                        @endif
                    </div>
                @elseif($booking->effectiveStatus() === \App\BookingStatus::Expired)
                    <p class="mt-6 rounded-md bg-zinc-100 p-4 text-sm text-zinc-600">Tawaran sudah kedaluwarsa. Ajukan booking baru untuk memilih periode yang tersedia.</p>
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
                            <dt class="text-zinc-600">Uang muka (DP)</dt>
                            <dd class="font-medium text-zinc-950"><x-price :amount="$booking->deposit_amount" suffix="" /></dd>
                        </div>
                        @if($booking->fulfillment_method === 'delivery')
                            <div class="flex justify-between gap-4"><dt class="text-zinc-600">Ongkir ke penyewa</dt><dd class="font-medium text-zinc-950">@if($booking->status === \App\BookingStatus::Pending && !($booking->fulfillment_snapshot['free_delivery'] ?? false))Menunggu tawaran @else<x-price :amount="$booking->delivery_fee" suffix="" />@endif</dd></div>
                        @endif
                        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-base">
                            <dt class="font-bold text-zinc-950">Total</dt>
                            <dd class="font-bold text-blue-700"><x-price :amount="$booking->total_amount" suffix="" /></dd>
                        </div>
                    </dl>
                </div>

                @include('bookings.partials.payment', ['booking' => $booking, 'viewer' => $viewer])

                @if($viewer === 'owner' && $booking->status === \App\BookingStatus::Pending && $booking->fulfillment_method === 'delivery' && !($booking->fulfillment_snapshot['free_delivery'] ?? false))
                    <section class="mt-6 border-t border-zinc-200 pt-6" aria-labelledby="delivery-quote-title">
                        <h2 id="delivery-quote-title" class="font-semibold text-zinc-950">Tawaran Ongkir</h2>
                        @error('payment_method')
                            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
                        @enderror

                        @if($hasActivePaymentMethod)
                            <form method="POST" action="{{ route('incoming-bookings.quote-delivery', $booking) }}" class="mt-2">
                                @csrf @method('PATCH')
                                <p class="text-sm leading-6 text-zinc-600">Biaya sekali untuk pengiriman ke alamat penyewa. Rp 0 diperbolehkan; total tetap perlu disetujui penyewa.</p>
                                <div class="mt-4"><x-currency-input name="delivery_fee" label="Ongkir" :value="old('delivery_fee', 0)" required /></div>
                                <x-button type="submit" class="mt-4">Kirim Tawaran Ongkir</x-button>
                            </form>
                        @else
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4">
                                <p class="text-sm font-semibold text-amber-950">Metode pembayaran belum tersedia</p>
                                <p class="mt-1 text-sm leading-6 text-amber-900">Tambahkan dan aktifkan minimal satu rekening atau e-wallet agar penyewa mengetahui tujuan pembayaran setelah menyetujui ongkir.</p>
                                <x-button :href="route('payment-methods.index')" variant="outline" class="mt-4">Atur Metode Pembayaran</x-button>
                            </div>
                        @endif
                    </section>
                @endif

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
                            <summary class="cursor-pointer text-sm font-semibold text-red-600">Batalkan Booking</summary>
                            <form method="POST" action="{{ route('incoming-bookings.cancel', $booking) }}" class="mt-4 grid gap-3" data-confirm data-confirm-title="Batalkan booking ini?" data-confirm-message="Penyewa akan melihat alasan pembatalan dan reservasi stok akan dibebaskan." data-confirm-button="Batalkan Booking">
                                @csrf @method('PATCH')
                                <label class="block"><span class="field-label">Alasan pembatalan</span><textarea name="cancellation_reason" rows="3" maxlength="1000" required class="input">{{ old('cancellation_reason') }}</textarea></label>
                                <x-button type="submit" variant="danger">Batalkan Booking</x-button>
                            </form>
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
                    <form method="POST" action="{{ route('incoming-bookings.reject', $booking) }}" class="mt-6 rounded-md border border-red-100 bg-red-50 p-4">
                        @csrf
                        @method('PATCH')
                        <label class="field-label text-red-950" for="owner_note">Alasan penolakan</label>
                        <textarea name="owner_note" id="owner_note" rows="3" class="input mt-2 border-red-200 focus:border-red-400 focus:ring-red-200">{{ old('owner_note') }}</textarea>
                        <x-button type="submit" variant="danger" class="mt-3">Tolak Booking</x-button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
