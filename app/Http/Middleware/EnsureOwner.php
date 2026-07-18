<?php

namespace App\Http\Middleware;

use App\Models\Vlasnik;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    /**
     * Session key holding the authenticated owner's id.
     */
    public const SESSION_KEY = 'spa_owner_id';

    /**
     * Ensure the request belongs to an owner session (token-based, no password).
     * The resolved Vlasnik is shared to views as `$owner`.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $owner = Vlasnik::query()
            ->where('aktivan', true)
            ->find($request->session()->get(self::SESSION_KEY));

        if ($owner === null) {
            $request->session()->forget(self::SESSION_KEY);

            abort(403, 'Pristup dozvoljen samo preko ličnog linka.');
        }

        $request->attributes->set('owner', $owner);
        view()->share('owner', $owner);

        return $next($request);
    }
}
