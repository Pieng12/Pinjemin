<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('features.marketplace_enabled')) {
            return redirect()
                ->route('home')
                ->with('status', 'Halaman tersebut tidak tersedia. Kenali Pinjemin dan temukan informasi terbaru di beranda.');
        }

        return $next($request);
    }
}
