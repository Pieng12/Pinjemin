<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }

        $roles = [User::RoleAdmin => 'Admin', User::RoleUser => 'Pengguna'];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::in(array_keys($roles))],
        ]);

        return view('admin.users.index', [
            'users' => User::query()
                ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->where('role', $filters['role']))
                ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                    $query->where(function (Builder $query) use ($filters): void {
                        $query->where('name', 'like', '%'.$filters['q'].'%')
                            ->orWhere('email', 'like', '%'.$filters['q'].'%');
                    });
                })
                ->latest()
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString(),
            'totalCount' => User::query()->count(),
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }
}
