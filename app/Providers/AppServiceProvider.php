<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('chat-send', function (Request $request): Limit {
            return Limit::perMinute(20)->by($request->user()->id)->response(
                function (Request $request, array $headers) {
                    $message = 'Batas pengiriman pesan tercapai. Silakan coba kembali dalam satu menit.';

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 429, $headers);
                    }

                    return back()->withInput($request->only(['body', 'submission_id']))
                        ->withErrors(['body' => $message])->withHeaders($headers);
                }
            );
        });
    }
}
