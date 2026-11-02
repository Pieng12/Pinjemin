<?php

namespace App\Http\Controllers;

use App\ItemCondition;
use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'in:'.implode(',', ItemCondition::values())],
            'city' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', ...($request->filled('min_price') ? ['gte:min_price'] : [])],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
        ], ['max_price.gte' => 'Harga maksimum harus sama atau lebih besar dari harga minimum.']);

        $items = Item::query()
            ->marketplace()
            ->with(['category', 'user', 'primaryPhoto'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['category'] ?? null, function ($query, string $category): void {
                $query->whereHas('category', fn ($query) => $query->where('slug', $category));
            })
            ->when($filters['condition'] ?? null, fn ($query, string $condition) => $query->where('condition', $condition))
            ->when($filters['city'] ?? null, fn ($query, string $city) => $query->where('city', $city))
            ->when(isset($filters['min_price']), fn ($query) => $query->where('daily_price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('daily_price', '<=', $filters['max_price']));

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $items->orderBy('daily_price')->orderByDesc('id'),
            'price_desc' => $items->orderByDesc('daily_price')->orderByDesc('id'),
            default => $items->latest()->orderByDesc('id'),
        };

        return view('items.index', [
            'items' => $items->paginate(12)->withQueryString(),
            'categories' => Category::active()->orderBy('name')->get(),
            'conditions' => ItemCondition::options(),
            'cities' => Item::query()->marketplace()->select('city')->distinct()->orderBy('city')->pluck('city'),
            'filters' => $filters,
        ]);
    }

    public function show(Item $item): View
    {
        $item->load(['category', 'user', 'photos']);

        if (! $item->is_active || $item->status !== ItemStatus::Available) {
            abort_unless(auth()->user()?->can('view', $item), 404);
        }

        return view('items.show', [
            'item' => $item,
        ]);
    }
}
