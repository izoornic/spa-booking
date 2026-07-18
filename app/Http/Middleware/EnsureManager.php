<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    /**
     * Ensure the request comes from an authenticated manager (upravnik).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isManager()) {
            abort(403, 'Pristup dozvoljen samo upravniku.');
        }

        return $next($request);
    }
}
