<?php

namespace App\Models;

use Database\Factories\IdentityVerificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class IdentityVerification extends Model
{
    /** @use HasFactory<IdentityVerificationFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'document_type', 'legal_name', 'document_number', 'document_path', 'document_mime', 'document_expires_on', 'status', 'consented_at', 'reviewed_at', 'reviewed_by', 'verified_until', 'retain_until', 'hold_review_at', 'hold_reason', 'purged_at', 'review_note'];

    protected $hidden = ['document_number', 'legal_name', 'document_path'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'verified' && $this->verified_until?->isFuture();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Menunggu Pemeriksaan', 'verified' => $this->isValid() ? 'Terverifikasi' : 'Kedaluwarsa',
            'rejected' => 'Perlu Perbaikan', 'withdrawn' => 'Ditarik', 'revoked' => 'Dicabut', default => 'Kedaluwarsa',
        };
    }

    public function audit(string $action, ?User $actor = null, ?string $note = null): void
    {
        DB::table('identity_verification_events')->insert(['identity_verification_id' => $this->id, 'actor_id' => $actor?->id, 'action' => $action, 'note' => $note, 'created_at' => now()]);
    }

    protected function casts(): array
    {
        return ['legal_name' => 'encrypted', 'document_number' => 'encrypted', 'document_expires_on' => 'date', 'consented_at' => 'datetime', 'reviewed_at' => 'datetime', 'verified_until' => 'datetime', 'retain_until' => 'datetime', 'hold_review_at' => 'datetime', 'purged_at' => 'datetime'];
    }
}
