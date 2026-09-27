<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\UserPaymentMethod;
use App\PaymentMethodType;
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
            'methods' => request()->user()->paymentMethods()->get(),
            'activeMethodsCount' => request()->user()->paymentMethods()->where('is_active', true)->count(),
            'types' => PaymentMethodType::options(),
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $user = $request->user();
        $willBeActive = $request->boolean('is_active') || $request->boolean('is_primary');
        if ($willBeActive && $user->paymentMethods()->where('is_active', true)->count() >= 10) {
            throw ValidationException::withMessages(['type' => 'Maksimal 10 metode pembayaran aktif per akun.']);
        }
        $data = $this->data($request);
        $path = $request->hasFile('qris_image') ? $request->file('qris_image')->store('payment-methods/'.$user->id, 'local') : null;
        $data['qris_path'] = $path;
        try {
            DB::transaction(function () use ($user, $data): void {
                if ($data['is_primary']) {
                    $user->paymentMethods()->update(['is_primary' => false]);
                }
                $method = $user->paymentMethods()->create($data);
                if ($method->is_active && ! $user->paymentMethods()->where('is_primary', true)->exists()) {
                    $method->update(['is_primary' => true]);
                }
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
        $willBeActive = $request->boolean('is_active') || $request->boolean('is_primary');
        if ($willBeActive && ! $paymentMethod->is_active && $request->user()->paymentMethods()->where('is_active', true)->count() >= 10) {
            throw ValidationException::withMessages(['type' => 'Maksimal 10 metode pembayaran aktif per akun.']);
        }
        $data = $this->data($request);
        $oldPath = $paymentMethod->qris_path;
        $newPath = $request->hasFile('qris_image') ? $request->file('qris_image')->store('payment-methods/'.$request->user()->id, 'local') : null;
        if ($newPath) {
            $data['qris_path'] = $newPath;
        } elseif ($data['type'] !== PaymentMethodType::Qris->value) {
            $data['qris_path'] = null;
        }
        try {
            DB::transaction(function () use ($request, $paymentMethod, $data): void {
                if ($data['is_primary']) {
                    $request->user()->paymentMethods()->whereKeyNot($paymentMethod->id)->update(['is_primary' => false]);
                }
                $paymentMethod->update($data);
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
        if ($paymentMethod->is_primary && $paymentMethod->user->paymentMethods()->whereKeyNot($paymentMethod->id)->exists()) {
            throw ValidationException::withMessages(['payment_method' => 'Pilih metode utama lain sebelum menghapus metode ini.']);
        }
        $paymentMethod->delete();

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
}
