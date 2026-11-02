<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIdentityVerificationRequest;
use App\Models\IdentityVerification;
use App\Services\IdentityVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdentityVerificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('identity.index', ['verifications' => $request->user()->identityVerifications()->get(), 'current' => $request->user()->currentIdentity()]);
    }

    public function store(StoreIdentityVerificationRequest $request, IdentityVerificationService $service): RedirectResponse
    {
        $service->submit($request->user(), $request->validated(), $request->file('document'));

        return redirect()->route('identity.index')->with('status', 'Identitas dikirim secara privat untuk diperiksa admin.');
    }

    public function adminIndex(Request $request): View
    {
        Gate::authorize('viewAny', IdentityVerification::class);
        $filters = $request->validate(['status' => ['nullable', Rule::in(['pending', 'verified', 'rejected', 'revoked', 'expired', 'retention'])]]);
        $status = $filters['status'] ?? 'pending';
        $query = IdentityVerification::query()->with('user');
        if ($status === 'expired') {
            $query->where(fn ($query) => $query->where('status', 'expired')->orWhere(fn ($query) => $query->where('status', 'verified')->where('verified_until', '<=', now())));
        } elseif ($status === 'retention') {
            $query->whereNotNull('hold_reason')->whereNull('purged_at');
        } else {
            $query->where('status', $status);
        }

        return view('admin.identity.index', ['verifications' => $query->latest('id')->paginate(15)->withQueryString(), 'status' => $status]);
    }

    public function show(IdentityVerification $verification): View
    {
        Gate::authorize('view', $verification);
        if (request()->user()->isAdmin()) {
            $verification->audit('details_viewed', request()->user());
        }

        return view('identity.show', ['verification' => $verification->load('user'),
            'events' => DB::table('identity_verification_events')->where('identity_verification_id', $verification->id)->orderBy('id')->get()]);
    }

    public function image(IdentityVerification $verification): Response
    {
        Gate::authorize('view', $verification);
        abort_unless($verification->document_path && Storage::disk('local')->exists($verification->document_path), 404);
        if (request()->user()->isAdmin()) {
            $verification->audit('document_viewed', request()->user());
        }

        return response(Crypt::decryptString(Storage::disk('local')->get($verification->document_path)), 200, [
            'Content-Type' => $verification->document_mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline; filename="identitas"',
        ]);
    }

    public function review(Request $request, IdentityVerification $verification, IdentityVerificationService $service): RedirectResponse
    {
        Gate::authorize('update', $verification);
        $data = $request->validate(['action' => ['required', Rule::in(['verify', 'reject', 'revoke', 'hold', 'release_hold', 'disclosure'])],
            'note' => [Rule::requiredIf($request->input('action') !== 'verify'), 'nullable', 'string', 'max:2000']]);
        $service->review($verification, $request->user(), $data['action'], $data['note'] ?? null);

        return back()->with('status', 'Keputusan verifikasi identitas berhasil dicatat.');
    }

    public function withdraw(Request $request, IdentityVerification $verification, IdentityVerificationService $service): RedirectResponse
    {
        Gate::authorize('withdraw', $verification);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $service->review($verification, $request->user(), 'withdraw', $data['note']);

        return redirect()->route('identity.index')->with('status', 'Pengajuan ditarik. Data dihapus sesuai masa retensi dan kebutuhan kasus aktif.');
    }
}
