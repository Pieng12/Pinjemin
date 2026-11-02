<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Services\AvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MyItemController extends Controller
{
    public function index(): View
    {
        $items = request()->user()
            ->items()
            ->with(['category', 'primaryPhoto'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('my-items.index', [
            'items' => $items,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Item::class);

        return view('my-items.create', [
            'categories' => Category::active()->orderBy('name')->get(),
            'item' => null,
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $storedPaths = [];
        try {
            $item = DB::transaction(function () use ($request, &$storedPaths): Item {
                $item = $request->user()->items()->create($this->itemData($request->validated()));
                $this->storePhotos($item, $request->file('photos', []), $storedPaths);

                return $item;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return redirect()->route('my-items.show', $item)->with('status', 'Barang berhasil ditambahkan.');
    }

    public function show(Item $item): View
    {
        Gate::authorize('view', $item);

        $item->load(['category', 'photos', 'user']);

        return view('my-items.show', [
            'item' => $item,
        ]);
    }

    public function edit(Item $item): View
    {
        Gate::authorize('update', $item);

        $item->load(['photos', 'category']);

        return view('my-items.edit', [
            'categories' => Category::query()
                ->where('is_active', true)
                ->orWhereKey($item->category_id)
                ->orderBy('name')
                ->get(),
            'item' => $item,
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item, AvailabilityService $availability): RedirectResponse
    {
        $storedPaths = [];
        try {
            $item = DB::transaction(function () use ($request, $item, $availability, &$storedPaths): Item {
                $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                if ($request->integer('quantity') < $item->quantity && $request->integer('quantity') < $availability->minimumStockRequired($item)) {
                    throw ValidationException::withMessages(['quantity' => 'Jumlah unit tidak boleh kurang dari stok yang sedang direservasi.']);
                }
                $item->update($this->itemData($request->validated(), $item));
                $this->storePhotos($item, $request->file('photos', []), $storedPaths);

                return $item;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }

        return redirect()->route('my-items.show', $item)->with('status', 'Barang berhasil diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        if ($item->bookings()->exists()) {
            return back()->withErrors([
                'item' => 'Barang yang sudah memiliki booking tidak dapat dihapus. Nonaktifkan barang untuk menutup booking baru.',
            ]);
        }

        $paths = $item->photos()->pluck('path')->all();

        DB::transaction(function () use ($item): void {
            $item->delete();
        });

        Storage::disk('public')->delete($paths);

        return redirect()->route('my-items.index')->with('status', 'Barang berhasil dihapus.');
    }

    public function toggleStatus(Item $item): RedirectResponse
    {
        Gate::authorize('manage', $item);

        $willActivate = ! ($item->is_active && $item->status === ItemStatus::Available);

        if ($willActivate && $item->photos()->count() === 0) {
            return back()->withErrors(['photos' => 'Barang aktif harus memiliki minimal satu foto.']);
        }

        DB::transaction(function () use ($item, $willActivate): void {
            $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($willActivate && ! filled($item->address)) {
                throw ValidationException::withMessages(['address' => 'Lengkapi lokasi pengambilan sebelum mengaktifkan barang.']);
            }
            $item->update([
                'is_active' => $willActivate,
                'status' => $willActivate ? ItemStatus::Available : ItemStatus::Inactive,
            ]);
        });

        return back()->with('status', $willActivate ? 'Barang berhasil diaktifkan.' : 'Barang berhasil dinonaktifkan.');
    }

    /** @param  array<string, mixed>  $validated */
    private function itemData(array $validated, ?Item $item = null): array
    {
        $isActive = (bool) ($validated['is_active'] ?? false);
        $data = Arr::only($validated, [
            'category_id',
            'name',
            'description',
            'condition',
            'daily_price',
            'deposit_amount',
            'quantity',
            'city',
            'address',
            'rules',
        ]);

        $data['slug'] = $this->uniqueSlug($validated['name'], $item);
        $data['delivery_enabled'] = (bool) ($validated['delivery_enabled'] ?? false);
        $data['free_delivery'] = $data['delivery_enabled'] && (bool) ($validated['free_delivery'] ?? false);
        $data['allow_deposit_payment'] = (bool) ($validated['allow_deposit_payment'] ?? false);
        $data['requires_condition_photos'] = (bool) ($validated['requires_condition_photos'] ?? false);
        $data['requires_identity_verification'] = (bool) ($validated['requires_identity_verification'] ?? false);
        $data['allow_cash_balance'] = $data['allow_deposit_payment'] && (bool) ($validated['allow_cash_balance'] ?? false);
        $data['is_active'] = $isActive;
        $data['status'] = $isActive ? ItemStatus::Available : ItemStatus::Inactive;

        return $data;
    }

    public function updateRequirements(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);
        $data = $request->validate(['requires_condition_photos' => ['required', 'boolean'], 'requires_identity_verification' => ['required', 'boolean']]);
        DB::transaction(function () use ($item, $data): void {
            $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $item->update($data);
        });

        return back()->with('status', 'Persyaratan diperbarui untuk booking berikutnya. Booking yang sudah diajukan tidak berubah.');
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, string>  $storedPaths
     */
    private function storePhotos(Item $item, array $photos, array &$storedPaths): void
    {
        if ($photos === []) {
            return;
        }

        $sortOrder = (int) $item->photos()->max('sort_order');
        $hasPrimary = $item->photos()->where('is_primary', true)->exists();
        if ($item->photos()->count() + count($photos) > 5) {
            throw ValidationException::withMessages(['photos' => 'Total foto barang maksimal 5.']);
        }

        foreach ($photos as $photo) {
            $sortOrder++;
            $path = $photo->store('items/'.now()->format('Y/m'), 'public');
            if ($path === false) {
                throw ValidationException::withMessages(['photos' => 'Foto barang gagal disimpan. Coba lagi.']);
            }
            $storedPaths[] = $path;

            $item->photos()->create([
                'path' => $path,
                'is_primary' => ! $hasPrimary,
                'sort_order' => $sortOrder,
            ]);

            $hasPrimary = true;
        }
    }

    private function uniqueSlug(string $name, ?Item $except = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Item::query()
            ->where('slug', $slug)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
