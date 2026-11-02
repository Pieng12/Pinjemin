<?php

namespace App\Models;

use Database\Factories\ConditionSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConditionSession extends Model
{
    /** @use HasFactory<ConditionSessionFactory> */
    use HasFactory;

    protected $fillable = ['booking_id', 'stage', 'attempt', 'status', 'owner_ready_at', 'renter_ready_at', 'opened_at', 'expires_at', 'closed_at', 'resolved_by', 'resolution_note', 'resolution_outcome', 'resolved_at', 'events'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ConditionReport::class)->orderBy('id');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now()) && ! $this->bothSubmitted() && in_array($this->status, ['active', 'review', 'expired'], true);
    }

    public function bothSubmitted(): bool
    {
        return $this->reports->where('kind', 'initial')->whereNotNull('submitted_at')->pluck('role')->unique()->count() === 2;
    }

    public function isSettled(): bool
    {
        return $this->resolution_outcome !== 'financial_review' && ($this->status === 'accepted' || ($this->status === 'resolved' && $this->resolution_outcome === 'confirmed'));
    }

    public function canSeeOthers(User $user): bool
    {
        return $user->isAdmin() || $this->bothSubmitted() || $this->hasExpired() || in_array($this->status, ['accepted', 'disputed', 'resolved', 'cancelled'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->hasExpired() && ! $this->bothSubmitted() ? 'expired' : $this->status) {
            'waiting' => 'Menunggu kesiapan', 'active' => 'Unggah kondisi', 'review' => 'Periksa bukti kedua pihak',
            'accepted' => 'Disepakati kedua pihak', 'disputed' => 'Perlu pemeriksaan admin', 'expired' => 'Sesi berakhir tanpa persetujuan',
            'resolved' => 'Diputuskan admin', 'cancelled' => 'Booking dibatalkan', default => $this->status,
        };
    }

    protected function casts(): array
    {
        return ['owner_ready_at' => 'datetime', 'renter_ready_at' => 'datetime', 'opened_at' => 'datetime', 'expires_at' => 'datetime', 'closed_at' => 'datetime', 'resolved_at' => 'datetime', 'events' => 'array'];
    }
}
