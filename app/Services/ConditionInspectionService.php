<?php

namespace App\Services;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ConditionInspectionService
{
    public function __construct(private AvailabilityService $availability, private IdentityVerificationService $identities) {}

    /** @param array<string, mixed> $data
     * @param  array<int, array{id: string, path: string, mime: string, hash: string}>  $photos
     */
    public function act(Booking $booking, User $actor, string $stage, array $data, array $photos = []): Booking
    {
        $deleteAfterCommit = [];
        $result = DB::transaction(function () use ($booking, $actor, $stage, $data, $photos, &$deleteAfterCommit): Booking {
            $deleteAfterCommit = [];
            $item = Item::query()->whereKey($booking->item_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('item', $item);
            $action = $data['action'];
            Gate::forUser($actor)->authorize($action === 'resolve' ? 'resolveInspection' : 'participateInspection', $booking);
            if (! $booking->usesConditionInspections() || ! in_array($stage, ['handover', 'return'], true)) {
                throw ValidationException::withMessages(['inspection' => 'Booking ini tidak memakai alur pemeriksaan kondisi baru.']);
            }
            if (! in_array($booking->status, [BookingStatus::Approved, BookingStatus::Completed], true) || $booking->remainingAmount() > 0) {
                throw ValidationException::withMessages(['inspection' => 'Pemeriksaan penyerahan memerlukan pembayaran lunas dan booking aktif.']);
            }
            if ($stage === 'return' && ! $booking->handed_over_at && $action !== 'resolve') {
                throw ValidationException::withMessages(['inspection' => 'Penyerahan fisik belum dicatat.']);
            }
            if ($stage === 'handover' && ! $booking->handed_over_at) {
                $this->ensureCanHandOver($booking);
            }
            $session = $booking->conditionSessions()->where('stage', $stage)->reorder()->orderByDesc('attempt')->lockForUpdate()->first();
            if (isset($data['session_id']) && (! $session || (int) $data['session_id'] !== $session->id)) {
                throw ValidationException::withMessages(['inspection' => 'Sesi sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
            }
            if ($session && ($session->hasExpired() || $session->status === 'expired') && $action === 'ready') {
                $session->update(['status' => 'expired', 'closed_at' => $session->closed_at ?? now()]);
                $session = $this->newSession($booking, $stage, $session->attempt + 1);
            }
            $session ??= $this->newSession($booking, $stage, 1);
            $session->setRelation('reports', $session->reports()->lockForUpdate()->get());
            $payout = $booking->payout()->lockForUpdate()->first();
            if ($payout && in_array($payout->status, ['processing', 'paid', 'cancelled'], true)) {
                throw ValidationException::withMessages(['inspection' => 'Pencairan sedang diproses atau sudah selesai. Hubungi admin untuk bantuan.']);
            }
            $role = $actor->id === $item->user_id ? 'owner' : 'renter';
            $report = $session->reports->where('kind', 'initial')->where('user_id', $actor->id)->first();
            if ($action === 'ready') {
                if (! in_array($session->status, ['waiting', 'active'], true) || $session->hasExpired()) {
                    throw ValidationException::withMessages(['inspection' => 'Sesi ini sudah dikirim atau memerlukan penyelesaian admin.']);
                }
                $field = $role.'_ready_at';
                if (! $session->{$field}) {
                    $session->{$field} = now();
                }
                if ($booking->fulfillment_method === Booking::Delivery || ($session->owner_ready_at && $session->renter_ready_at)) {
                    $session->opened_at ??= now();
                    $session->expires_at = $booking->fulfillment_method === Booking::Pickup ? ($session->expires_at ?? now()->addMinutes(30)) : null;
                    $session->status = 'active';
                }
                $session->save();
            } elseif (in_array($action, ['save', 'send', 'supplement'], true)) {
                if ($action !== 'supplement' && ($session->status !== 'active' || $session->hasExpired())) {
                    throw ValidationException::withMessages(['inspection' => 'Sesi unggah belum dimulai atau sudah berakhir. Nyatakan siap bersama atau buka sesi baru.']);
                }
                if ($action !== 'supplement' && $report?->submitted_at) {
                    throw ValidationException::withMessages(['inspection' => 'Bukti awal sudah dikunci. Gunakan tambahan bukti.']);
                }
                if ($action === 'supplement' && ($data['request_admin_review'] ?? false) && $session->resolved_at) {
                    throw ValidationException::withMessages(['inspection' => 'Pemeriksaan sudah diputuskan admin. Hubungi admin untuk peninjauan lebih lanjut.']);
                }
                $sender = $stage === 'handover' ? 'owner' : 'renter';
                if ($action !== 'supplement' && $booking->fulfillment_method === Booking::Delivery && $role !== $sender && ! $session->reports->where('kind', 'initial')->where('role', $sender)->whereNotNull('submitted_at')->count()) {
                    throw ValidationException::withMessages(['inspection' => 'Pihak pengirim harus mengirim dokumentasi pengiriman terlebih dahulu.']);
                }
                $oldPhotos = $report?->photos ?? [];
                $replacing = $action !== 'supplement' && ($data['replace_photos'] ?? false);
                $allPhotos = array_merge($action === 'supplement' || $replacing ? [] : $oldPhotos, $photos);
                if (count($allPhotos) > 5) {
                    throw ValidationException::withMessages(['photos' => 'Maksimal 5 foto untuk bukti awal. Ganti foto draf atau gunakan tambahan bukti setelah terkirim.']);
                }
                if ($action === 'send' && ($booking->fulfillment_snapshot['inspection_policy']['photos_required'] ?? false) && count($allPhotos) < 1) {
                    throw ValidationException::withMessages(['photos' => 'Pemilik mewajibkan minimal satu foto kondisi dari masing-masing pihak.']);
                }
                if ($action === 'send' && ! filled($data['function_state'] ?? null)) {
                    throw ValidationException::withMessages(['function_state' => 'Catat hasil pemeriksaan fungsi barang.']);
                }
                if ($action === 'supplement' && ! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Jelaskan tambahan bukti ini.']);
                }
                if ($replacing) {
                    $deleteAfterCommit = array_column($oldPhotos, 'path');
                }
                $values = [
                    'user_id' => $actor->id, 'role' => $role, 'kind' => $action === 'supplement' ? 'supplement' : 'initial',
                    'initial_key' => $action === 'supplement' ? null : $session->id.'-'.$actor->id,
                    'function_state' => $data['function_state'] ?? null, 'accessories' => $data['accessories'] ?? null,
                    'serial_number' => $data['serial_number'] ?? null,
                    'condition_note' => $action === 'supplement' ? $data['note'] : ($data['condition_note'] ?? null),
                    'photos' => $allPhotos, 'submitted_at' => $action === 'save' ? null : now(),
                ];
                if ($report && $action !== 'supplement') {
                    $report->update($values);
                } else {
                    $session->reports()->create($values);
                }
                if ($action === 'send' && $booking->fulfillment_method === Booking::Delivery && $role === 'owner') {
                    if (! ($data['physical_confirmation'] ?? false)) {
                        throw ValidationException::withMessages(['physical_confirmation' => 'Konfirmasikan barang sudah diserahkan ke kurir atau diterima kembali secara fisik.']);
                    }
                    $this->physical($booking, $stage);
                }
                $session->load('reports');
                if ($action === 'supplement' && ($data['request_admin_review'] ?? false)) {
                    $session->update(['status' => 'disputed']);
                }
                if ($session->bothSubmitted() && $session->status === 'active') {
                    $session->update(['status' => 'review']);
                }
            } elseif ($action === 'agree' || $action === 'dispute') {
                if (! $session->bothSubmitted() || $session->hasExpired() || ! $report?->submitted_at || $session->resolved_at) {
                    throw ValidationException::withMessages(['inspection' => 'Bukti kedua pihak belum lengkap atau sesi sudah ditangani admin.']);
                }
                if ($action === 'dispute' && ! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Jelaskan perbedaan kondisi yang ditemukan.']);
                }
                if ($report->decision !== null && ! ($report->decision === 'agree' && $action === 'dispute')) {
                    throw ValidationException::withMessages(['inspection' => 'Keputusan sudah dicatat. Perubahan keberatan memerlukan admin.']);
                }
                $report->update(['decision' => $action, 'decision_note' => $data['note'] ?? null, 'decided_at' => now()]);
                $session->load('reports');
                if ($session->reports->where('kind', 'initial')->where('decision', 'dispute')->count()) {
                    $session->status = 'disputed';
                    if ($booking->status === BookingStatus::Completed) {
                        $booking->update(['status' => BookingStatus::Approved, 'completed_at' => null]);
                    }
                } elseif ($session->reports->where('kind', 'initial')->where('decision', 'agree')->count() === 2) {
                    $session->status = 'accepted';
                    $session->closed_at = now();
                    if ($stage === 'handover') {
                        $this->physical($booking, $stage);
                    }
                }
                $session->save();
            } elseif ($action === 'physical') {
                if ($role !== 'owner' || ! ($data['physical_confirmation'] ?? false)) {
                    throw ValidationException::withMessages(['physical_confirmation' => 'Hanya pemilik dapat mengonfirmasi penyerahan atau penerimaan fisik.']);
                }
                $this->physical($booking, $stage);
            } elseif ($action === 'resolve') {
                if (! filled($data['note'] ?? null)) {
                    throw ValidationException::withMessages(['note' => 'Jelaskan dasar keputusan admin.']);
                }
                $proof = isset($data['evidence_report_id']) ? ConditionReport::query()->whereKey($data['evidence_report_id'])->whereNotNull('submitted_at')
                    ->whereHas('session', fn ($query) => $query->where('booking_id', $booking->id))->first() : null;
                if (! $photos && (! $proof || ! $proof->photos)) {
                    throw ValidationException::withMessages(['photos' => 'Unggah bukti atau pilih dokumentasi foto dari booking ini sebagai dasar keputusan.']);
                }
                $session->reports()->create(['user_id' => $actor->id, 'role' => 'admin', 'kind' => 'admin', 'condition_note' => $data['note'],
                    'photos' => $photos, 'submitted_at' => now(), 'accessories' => $proof ? 'Referensi bukti #'.$proof->id : null]);
                $session->fill(['status' => ($data['outcome'] ?? null) === 'confirmed' ? 'resolved' : 'disputed',
                    'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => $data['note'],
                    'resolution_outcome' => $data['outcome'], 'closed_at' => now()])->save();
                if (($data['physical_event'] ?? 'none') !== 'none') {
                    $eventStage = $data['physical_event'] === 'returned' ? 'return' : 'handover';
                    $this->physical($booking, $eventStage);
                }
            }
            $session->events = [...($session->events ?? []), ['actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'at' => now()->toIso8601String(), 'note' => $data['note'] ?? null]];
            $session->save();
            if ($session->status === 'disputed' && $payout) {
                $payout->update(['status' => 'on_hold', 'hold_reason' => 'Perbedaan kondisi barang memerlukan pemeriksaan admin.']);
            }
            if ($session->status === 'disputed' && $booking->status === BookingStatus::Completed) {
                $booking->update(['status' => BookingStatus::Approved, 'completed_at' => null]);
            }
            $booking->unsetRelation('conditionSessions');
            if ($booking->inspectionsSettled()) {
                $booking->update(['status' => BookingStatus::Completed, 'completed_at' => $booking->completed_at ?? now()]);
            }

            return $booking->refresh();
        }, attempts: 3);
        Storage::disk('local')->delete($deleteAfterCommit);

        return $result;
    }

    private function newSession(Booking $booking, string $stage, int $attempt): ConditionSession
    {
        return $booking->conditionSessions()->create(['stage' => $stage, 'attempt' => $attempt, 'status' => 'waiting', 'events' => []]);
    }

    private function ensureCanHandOver(Booking $booking): void
    {
        $this->identities->ensureForHandover($booking);
        if ($booking->start_date->toDateString() > now(Booking::RentalTimezone)->toDateString()) {
            throw ValidationException::withMessages(['inspection' => 'Penyerahan hanya dapat dicatat pada atau setelah tanggal mulai.']);
        }
        if ($this->availability->getAvailableQuantity($booking->item, now(Booking::RentalTimezone)->toDateString(), max($booking->end_date->toDateString(), now(Booking::RentalTimezone)->toDateString()), $booking) < $booking->quantity) {
            throw ValidationException::withMessages(['inspection' => 'Ada konflik stok. Selesaikan pengembalian barang sebelum penyerahan.']);
        }
    }

    private function physical(Booking $booking, string $stage): void
    {
        if ($stage === 'handover') {
            if (! $booking->handed_over_at) {
                $this->ensureCanHandOver($booking);
                $booking->update(['handed_over_at' => now()]);
            }
        } else {
            if (! $booking->handed_over_at) {
                throw ValidationException::withMessages(['inspection' => 'Penyerahan fisik belum dicatat.']);
            }
            if (! $booking->returned_at) {
                $booking->update(['returned_at' => now()]);
            }
        }
    }
}
