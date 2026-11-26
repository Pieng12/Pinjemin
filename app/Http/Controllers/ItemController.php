<?php

namespace App\Http\Controllers;

use App\ItemCondition;
use App\ItemStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Item;
use App\Services\AvailabilityService;
use App\Services\RentalPriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request, AvailabilityService $availability): View
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'in:'.implode(',', ItemCondition::values())],
            'city' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', ...($request->filled('min_price') ? ['gte:min_price'] : [])],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
            ...$this->periodRules(),
        ], [
            'max_price.gte' => 'Harga maksimum harus sama atau lebih besar dari harga minimum.',
            'start_date.required_with' => 'Isi tanggal mulai untuk menyaring ketersediaan.',
            'start_date.date_format' => 'Masukkan tanggal mulai yang valid.',
            'start_date.after_or_equal' => 'Tanggal mulai paling awal adalah hari ini (WIB).',
            'end_date.required_with' => 'Isi tanggal pengembalian untuk menyaring ketersediaan.',
            'end_date.date_format' => 'Masukkan tanggal pengembalian yang valid.',
            'end_date.after_or_equal' => 'Tanggal pengembalian harus sama dengan atau setelah tanggal mulai.',
            'quantity.integer' => 'Jumlah unit harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah unit minimal satu.',
        ]);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('browse'));
        }
        $filters = $validator->validated();
        $period = $this->periodSelection($filters);

        $items = Item::query()
            ->marketplace()
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

        if ($period !== []) {
            $availability->filterAvailableItems($items, $period['start_date'], $period['end_date'], $period['quantity']);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $items->orderBy('daily_price')->orderByDesc('id'),
            'price_desc' => $items->orderByDesc('daily_price')->orderByDesc('id'),
            default => $items->latest()->orderByDesc('id'),
        };

        $results = $items->with(['category', 'user', 'primaryPhoto'])->paginate(12)->appends($filters);
        $categories = Category::active()
            ->withExists(['items as has_marketplace_items' => fn ($query) => $query->marketplace()])
            ->orderBy('name')->get();

        return view('items.index', [
            'items' => $results,
            'catalogHasItems' => $results->total() > 0 || Item::query()->marketplace()->exists(),
            'categories' => $categories,
            'quickCategories' => $categories->where('has_marketplace_items', true),
            'conditions' => ItemCondition::options(),
            'cities' => Item::query()->marketplace()->select('city')->distinct()->orderBy('city')->pluck('city'),
            'filters' => $filters,
            'period' => $period,
        ]);
    }

    public function show(Request $request, Item $item, AvailabilityService $availability, RentalPriceCalculator $calculator): View
    {
        $item->load(['category', 'user', 'photos']);

        if (! $item->is_active || $item->status !== ItemStatus::Available) {
            abort_unless(auth()->user()?->can('view', $item), 404);
        }

        $period = $this->periodSelection($request->query());
        $selection = array_replace(['start_date' => '', 'end_date' => '', 'quantity' => 1], $period,
            array_filter(Arr::only($request->session()->getOldInput(), array_keys($this->periodRules())), 'is_scalar'));
        $validSelection = $this->periodSelection($selection);
        $estimate = $validSelection === [] ? null : $calculator->calculate($item, $validSelection['start_date'], $validSelection['end_date'], $validSelection['quantity']);
        $availableUnits = $validSelection === [] || $request->user()?->id === $item->user_id
            ? null : $availability->getAvailableQuantity($item, $validSelection['start_date'], $validSelection['end_date']);

        return view('items.show', [
            'item' => $item,
            'period' => $period,
            'selection' => $selection,
            'estimate' => $estimate,
            'availableUnits' => $availableUnits,
        ]);
    }

    public function continueRental(Request $request, Item $item): RedirectResponse
    {
        return to_route('items.show', ['item' => $item, ...$this->periodSelection($request->query())]);
    }

    /** @return array<string, list<string>> */
    private function periodRules(): array
    {
        return [
            'start_date' => ['nullable', 'required_with:end_date,quantity', 'date_format:Y-m-d', 'after_or_equal:'.now(Booking::RentalTimezone)->toDateString()],
            'end_date' => ['nullable', 'required_with:start_date,quantity', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{start_date: string, end_date: string, quantity: int}|array{}
     */
    private function periodSelection(array $input): array
    {
        $validator = Validator::make($input, $this->periodRules());
        if (! isset($input['start_date'], $input['end_date']) || $validator->fails()) {
            return [];
        }

        $dates = $validator->validated();

        return ['start_date' => $dates['start_date'], 'end_date' => $dates['end_date'], 'quantity' => (int) ($dates['quantity'] ?? 1)];
    }
}
