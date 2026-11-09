<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        return view('admin.categories.index', [
            'categories' => Category::query()
                ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
                ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                    $query->where(function (Builder $query) use ($filters): void {
                        $query->where('name', 'like', '%'.$filters['q'].'%')
                            ->orWhere('slug', 'like', '%'.$filters['q'].'%');
                    });
                })
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(10)
                ->withQueryString(),
            'totalCount' => Category::query()->count(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Category::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.categories.index')->with('status', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        $category->update([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name'], $category),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.categories.index')->with('status', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->update(['is_active' => false]);

        return back()->with('status', 'Kategori berhasil dinonaktifkan.');
    }

    private function uniqueSlug(string $name, ?Category $except = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
