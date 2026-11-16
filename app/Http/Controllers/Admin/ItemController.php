<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
        ]);

        return view('admin.items.index', [
            'items' => Item::query()
                ->with(['user', 'category', 'primaryPhoto'])
                ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->marketplace())
                ->when(($filters['status'] ?? null) === 'inactive', function (Builder $query): void {
                    $query->where(fn (Builder $query) => $query->where('is_active', false)->orWhere('status', ItemStatus::Inactive));
                })
                ->when(filled($filters['category'] ?? null), fn (Builder $query) => $query->whereHas('category', fn (Builder $query) => $query->where('slug', $filters['category'])))
                ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                    $query->where(function (Builder $query) use ($filters): void {
                        $query->where('name', 'like', '%'.$filters['q'].'%')
                            ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', '%'.$filters['q'].'%'));
                    });
                })
                ->latest()
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'totalCount' => Item::query()->count(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => $filters,
        ]);
    }

    public function show(Item $item): View
    {
        $item->load(['user', 'category', 'photos']);

        return view('admin.items.show', [
            'item' => $item,
        ]);
    }

    public function deactivate(Item $item): RedirectResponse
    {
        Gate::authorize('moderate', $item);

        $item->update([
            'is_active' => false,
            'status' => ItemStatus::Inactive,
        ]);

        return back()->with('status', 'Barang berhasil dinonaktifkan oleh administrator.');
    }
}
