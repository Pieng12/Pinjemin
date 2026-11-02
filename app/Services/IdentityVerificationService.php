<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class IdentityVerificationService
{
    /** @param array<string, mixed> $data */
    public function submit(User $user, array $data, UploadedFile $document): IdentityVerification
    {
        $path = 'identity-documents/'.$user->id.'/'.Str::uuid().'.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($document->get()))) {
            throw ValidationException::withMessages(['document' => 'Dokumen gagal disimpan. Silakan coba lagi.']);
        }
        try {
            return DB::transaction(function () use ($user, $data, $document, $path): IdentityVerification {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $current = $user->currentIdentity();
                if ($current && ($current->status === 'pending' || $current->isValid())) {
                    throw ValidationException::withMessages(['identity' => 'Masih ada pengajuan yang diperiksa atau verifikasi yang berlaku. Tarik pengajuan terlebih dahulu jika perlu mengganti dokumen.']);
                }
                $verification = $user->identityVerifications()->create([
                    'document_type' => $data['document_type'], 'legal_name' => $data['legal_name'],
                    'document_number' => $data['document_number'], 'document_path' => $path,
                    'document_mime' => $document->getMimeType(),
                    'privacy_version' => config('legal.privacy_version'),
                    'document_expires_on' => $data['document_type'] === 'sim' ? $data['document_expires_on'] : null,
                    'status' => 'pending', 'consented_at' => now(), 'retain_until' => now()->addDays(30),
                ]);
                $verification->audit('submitted', $user);

                return $verification;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(IdentityVerification $verification, User $actor, string $action, ?string $note): IdentityVerification
    {
        return DB::transaction(function () use ($verification, $actor, $action, $note): IdentityVerification {
            User::query()->whereKey($verification->user_id)->lockForUpdate()->firstOrFail();
            $verification = IdentityVerification::query()->whereKey($verification->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($action === 'withdraw' ? 'withdraw' : 'update', $verification);
            if ($action !== 'verify' && ! filled($note)) {
                throw ValidationException::withMessages(['note' => 'Isi alasan atau dasar tindakan ini.']);
            }
            if ($action === 'verify' || $action === 'reject') {
                if ($verification->status !== 'pending' || ! $verification->document_path || $verification->purged_at) {
                    throw ValidationException::withMessages(['identity' => 'Pengajuan sudah diperiksa atau dokumennya sudah dihapus.']);
                }
                $until = now()->addYear();
                if ($verification->document_expires_on) {
                    $until = $until->min(Carbon::parse($verification->document_expires_on->toDateString(), Booking::RentalTimezone)->endOfDay()->utc());
                }
                if ($action === 'verify' && $until->lte(now())) {
                    throw ValidationException::withMessages(['identity' => 'Dokumen sudah kedaluwarsa. Minta pengguna mengajukan ulang.']);
                }
                $verification->fill(['status' => $action === 'verify' ? 'verified' : 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note,
                    'verified_until' => $action === 'verify' ? $until : null, 'retain_until' => $action === 'verify' ? $until : now()->addDays(30)]);
            } elseif ($action === 'revoke' || $action === 'withdraw') {
                if (! in_array($verification->status, ['verified', 'pending'], true)) {
                    throw ValidationException::withMessages(['identity' => 'Verifikasi ini sudah tidak aktif.']);
                }
                $verification->fill(['status' => $action === 'revoke' ? 'revoked' : 'withdrawn', 'review_note' => $note, 'retain_until' => now()->addDays(30)]);
            } elseif ($action === 'hold') {
                $verification->fill(['hold_reason' => $note, 'hold_review_at' => now()->addDays(30)]);
            } elseif ($action === 'release_hold') {
                $verification->fill(['hold_reason' => null, 'hold_review_at' => null]);
            } elseif ($action !== 'disclosure') {
                throw ValidationException::withMessages(['action' => 'Tindakan tidak tersedia.']);
            }
            $verification->save();
            $verification->audit($action, $actor, $note);

            return $verification;
        });
    }

    public function ensureForApproval(Booking $booking): void
    {
        if (! ($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false)) {
            return;
        }
        User::query()->whereKey($booking->renter_user_id)->lockForUpdate()->firstOrFail();
        $verification = $booking->renter->currentIdentity();
        if (! $verification?->isValid()) {
            throw ValidationException::withMessages(['identity' => 'Penyewa harus menyelesaikan verifikasi identitas melalui admin sebelum booking diterima atau ongkir ditawarkan.']);
        }
        $this->rememberIdentity($booking, $verification);
    }

    public function ensureForHandover(Booking $booking): void
    {
        if (! ($booking->fulfillment_snapshot['inspection_policy']['identity_required'] ?? false)) {
            return;
        }
        User::query()->whereKey($booking->renter_user_id)->lockForUpdate()->firstOrFail();
        $accepted = $booking->identity_verification_id ? IdentityVerification::query()->whereKey($booking->identity_verification_id)->lockForUpdate()->first() : null;
        if (! $accepted || in_array($accepted->status, ['revoked', 'withdrawn'], true)) {
            $current = $booking->renter->currentIdentity();
            if (! $current?->isValid()) {
                throw ValidationException::withMessages(['identity' => 'Verifikasi identitas dicabut atau belum terpenuhi. Hubungi admin sebelum menyerahkan barang.']);
            }
            $this->rememberIdentity($booking, $current);
        }
    }

    private function rememberIdentity(Booking $booking, IdentityVerification $verification): void
    {
        $snapshot = $booking->fulfillment_snapshot;
        $ids = array_map('intval', $snapshot['identity_verification_ids'] ?? []);
        if ($booking->identity_verification_id) {
            $ids[] = (int) $booking->identity_verification_id;
        }
        $ids[] = $verification->id;
        $snapshot['identity_verification_ids'] = array_values(array_unique($ids));
        $booking->update(['identity_verification_id' => $verification->id, 'fulfillment_snapshot' => $snapshot]);
    }

    public function hasActiveCase(IdentityVerification $verification): bool
    {
        $isCurrent = $verification->user->currentIdentity()?->id === $verification->id;

        return Booking::query()->where(function ($query) use ($verification, $isCurrent): void {
            $query->where('identity_verification_id', $verification->id)
                ->orWhereJsonContains('fulfillment_snapshot->identity_verification_ids', $verification->id);
            if ($isCurrent) {
                $query->orWhere(fn ($query) => $query->whereNull('identity_verification_id')->where('renter_user_id', $verification->user_id)->where('fulfillment_snapshot->inspection_policy->identity_required', true));
            }
        })->where(function ($query): void {
            $query->whereNotIn('status', ['completed', 'cancelled', 'rejected', 'expired'])
                ->orWhere('refund_status', 'pending')
                ->orWhereHas('conditionSessions', fn ($query) => $query->where('status', 'disputed'))
                ->orWhereHas('payout', fn ($query) => $query->where('status', 'on_hold'));
        })->exists();
    }

    public function prune(): int
    {
        $purged = 0;
        IdentityVerification::query()->whereNull('purged_at')->where('retain_until', '<=', now())->orderBy('id')->chunkById(100, function ($verifications) use (&$purged): void {
            foreach ($verifications as $candidate) {
                DB::transaction(function () use ($candidate, &$purged): void {
                    User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
                    $verification = IdentityVerification::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if ($verification->purged_at || $verification->retain_until?->isFuture()) {
                        return;
                    }
                    $activeCase = $this->hasActiveCase($verification);
                    $systemHold = 'Booking atau sengketa terkait masih aktif; perlu peninjauan admin.';
                    if ($activeCase || ($verification->hold_reason && $verification->hold_reason !== $systemHold && $verification->hold_review_at?->isFuture())) {
                        if (! $verification->hold_reason) {
                            $verification->update(['hold_reason' => $systemHold, 'hold_review_at' => now()->addDays(30)]);
                            $verification->audit('retention_review_due');
                        }

                        return;
                    }
                    if ($verification->document_path && ! Storage::disk('local')->delete($verification->document_path)) {
                        return;
                    }
                    $verification->update(['document_path' => null, 'document_number' => null, 'legal_name' => null, 'document_mime' => null, 'purged_at' => now(),
                        'status' => in_array($verification->status, ['pending', 'verified'], true) ? 'expired' : $verification->status, 'hold_reason' => null, 'hold_review_at' => null]);
                    $verification->audit('purged');
                    $purged++;
                });
            }
        });

        return $purged;
    }
}
