<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\PaymentMethodType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', UserPaymentMethod::class);

        return view('payment-methods.index', [
            'methods' => $this->methods(request()->user())->get(),
            'activeMethodsCount' => $this->methods(request()->user())->where('is_active', true)->count(),
            'platform' => request()->routeIs('admin.payment-methods.*'),
            'types' => PaymentMethodType::options(),
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->data($request);
        $data['is_platform'] = $request->routeIs('admin.payment-methods.*');
        $data['user_id'] = $user->id;
        $path = $request->hasFile('qris_image') ? $request->file('qris_image')->store('payment-methods/'.$user->id, 'local') : null;
        if ($path === false) {
            throw ValidationException::withMessages(['qris_image' => 'Gambar QRIS gagal disimpan. Coba lagi.'])->errorBag($request->formKey());
        }
        $data['qris_path'] = $path;
        try {
            DB::transaction(function () use ($user, $data, $request): void {
                $this->lockMethods($user);
                if ($data['is_active'] && $this->methods($user)->where('is_active', true)->count() >= 10) {
                    throw ValidationException::withMessages(['type' => 'Maksimal 10 metode pembayaran aktif per akun.'])->errorBag($request->formKey());
                }
                if ($data['is_primary']) {
                    $this->methods($user)->update(['is_primary' => false]);
                }
                UserPaymentMethod::create($data);
                $this->syncPrimary($user);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'Metode pembayaran berhasil ditambahkan.');
    }

    public function update(StorePaymentMethodRequest $request, UserPaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->data($request);
        $oldPath = $paymentMethod->qris_path;
        $newPath = $request->hasFile('qris_image') ? $request->file('qris_image')->store('payment-methods/'.$request->user()->id, 'local') : null;
        if ($newPath === false) {
            throw ValidationException::withMessages(['qris_image' => 'Gambar QRIS gagal disimpan. Coba lagi.'])->errorBag($request->formKey());
        }
        if ($newPath) {
            $data['qris_path'] = $newPath;
        } elseif ($data['type'] !== PaymentMethodType::Qris->value) {
            $data['qris_path'] = null;
        }
        try {
            DB::transaction(function () use ($request, $paymentMethod, $data, &$oldPath): void {
                $user = $request->user();
                $this->lockMethods($user);
                $paymentMethod->refresh();
                $oldPath = $paymentMethod->qris_path;
                if ($data['is_active'] && ! $paymentMethod->is_active && $this->methods($user)->where('is_active', true)->count() >= 10) {
                    throw ValidationException::withMessages(['type' => 'Maksimal 10 metode pembayaran aktif per akun.'])->errorBag($request->formKey());
                }
                if ($data['is_primary']) {
                    $this->methods($user)->whereKeyNot($paymentMethod->id)->update(['is_primary' => false]);
                }
                $paymentMethod->update($data);
                $this->syncPrimary($user);
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if (array_key_exists('qris_path', $data) && $oldPath && $oldPath !== $data['qris_path']) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', 'Metode pembayaran berhasil diperbarui.');
    }

    public function destroy(UserPaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize('delete', $paymentMethod);
        abort_unless((bool) $paymentMethod->is_platform === request()->routeIs('admin.payment-methods.*'), 403);
        $path = null;
        DB::transaction(function () use ($paymentMethod, &$path): void {
            $user = request()->user();
            $this->lockMethods($user);
            $paymentMethod->refresh();
            $path = $paymentMethod->qris_path;
            $paymentMethod->delete();
            $this->syncPrimary($user);
        });
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('status', 'Metode pembayaran dihapus. Snapshot booking lama tetap tersimpan.');
    }

    /** @return array<string, mixed> */
    private function data(StorePaymentMethodRequest $request): array
    {
        $data = Arr::only($request->validated(), ['type', 'provider', 'account_name', 'account_identifier', 'instructions', 'sort_order']);
        $data['provider'] = in_array($data['type'], ['dana', 'gopay', 'ovo'], true)
            ? PaymentMethodType::from($data['type'])->label() : ($data['provider'] ?? null);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_primary'] = $request->boolean('is_primary');
        if ($data['is_primary']) {
            $data['is_active'] = true;
        }

        return $data;
    }

    private function syncPrimary(User $user): void
    {
        $primary = $this->methods($user)->where('is_active', true)->first();
        $this->methods($user)->where('is_primary', true)->update(['is_primary' => false]);
        $primary?->update(['is_primary' => true]);
    }

    private function methods(User $user): Builder
    {
        $platform = request()->routeIs('admin.payment-methods.*');
        abort_if($platform && ! $user->isAdmin(), 403);

        return UserPaymentMethod::query()->where('is_platform', $platform)
            ->when(! $platform, fn (Builder $query) => $query->where('user_id', $user->id))
            ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    private function lockMethods(User $user): void
    {
        $query = User::query();
        if (request()->routeIs('admin.payment-methods.*')) {
            $query->where('role', User::RoleAdmin)->orderBy('id');
        } else {
            $query->whereKey($user->id);
        }
        $query->lockForUpdate()->firstOrFail();
    }
}
