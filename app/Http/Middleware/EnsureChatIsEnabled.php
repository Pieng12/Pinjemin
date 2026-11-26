<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('features.chat_enabled'), 404);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
