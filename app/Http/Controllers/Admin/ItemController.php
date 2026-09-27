<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\ItemStatus;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(): View
    {
        return view('admin.items.index', [
            'items' => Item::query()
                ->with(['user', 'category', 'primaryPhoto'])
                ->latest()
                ->orderByDesc('id')
                ->paginate(15),
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

        return back()->with('status', 'Barang berhasil dinonaktifkan oleh admin.');
    }
}
