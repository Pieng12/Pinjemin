<?php

namespace App\Services;

use App\BookingPaymentStatus;
use App\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPayout;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\PaymentPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private AvailabilityService $availability) {}

    /** @param array<int, string> $copiedPaths */
    public function beginPayment(Booking $booking, array &$copiedPaths): void
    {
        $this->ensureDestinationsAvailable($booking);
        $methods = $this->destinationMethods($booking)->limit(10)->lockForUpdate()->get();

        if ($booking->collectsViaPlatform()) {
            $destination = $booking->item->user->paymentMethods()->where('is_active', true)
                ->where('type', '!=', 'qris')->whereNotNull('account_identifier')->where('account_identifier', '!=', '')->lockForUpdate()->first();
            if (! $destination) {
                throw ValidationException::withMessages(['payment_method' => 'Rekening pencairan pemilik berubah. Muat ulang dan periksa kembali tujuan pencairan.']);
            }
            $rate = (float) ($booking->fulfillment_snapshot['commission_rate'] ?? 5);
            $commission = round((float) $booking->subtotal * $rate / 100, 2);
            $booking->payout()->create([
                'status' => 'pending', 'commission_rate' => $rate,
                'commission_amount' => $commission,
                'amount' => round((float) $booking->total_amount - $commission, 2),
                'destination_snapshot' => $destination->snapshot(),
                'events' => [],
            ]);
        }

        $snapshots = $methods->map(function ($method) use ($booking, &$copiedPaths): array {
            $qrisPath = null;
            if ($method->qris_path) {
                $extension = pathinfo($method->qris_path, PATHINFO_EXTENSION) ?: 'jpg';
                $qrisPath = 'booking-payment-destinations/'.$booking->booking_code.'/'.Str::uuid().'.'.$extension;
                if (! Storage::disk('local')->copy($method->qris_path, $qrisPath)) {
                    throw ValidationException::withMessages(['payment_method' => 'Gambar QRIS tidak dapat disalin. Perbarui metode pembayaran.']);
                }
                $copiedPaths[] = $qrisPath;
            }

            return $method->snapshot($qrisPath);
        })->values()->all();

        $booking->status = BookingStatus::AwaitingPayment;
        $booking->accepted_at = now();
        $booking->payment_due_at = now()->addHours(24)->min(
            Carbon::parse($booking->start_date->toDateString(), Booking::RentalTimezone)->endOfDay()->utc()
        );
        $booking->payment_methods_snapshot = $snapshots;
        $booking->invoice_snapshot = $this->invoiceSnapshot($booking);
        $booking->save();
    }

    public function ensureDestinationsAvailable(Booking $booking): void
    {
        $methods = $this->destinationMethods($booking)->limit(11)->get();
        if ($methods->isEmpty()) {
            $message = $booking->collectsViaPlatform()
                ? 'Rekening penerimaan Pinjemin belum tersedia. Hubungi admin sebelum menerima booking.'
                : 'Tambahkan minimal satu metode pembayaran aktif sebelum menerima booking.';
            throw ValidationException::withMessages(['payment_method' => $message]);
        }
        if ($methods->count() > 10) {
            throw ValidationException::withMessages(['payment_method' => 'Nonaktifkan metode pembayaran hingga maksimal 10 metode aktif.']);
        }

        if ($booking->collectsViaPlatform() && ! $booking->item->user->paymentMethods()
            ->where('is_active', true)->where('type', '!=', 'qris')->whereNotNull('account_identifier')->where('account_identifier', '!=', '')->exists()) {
            throw ValidationException::withMessages(['payment_method' => 'Tambahkan rekening atau e-wallet pencairan aktif pada Metode Pembayaran. QRIS saja tidak cukup untuk tujuan pencairan.']);
        }
    }

    private function destinationMethods(Booking $booking): Builder
    {
        return UserPaymentMethod::query()->where('is_active', true)
            ->when($booking->collectsViaPlatform(), fn (Builder $query) => $query->where('is_platform', true),
                fn (Builder $query) => $query->where('is_platform', false)->where('user_id', $booking->item->user_id))
            ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    /** @param array<string, mixed> $data */
    public function submit(Booking $booking, User $renter, string $proofPath, array $data): BookingPayment
    {
        $expired = false;
        $payment = DB::transaction(function () use ($booking, $renter, $proofPath, $data, &$expired): ?BookingPayment {
            $item = $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('item', $item);
            if ($booking->paymentHasExpired()) {
                $booking->update(['status' => BookingStatus::Expired, 'expired_at' => now()]);
                $booking->payout()->update(['status' => 'cancelled']);
                $expired = true;

                return null;
            }
            if (! in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::PartiallyPaid], true)) {
                throw ValidationException::withMessages(['payment' => 'Booking tidak sedang menerima bukti pembayaran.']);
            }
            if ($booking->payments()->where('status', BookingPaymentStatus::Submitted->value)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Masih ada bukti pembayaran yang sedang diperiksa.']);
            }

            $verified = (float) $booking->verifiedPayments()->sum('amount');
            $plan = $booking->payment_plan;
            if (! $plan) {
                if (! filled($data['payment_plan'] ?? null)) {
                    throw ValidationException::withMessages(['payment_plan' => 'Pilih cara pembayaran.']);
                }
                $plan = PaymentPlan::from($data['payment_plan']);
                $this->validatePlan($booking, $plan);
                $booking->payment_plan = $plan;
            }
            if ($verified > 0 && $plan === PaymentPlan::DepositCash) {
                throw ValidationException::withMessages(['payment' => 'Sisa pembayaran dipilih tunai saat pengambilan.']);
            }

            $methods = array_values($booking->payment_methods_snapshot ?? []);
            $method = $methods[(int) $data['method_index']] ?? null;
            if (! $method) {
                throw ValidationException::withMessages(['method_index' => 'Metode pembayaran tidak tersedia pada snapshot booking.']);
            }
            $amount = $verified > 0
                ? round((float) $booking->total_amount - $verified, 2)
                : ($plan === PaymentPlan::FullTransfer ? (float) $booking->total_amount : (float) $booking->deposit_amount);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['payment' => 'Tidak ada sisa pembayaran untuk dikirim.']);
            }

            $transferredAt = Carbon::parse($data['transferred_at'], Booking::RentalTimezone)->utc();
            if ($transferredAt->isFuture()) {
                throw ValidationException::withMessages(['transferred_at' => 'Waktu transfer tidak boleh berada di masa depan.']);
            }
            $payment = $booking->payments()->create([
                'submitted_by_user_id' => $renter->id,
                'kind' => $verified > 0 ? 'balance' : ($plan === PaymentPlan::FullTransfer ? 'full' : 'deposit'),
                'channel' => 'transfer',
                'amount' => $amount,
                'method_snapshot' => $method,
                'proof_path' => $proofPath,
                'transferred_at' => $transferredAt,
                'renter_note' => $data['renter_note'] ?? null,
                'status' => BookingPaymentStatus::Submitted,
                'submitted_at' => now(),
            ]);
            $booking->update(['status' => BookingStatus::PaymentReview, 'payment_plan' => $plan]);

            return $payment;
        }, attempts: 3);

        if ($expired) {
            throw ValidationException::withMessages(['payment' => 'Batas pembayaran sudah berakhir dan stok telah dibebaskan.']);
        }

        return $payment;
    }

    public function review(Booking $booking, BookingPayment $payment, User $owner, string $decision, ?string $comment, ?string $transactionReference = null): Booking
    {
        return DB::transaction(function () use ($booking, $payment, $owner, $decision, $comment, $transactionReference): Booking {
            $item = $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $payment = BookingPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('item', $item);
            Gate::forUser($owner)->authorize('reviewPayment', $booking);
            if ($payment->booking_id !== $booking->id || $payment->status !== BookingPaymentStatus::Submitted || $booking->status !== BookingStatus::PaymentReview) {
                throw ValidationException::withMessages(['payment' => 'Bukti pembayaran ini sudah diproses atau status booking berubah.']);
            }

            $transactionKey = null;
            if ($booking->collectsViaPlatform() && $decision === 'verify') {
                if (! filled($transactionReference)) {
                    throw ValidationException::withMessages(['transaction_reference' => 'Isi referensi transaksi dari mutasi rekening yang sudah diperiksa.']);
                }
                $destination = $payment->method_snapshot;
                $transactionKey = hash('sha256', mb_strtolower(trim(($destination['type'] ?? '').'|'.($destination['provider'] ?? '').'|'.($destination['account_identifier'] ?? $destination['account_name']).'|'.$transactionReference)));
                if (BookingPayment::query()->where('transaction_key', $transactionKey)->exists()) {
                    throw ValidationException::withMessages(['transaction_reference' => 'Transaksi masuk ini sudah dipakai untuk pembayaran lain.']);
                }
            }
            $payment->update([
                'status' => $decision === 'verify' ? BookingPaymentStatus::Verified : BookingPaymentStatus::Rejected,
                'reviewed_by_user_id' => $owner->id,
                'owner_comment' => $comment,
                'reviewed_at' => now(),
                'transaction_reference' => $transactionKey ? trim($transactionReference) : null,
                'transaction_key' => $transactionKey,
            ]);
            $verified = (float) $booking->verifiedPayments()->sum('amount');
            if ($decision === 'verify' && $verified >= (float) $booking->total_amount) {
                $this->finalizePayment($booking);
            } elseif ($verified > 0) {
                $booking->update(['status' => BookingStatus::PartiallyPaid]);
            } elseif ($booking->payment_due_at?->lte(now())) {
                $booking->update(['status' => BookingStatus::Expired, 'expired_at' => now()]);
                $booking->payout()->update(['status' => 'cancelled']);
            } else {
                $booking->update(['status' => BookingStatus::AwaitingPayment]);
            }

            return $booking->refresh();
        }, attempts: 3);
    }

    public function cashAndHandOver(Booking $booking, User $owner): Booking
    {
        return DB::transaction(function () use ($booking, $owner): Booking {
            $item = $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('item', $item);
            Gate::forUser($owner)->authorize('cashAndHandOver', $booking);
            if ($booking->status !== BookingStatus::PartiallyPaid || $booking->payment_plan !== PaymentPlan::DepositCash || $booking->fulfillment_method !== Booking::Pickup) {
                throw ValidationException::withMessages(['payment' => 'Booking ini tidak menggunakan pelunasan tunai saat pengambilan.']);
            }
            if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
                throw ValidationException::withMessages(['payment' => 'Pelunasan tunai hanya dapat dikonfirmasi pada atau setelah tanggal mulai.']);
            }
            if ($this->availability->getAvailableQuantity($item, now(Booking::RentalTimezone)->toDateString(), $booking->end_date->max(now(Booking::RentalTimezone))->toDateString(), $booking) < $booking->quantity) {
                throw ValidationException::withMessages(['booking' => 'Ada konflik stok atau barang terlambat kembali.']);
            }
            $remaining = $booking->remainingAmount();
            $booking->payments()->create([
                'submitted_by_user_id' => $booking->renter_user_id,
                'reviewed_by_user_id' => $owner->id,
                'kind' => 'cash_balance',
                'channel' => 'cash',
                'amount' => $remaining,
                'method_snapshot' => ['type' => 'cash', 'label' => 'Tunai saat pengambilan'],
                'transferred_at' => now(),
                'status' => BookingPaymentStatus::Verified,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);
            $this->finalizePayment($booking);
            $booking->update(['handed_over_at' => now()]);

            return $booking->refresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function recordRefund(Booking $booking, User $owner, string $proofPath, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $owner, $proofPath, $data): Booking {
            $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('recordRefund', $booking);
            if ($booking->collectsViaPlatform() && ! $booking->refund_destination) {
                throw ValidationException::withMessages(['refund' => 'Penyewa harus mengisi rekening tujuan refund terlebih dahulu.']);
            }
            if ($booking->collectsViaPlatform() && $booking->refund_claimed_by_user_id !== $owner->id) {
                throw ValidationException::withMessages(['refund' => 'Mulai proses refund terlebih dahulu. Hanya admin yang menangani refund ini dapat mencatat transfernya.']);
            }
            if ($booking->status !== BookingStatus::Cancelled || $booking->refund_status !== 'pending' || $booking->refund()->exists()) {
                throw ValidationException::withMessages(['refund' => 'Refund tidak diperlukan atau sudah dicatat.']);
            }
            $refundedAt = Carbon::parse($data['refunded_at'], Booking::RentalTimezone)->utc();
            if ($refundedAt->isFuture()) {
                throw ValidationException::withMessages(['refunded_at' => 'Waktu refund tidak boleh berada di masa depan.']);
            }
            $booking->refund()->create([
                'recorded_by_user_id' => $owner->id,
                'amount' => $booking->verifiedPayments()->sum('amount'),
                'proof_path' => $proofPath,
                'refunded_at' => $refundedAt,
                'note' => $data['note'] ?? null,
                'destination_snapshot' => $booking->refund_destination,
            ]);
            $booking->update(['refund_status' => 'refunded']);

            return $booking->refresh();
        }, attempts: 3);
    }

    public function expireAwaitingPayments(): int
    {
        $expired = Booking::query()
            ->where('status', BookingStatus::AwaitingPayment->value)
            ->where('payment_due_at', '<=', now())
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', BookingPaymentStatus::Verified->value))
            ->update(['status' => BookingStatus::Expired->value, 'expired_at' => now()]);
        BookingPayout::query()->where('status', 'pending')
            ->whereHas('booking', fn (Builder $query) => $query->where('status', BookingStatus::Expired))->update(['status' => 'cancelled']);

        return $expired;
    }

    private function validatePlan(Booking $booking, PaymentPlan $plan): void
    {
        if ($booking->collectsViaPlatform() && $plan === PaymentPlan::DepositCash) {
            throw ValidationException::withMessages(['payment_plan' => 'Pelunasan booking ini harus ditransfer ke Pinjemin.']);
        }
        if ($plan === PaymentPlan::FullTransfer) {
            return;
        }
        $policy = $booking->fulfillment_snapshot['payment_policy'] ?? [];
        if (! ($policy['allow_deposit'] ?? false) || (float) $booking->deposit_amount <= 0 || (float) $booking->deposit_amount >= (float) $booking->total_amount) {
            throw ValidationException::withMessages(['payment_plan' => 'Pembayaran DP tidak tersedia untuk booking ini.']);
        }
        if ($plan === PaymentPlan::DepositCash && (! ($policy['allow_cash_balance'] ?? false) || $booking->fulfillment_method !== Booking::Pickup)) {
            throw ValidationException::withMessages(['payment_plan' => 'Pelunasan tunai hanya tersedia untuk pengambilan langsung yang diizinkan pemilik.']);
        }
    }

    private function finalizePayment(Booking $booking): void
    {
        $booking->status = BookingStatus::Approved;
        $booking->approved_at = now();
        $booking->payment_verified_at = now();
        $booking->payment_receipt_snapshot = [
            'invoice' => $booking->invoice_snapshot,
            'verified_at' => now()->toIso8601String(),
            'payments' => $booking->verifiedPayments()->with('reviewer')->get()->map(fn (BookingPayment $payment): array => [
                'kind' => $payment->kind,
                'channel' => $payment->channel,
                'amount' => $payment->amount,
                'method' => $payment->method_snapshot,
                'transferred_at' => $payment->transferred_at?->toIso8601String(),
                'reviewed_at' => $payment->reviewed_at?->toIso8601String(),
                'reviewer' => $payment->reviewer?->name,
                'transaction_reference' => $payment->transaction_reference,
            ])->all(),
        ];
        $booking->save();
    }

    /** @return array<string, mixed> */
    private function invoiceSnapshot(Booking $booking): array
    {
        $snapshot = $booking->fulfillment_snapshot ?? [];

        return $snapshot + ['booking' => [
            'booking_code' => $booking->booking_code,
            'start_date' => $booking->start_date->toDateString(),
            'end_date' => $booking->end_date->toDateString(),
            'quantity' => $booking->quantity,
            'daily_price' => $booking->daily_price,
            'rental_days' => $booking->rental_days,
            'subtotal' => $booking->subtotal,
            'deposit_amount' => $booking->deposit_amount,
            'delivery_fee' => $booking->delivery_fee,
            'total_amount' => $booking->total_amount,
            'fulfillment_method' => $booking->fulfillment_method,
            'accepted_at' => now()->toIso8601String(),
            'approved_at' => null,
        ]];
    }
}
