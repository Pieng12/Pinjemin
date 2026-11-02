<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConditionInspectionRequest;
use App\Models\Booking;
use App\Models\ConditionReport;
use App\Models\ConditionSession;
use App\Services\ConditionInspectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConditionInspectionController extends Controller
{
    public function store(ConditionInspectionRequest $request, Booking $booking, string $stage, ConditionInspectionService $service): RedirectResponse
    {
        $photos = [];
        try {
            if (in_array($request->input('action'), ['save', 'send', 'supplement', 'resolve'], true)) {
                foreach ($request->file('photos', []) as $file) {
                    $id = (string) Str::uuid();
                    $path = $file->storeAs('condition-evidence/'.$booking->booking_code, $id.'.'.$file->guessExtension(), 'local');
                    if ($path === false) {
                        throw ValidationException::withMessages(['photos' => 'Foto gagal disimpan. Tidak ada bukti yang dicatat sebagai berhasil.']);
                    }
                    $photos[] = ['id' => $id, 'path' => $path, 'mime' => $file->getMimeType(), 'hash' => hash_file('sha256', $file->getRealPath())];
                }
            }
            $service->act($booking, $request->user(), $stage, $request->validated(), $photos);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_column($photos, 'path'));
            throw $exception;
        }

        return back()->with('status', 'Pemeriksaan kondisi berhasil diperbarui.');
    }

    public function photo(Booking $booking, ConditionReport $report, string $photo): StreamedResponse
    {
        Gate::authorize('view', $booking);
        abort_unless($report->session->booking_id === $booking->id, 404);
        abort_unless($report->user_id === request()->user()->id || ($report->submitted_at && $report->session->canSeeOthers(request()->user())), 403);
        $file = collect($report->photos ?? [])->firstWhere('id', $photo);
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->response($file['path'], null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function document(Booking $booking, ConditionSession $session): Response
    {
        Gate::authorize('view', $booking);
        abort_unless($session->booking_id === $booking->id, 404);
        $session->load('reports.user');
        $reports = $session->reports->filter(fn ($report) => $report->submitted_at && ($report->user_id === request()->user()->id || $session->canSeeOthers(request()->user())));

        return Pdf::loadView('bookings.condition-record', ['booking' => $booking, 'session' => $session, 'reports' => $reports])
            ->setPaper('a4')->download('BA-'.$booking->booking_code.'-'.$session->stage.'-'.$session->attempt.'.pdf');
    }

    public function status(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);
        $state = $booking->conditionSessions()->with('reports')->get()->map(fn ($session) => [
            'id' => $session->id, 'stage' => $session->stage, 'status' => $session->statusLabel(), 'expires_at' => $session->expires_at?->toIso8601String(),
            'updated_at' => $session->updated_at->toIso8601String(), 'reports' => $session->reports->whereNotNull('submitted_at')->map(fn ($report) => [$report->id, $report->decision, $report->decided_at?->toIso8601String()])->values(),
        ]);

        return response()->json(['fingerprint' => hash('sha256', $state->toJson().$booking->status->value), 'sessions' => $state])->header('Cache-Control', 'private, no-store');
    }
}
