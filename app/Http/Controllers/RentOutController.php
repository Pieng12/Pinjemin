<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class RentOutController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('rent-out', [
            'itemsCount' => $user?->items()->count() ?? 0,
            'hasItems' => $user ? $user->items()->exists() : false,
            'ownedItems' => $user
                ? $user->items()
                    ->with(['category', 'primaryPhoto'])
                    ->latest()
                    ->orderByDesc('id')
                    ->paginate(6, ['*'], 'barang_page')
                : null,
        ]);
    }
}
