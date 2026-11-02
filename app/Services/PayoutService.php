<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayoutService
{
    /** @param array<string, mixed> $data */
    public function act(Booking $booking, User $actor, array $data, ?string $proofPath = null): BookingPayout
    {
        return DB::transaction(function () use ($booking, $actor, $data, $proofPath): BookingPayout {
            $booking->item()->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            Gate::forUser($actor)->authorize(match ($action) {
                'receive' => 'receivePayout', 'report' => 'reportPaymentIssue', default => 'managePayout',
            }, $booking);
            $payout = $booking->payout()->lockForUpdate()->firstOrFail();
            $payout->setRelation('booking', $booking);
            if (in_array($payout->status, ['paid', 'cancelled'], true) && $action !== 'receive') {
                throw ValidationException::withMessages(['payout' => 'Pencairan ini sudah selesai atau dibatalkan.']);
            }
            if ($action === 'report' || $action === 'hold') {
                if (! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Jelaskan masalah atau alasan penahanan dana.']);
                }
                if ($payout->status === 'processing') {
                    throw ValidationException::withMessages(['payout' => 'Pencairan sedang diproses. Hubungi admin yang menangani sebelum mengubah status.']);
                }
                $payout->status = 'on_hold';
                $payout->hold_reason = $data['note'];
            } elseif ($action === 'release') {
                if (! $booking->inspectionsSettled()) {
                    throw ValidationException::withMessages(['payout' => 'Selesaikan pemeriksaan penyerahan dan pengembalian terlebih dahulu.']);
                }
                if ($payout->status !== 'on_hold' || ! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['payout' => 'Dana harus sedang ditahan. Isi alasan penyelesaian masalah.']);
                }
                $payout->status = 'pending';
                $payout->hold_reason = null;
            } elseif ($action === 'start') {
                if ($payout->status !== 'pending' || ! $payout->isEligible()) {
                    throw ValidationException::withMessages(['payout' => 'Pencairan memerlukan pembayaran lunas dan pengembalian barang terkonfirmasi tanpa masalah terbuka.']);
                }
                $payout->status = 'processing';
                $payout->processed_by_user_id = $actor->id;
                $payout->processing_at = now();
            } elseif ($action === 'paid') {
                if ($payout->status !== 'processing' || $payout->processed_by_user_id !== $actor->id || ! $payout->isEligible()) {
                    throw ValidationException::withMessages(['payout' => 'Hanya admin yang memulai pencairan ini dapat mencatat transfernya.']);
                }
                $paidAt = Carbon::parse($data['paid_at'], Booking::RentalTimezone)->utc();
                if ($paidAt->isFuture() || $paidAt->lt($booking->completed_at->copy()->startOfMinute())) {
                    throw ValidationException::withMessages(['paid_at' => 'Waktu transfer harus setelah penyewaan selesai dan tidak di masa depan.']);
                }
                $destination = $payout->destination_snapshot;
                $key = hash('sha256', mb_strtolower(trim(($destination['type'] ?? '').'|'.($destination['provider'] ?? '').'|'.($destination['account_identifier'] ?? '').'|'.$data['transaction_reference'])));
                if (BookingPayout::query()->where('transaction_key', $key)->exists()) {
                    throw ValidationException::withMessages(['transaction_reference' => 'Referensi transfer ini sudah digunakan untuk pencairan lain.']);
                }
                if (! $proofPath) {
                    throw ValidationException::withMessages(['proof' => 'Unggah bukti transfer pencairan.']);
                }
                $payout->fill([
                    'status' => 'paid', 'paid_at' => $paidAt, 'proof_path' => $proofPath,
                    'transaction_reference' => trim($data['transaction_reference']), 'transaction_key' => $key,
                    'note' => $data['note'] ?? null,
                ]);
            } elseif ($action === 'receive') {
                if ($payout->status !== 'paid' || $payout->received_at) {
                    throw ValidationException::withMessages(['payout' => 'Pencairan belum ditransfer atau sudah dikonfirmasi.']);
                }
                $payout->received_at = now();
            } else {
                throw ValidationException::withMessages(['action' => 'Tindakan pencairan tidak tersedia.']);
            }
            $payout->events = [...($payout->events ?? []), [
                'action' => $action, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'note' => $data['note'] ?? null, 'at' => now()->toIso8601String(),
            ]];
            $payout->save();

            return $payout;
        }, attempts: 3);
    }
}
