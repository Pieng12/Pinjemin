<?php

namespace App\Services;

use App\BookingStatus;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingActionPresenter
{
    public function __construct(private AvailabilityService $availability, private PaymentService $payments) {}

    /** @return array{title: string, description: string, label: ?string, href: ?string, actionable: bool, deadline: ?Carbon} */
    public function describe(Booking $booking, User $user, string $viewer): array
    {
        Gate::forUser($user)->authorize(match ($viewer) {
            'owner' => 'viewAsOwner', 'renter' => 'viewAsRenter', default => 'view',
        }, $booking);
        abort_if($viewer === 'admin' && ! $user->isAdmin(), 403);
        $booking->loadMissing(['item.user.paymentMethods', 'renter.identityVerifications', 'identityVerification', 'payments', 'payout', 'conditionSessions.reports']);
        $gate = Gate::forUser($user);
        $status = $booking->effectiveStatus();
        $url = route(match ($viewer) {
            'owner' => 'incoming-bookings.show', 'admin' => 'admin.bookings.show', default => 'my-bookings.show',
        }, $booking);
        $action = fn (string $title, string $description, ?string $target = null, ?string $label = null, ?Carbon $deadline = null): array => [
            'title' => $title, 'description' => $description, 'label' => $label,
            'href' => $target ? (str_starts_with($target, '#') ? $url.$target : $target) : null,
            'actionable' => $target !== null, 'deadline' => $deadline,
        ];

        if ($status === BookingStatus::Expired) {
            $listed = $booking->item->is_active && $booking->item->status === ItemStatus::Available;

            return $action('Batas waktu sudah berakhir', $booking->payment_due_at ? 'Pembayaran tidak diterima dalam batas waktu. Pilih periode baru jika masih membutuhkan barang.' : 'Tawaran biaya pengiriman sudah kedaluwarsa. Ajukan pemesanan baru untuk melanjutkan.', $viewer === 'renter' ? ($listed ? route('items.show', $booking->item) : route('browse')) : null, $viewer === 'renter' ? ($listed ? 'Lihat barang' : 'Cari barang lain') : null);
        }
        if ($booking->refund_status === 'pending') {
            if ($viewer === 'renter' && $booking->collectsViaPlatform() && ! $booking->refund_destination) {
                return $action('Lengkapi tujuan pengembalian dana', 'Periksa nama dan nomor rekening sebelum menyimpan. Tujuan akan dikunci.', '#booking-payments', 'Isi tujuan pengembalian dana');
            }
            if ($gate->allows('recordRefund', $booking) && (! $booking->collectsViaPlatform() || ! $booking->refund_claimed_by_user_id || $booking->refund_claimed_by_user_id === $user->id)) {
                return $action('Proses pengembalian dana', $booking->collectsViaPlatform() && ! $booking->refund_destination ? 'Menunggu penyewa mengisi tujuan pengembalian dana.' : 'Periksa tujuan dan kembalikan seluruh pembayaran terverifikasi.', ! $booking->collectsViaPlatform() || $booking->refund_destination ? '#booking-payments' : null, 'Periksa pengembalian dana');
            }

            return $action('Menunggu pengembalian dana', 'Pengembalian dana sedang menunggu tujuan atau diproses oleh pihak yang berwenang. Riwayat tetap tersedia.');
        }
        if (in_array($status, [BookingStatus::Cancelled, BookingStatus::Rejected], true)) {
            return $action($status->label(), 'Tidak ada tindakan transaksi yang perlu dilakukan. Dokumen dan riwayat tetap dapat dibaca.');
        }
        if ($status === BookingStatus::Pending) {
            $identity = $booking->renter->identityVerifications->sortByDesc('id')->first();
            if (($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false) && ! $identity?->isValid()) {
                $waiting = $identity?->status === 'pending';

                return $action($waiting ? 'Identitas menunggu pemeriksaan administrator' : 'Verifikasi identitas diperlukan', 'Persyaratan pemilik harus dipenuhi sebelum pengajuan diterima atau biaya pengiriman ditawarkan.', $viewer === 'renter' && ! $waiting ? route('identity.index') : ($viewer === 'admin' && $waiting ? route('identity.show', $identity) : null), $viewer === 'admin' ? 'Periksa identitas' : 'Lengkapi verifikasi');
            }
            if ($viewer === 'owner') {
                if ($blocker = $this->approvalBlocker($booking)) {
                    return $action('Pengajuan belum dapat diterima', $blocker['message'], $blocker['href'], $blocker['label']);
                }
                $delivery = $gate->allows('quoteDelivery', $booking);

                return $action($delivery ? 'Tentukan tawaran biaya pengiriman' : 'Tinjau pengajuan sewa', $delivery ? 'Periksa alamat tujuan dan masukkan biaya pengiriman satu kali untuk pemesanan ini.' : 'Periksa periode, jumlah unit, dan persyaratan sebelum menerima.', '#booking-owner-actions', $delivery ? 'Isi tawaran biaya pengiriman' : 'Tinjau pengajuan');
            }

            return $action('Menunggu keputusan pemilik', 'Pemilik sedang meninjau periode, jumlah barang, dan pilihan penerimaan.');
        }
        if ($status === BookingStatus::AwaitingRenterConfirmation) {
            return $action($viewer === 'renter' ? 'Tinjau total dan biaya pengiriman' : 'Menunggu persetujuan penyewa', 'Total tawaran tidak berubah setelah dikirim. Persetujuan diperlukan sebelum pembayaran.', $viewer === 'renter' ? '#booking-delivery' : null, 'Tinjau tawaran', $booking->quote_expires_at);
        }
        if ($status === BookingStatus::PaymentReview) {
            return $action($gate->allows('reviewPayment', $booking) ? 'Periksa bukti pembayaran' : 'Bukti sedang diperiksa', $gate->allows('reviewPayment', $booking) ? 'Cocokkan bukti dengan mutasi rekening sebelum memverifikasi atau menolak.' : 'Tunggu keputusan '.($booking->collectsViaPlatform() ? 'administrator Pinjemin' : 'pemilik').'. Jangan mengirim pembayaran yang sama lagi.', $gate->allows('reviewPayment', $booking) ? '#booking-payments' : null, 'Periksa pembayaran');
        }
        if (in_array($status, [BookingStatus::AwaitingPayment, BookingStatus::PartiallyPaid], true)) {
            if ($gate->allows('submitPayment', $booking)) {
                return $action($status === BookingStatus::PartiallyPaid ? 'Lunasi sisa pembayaran' : 'Lanjutkan pembayaran', 'Pilih tujuan pembayaran, transfer nominal yang ditampilkan, lalu kirim bukti.', '#booking-payments', 'Buka pembayaran', $status === BookingStatus::AwaitingPayment ? $booking->payment_due_at : null);
            }
            if ($gate->allows('cashAndHandOver', $booking)) {
                if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
                    return $action('Menunggu tanggal pengambilan', 'Pelunasan tunai dan penyerahan dapat dicatat mulai '.$booking->start_date->format('d M Y').'.');
                }
                $today = now(Booking::RentalTimezone)->toDateString();
                if ($this->availability->getAvailableQuantity($booking->item, $today, max($today, $booking->end_date->toDateString()), $booking) < $booking->quantity) {
                    return $action('Penyerahan menunggu ketersediaan barang', 'Ada konflik stok. Periksa pengembalian dari penyewaan lain terlebih dahulu.');
                }

                return $action('Konfirmasi pelunasan tunai', 'Pelunasan dan penyerahan transaksi lama dicatat bersama setelah dana diterima.', '#booking-payments', 'Periksa pelunasan tunai');
            }

            return $action('Menunggu pelunasan', 'Barang belum dapat diserahkan sampai pembayaran lunas. Pelunasan tunai hanya mengikuti ketentuan transaksi lama.');
        }

        if ($booking->usesConditionInspections() && in_array($status, [BookingStatus::Approved, BookingStatus::Completed], true)) {
            if ($inspection = $this->inspectionAction($booking, $user, $viewer)) {
                return $action(...$inspection);
            }
        } elseif ($status === BookingStatus::Approved && $viewer === 'owner') {
            if (! $booking->handed_over_at && ($blocked = $this->handoverBlocker($booking))) {
                return $action('Penyerahan belum dapat dicatat', $blocked);
            }
            if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
                return $action('Menunggu tanggal penyerahan', 'Penyerahan dapat dicatat mulai '.$booking->start_date->format('d M Y').'.');
            }

            return $action($booking->handed_over_at ? 'Konfirmasi barang kembali' : 'Catat penyerahan barang', 'Pastikan peristiwa fisik sudah terjadi sebelum mengonfirmasi.', '#booking-owner-actions', 'Periksa serah terima');
        }
        if ($payout = $booking->payout) {
            if ($viewer === 'admin' && $payout->isEligible() && ($payout->status === 'pending' || ($payout->status === 'processing' && $payout->processed_by_user_id === $user->id))) {
                return $action('Proses pencairan pemilik', 'Periksa jumlah bersih yang diterima pemilik, rekening yang tercatat, dan bukti transfer. Pencairan hanya dapat diproses setelah seluruh persyaratan terpenuhi.', '#booking-payout', 'Periksa pencairan');
            }
            if ($viewer === 'owner' && $payout->status === 'paid' && ! $payout->received_at) {
                return $action('Periksa dana pencairan', 'Cocokkan transfer pada rekening Anda sebelum mengonfirmasi penerimaan.', '#booking-payout', 'Konfirmasi dana diterima');
            }
            if ($payout->status === 'on_hold') {
                return $action('Pencairan ditahan untuk pemeriksaan', $payout->hold_reason ?: 'Menunggu penyelesaian masalah oleh administrator.', $viewer === 'admin' ? '#booking-payout' : null, 'Periksa penahanan');
            }
        }

        return $action($status === BookingStatus::Completed ? 'Penyewaan selesai' : 'Pantau penyewaan', 'Lihat periode pengembalian, dokumen, dan riwayat di bawah.');
    }

    public function handoverBlocker(Booking $booking): ?string
    {
        if ($booking->handed_over_at) {
            return null;
        }
        if ($booking->remainingAmount() > 0) {
            return 'Pembayaran belum lunas. Tunggu pelunasan sebelum menyerahkan barang.';
        }
        if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
            return 'Penyerahan tersedia mulai '.$booking->start_date->format('d M Y').'.';
        }
        $booking->loadMissing(['identityVerification', 'renter.identityVerifications']);
        $identity = $booking->identityVerification;
        if (($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false)
            && (! $identity || in_array($identity->status, ['revoked', 'withdrawn'], true))
            && ! $booking->renter->identityVerifications->sortByDesc('id')->first()?->isValid()) {
            return 'Verifikasi identitas dicabut atau belum terpenuhi. Hubungi administrator sebelum penyerahan.';
        }
        $today = now(Booking::RentalTimezone)->toDateString();
        if ($this->availability->getAvailableQuantity($booking->item, max($today, $booking->start_date->toDateString()), max($today, $booking->end_date->toDateString()), $booking) < $booking->quantity) {
            return 'Ada konflik stok. Periksa pengembalian barang dari penyewaan lain sebelum penyerahan.';
        }

        return null;
    }

    /** @return array{message: string, href: ?string, label: ?string}|null */
    public function approvalBlocker(Booking $booking): ?array
    {
        $item = $booking->item;
        $block = fn (string $message, ?string $href = null, ?string $label = null): array => compact('message', 'href', 'label');
        $identity = $booking->renter->identityVerifications->sortByDesc('id')->first();
        if (($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false) && ! $identity?->isValid()) {
            return $block('Menunggu verifikasi identitas penyewa oleh administrator.');
        }
        if (! $item->is_active || $item->status !== ItemStatus::Available || ! filled($item->address)) {
            return $block('Aktifkan barang dan lengkapi lokasi pengambilan terlebih dahulu.', route('my-items.edit', $item), 'Lengkapi barang');
        }
        if ($booking->start_date->toDateString() < now(Booking::RentalTimezone)->toDateString()) {
            return $block('Tanggal mulai sudah lewat. Penyewa perlu mengajukan periode baru.');
        }
        if ($this->availability->getAvailableQuantity($item, $booking->start_date, $booking->end_date, $booking) < $booking->quantity) {
            return $block('Stok tidak mencukupi untuk seluruh periode. Pengajuan belum dapat diterima.');
        }
        try {
            $this->payments->ensureDestinationsAvailable($booking);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            if ($booking->collectsViaPlatform() && ! str_starts_with($message, 'Tambahkan rekening atau e-wallet pencairan')) {
                return $block($message);
            }

            return $block($message, route('payment-methods.index'), 'Periksa metode pencairan');
        }

        return null;
    }

    /** @return array{0: string, 1: string, 2?: ?string, 3?: ?string, 4?: ?Carbon}|null */
    private function inspectionAction(Booking $booking, User $user, string $viewer): ?array
    {
        $sessions = $booking->conditionSessions->groupBy('stage')->map(fn ($group) => $group->sortByDesc('attempt')->first());
        $unsettled = $sessions->filter(fn ($session) => ! $session->isSettled());
        if ($viewer === 'admin') {
            if ($unsettled->contains(fn ($session) => $session->status === 'disputed' || $session->resolution_outcome === 'financial_review')) {
                return ['Periksa perbedaan kondisi', 'Bukti dan alasan kedua pihak perlu ditinjau. Jangan melepas dana sebelum pemeriksaan selesai.', '#booking-inspections', 'Periksa bukti kondisi'];
            }

            return null;
        }
        if (in_array($booking->payout?->status, ['processing', 'paid', 'cancelled'], true)) {
            return null;
        }
        $stage = isset($sessions['handover']) && ! $sessions['handover']->isSettled() ? 'handover' : ($booking->handed_over_at ? 'return' : 'handover');
        $session = $sessions->get($stage);
        if ($session?->isSettled()) {
            if ($viewer === 'owner' && (($stage === 'handover' && ! $booking->handed_over_at) || ($stage === 'return' && ! $booking->returned_at))) {
                return ['Konfirmasi penerimaan fisik', 'Persetujuan kondisi tidak menggantikan pencatatan serah terima fisik.', '#booking-inspections-'.$stage, 'Catat serah terima'];
            }

            return null;
        }
        if ($session?->status === 'disputed' || $session?->resolution_outcome === 'financial_review') {
            return ['Menunggu pemeriksaan administrator', 'Ada perbedaan kondisi. Bukti tetap tersimpan dan pencairan ditahan.'];
        }
        if ($stage === 'handover') {
            if (! $booking->handed_over_at && ($blocked = $this->handoverBlocker($booking))) {
                return ['Penyerahan belum dapat dicatat', $blocked];
            }
            if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
                return ['Menunggu tanggal penyerahan', 'Pemeriksaan penyerahan tersedia pada tanggal mulai sewa.'];
            }
            $identity = $booking->identityVerification;
            if (($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false) && (! $identity || in_array($identity->status, ['revoked', 'withdrawn'], true)) && ! $booking->renter->identityVerifications->sortByDesc('id')->first()?->isValid()) {
                return ['Penyerahan menunggu identitas', 'Identitas belum memenuhi persyaratan atau telah dicabut. Hubungi administrator.'];
            }
        }
        $mine = $session?->reports->where('kind', 'initial')->firstWhere('user_id', $user->id);
        if ($session?->bothSubmitted()) {
            if ($mine?->decision === 'agree') {
                return ['Menunggu persetujuan pihak lain', 'Keputusan Anda sudah tercatat. Pengembalian dan pencairan tetap mengikuti keputusan kedua pihak.'];
            }

            return ['Tinjau bukti kedua pihak', 'Pilih kondisi sesuai atau jelaskan perbedaan yang ditemukan.', '#booking-inspections-'.$stage, 'Tinjau kondisi'];
        }
        if ($mine?->submitted_at && ! $session?->hasExpired()) {
            return ['Menunggu bukti pihak lain', 'Bukti yang Anda kirim sudah dikunci. Tunggu pihak lain atau ajukan pemeriksaan jika ada masalah.'];
        }
        $ready = $viewer === 'owner' ? $session?->owner_ready_at : $session?->renter_ready_at;
        if ($ready && $session?->status === 'waiting' && $booking->fulfillment_method === Booking::Pickup) {
            return ['Menunggu kesiapan pihak lain', 'Sesi unggah dibuka setelah kedua pihak siap.'];
        }

        return [$stage === 'return' ? 'Dokumentasikan pengembalian' : 'Dokumentasikan penyerahan', 'Periksa fungsi, aksesori, dan kondisi barang. Foto mengikuti persyaratan pemesanan.', '#booking-inspections-'.$stage, 'Buka pemeriksaan', $session?->hasExpired() ? null : $session?->expires_at];
    }
}
