<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendant
{
    /**
     * Ensure the request comes from an authenticated attendant (domar).
     * Managers are also allowed, since they can do anything an attendant can.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! ($user->isAttendant() || $user->isManager())) {
            abort(403, 'Pristup dozvoljen samo osoblju spa centra.');
        }

        return $next($request);
    }
}
