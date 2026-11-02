<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ConditionSession;
use App\Services\IdentityVerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('rentals:maintain-evidence {--prune : Hapus identitas sesuai retensi}')]
#[Description('Tutup sesi pemeriksaan kedaluwarsa tanpa persetujuan otomatis dan kelola retensi identitas')]
class MaintainRentalEvidence extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(IdentityVerificationService $identities): int
    {
        $closed = 0;
        ConditionSession::query()->whereIn('status', ['active', 'review'])->where('expires_at', '<=', now())->chunkById(100, function ($sessions) use (&$closed): void {
            foreach ($sessions as $candidate) {
                DB::transaction(function () use ($candidate, &$closed): void {
                    $booking = $candidate->booking;
                    $booking->item()->lockForUpdate()->firstOrFail();
                    Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
                    $session = ConditionSession::query()->with('reports')->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if ($session->hasExpired() && $session->status !== 'expired') {
                        $session->update(['status' => 'expired', 'closed_at' => now()]);
                        $closed++;
                    }
                });
            }
        });
        $purged = $this->option('prune') ? $identities->prune() : 0;
        $this->info($closed.' sesi ditutup; '.$purged.' dokumen identitas dihapus.');

        return self::SUCCESS;
    }
}
