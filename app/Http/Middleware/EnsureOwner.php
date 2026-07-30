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
     * Session key holding the token the session was started with. Compared against
     * the owner's current token so a regenerated QR link ends existing sessions.
     */
    public const SESSION_TOKEN_KEY = 'spa_owner_token';

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
            ->whereKey($request->session()->get(self::SESSION_KEY))
            ->first();

        if ($owner === null || ! hash_equals($owner->token, (string) $request->session()->get(self::SESSION_TOKEN_KEY))) {
            $request->session()->forget([self::SESSION_KEY, self::SESSION_TOKEN_KEY]);

            abort(403, 'Pristup dozvoljen samo preko ličnog linka.');
        }

        $request->attributes->set('owner', $owner);
        view()->share('owner', $owner);

        return $next($request);
    }
}
