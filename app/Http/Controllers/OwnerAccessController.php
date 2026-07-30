<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureOwner;
use App\Models\Vlasnik;
use Illuminate\Http\RedirectResponse;

class OwnerAccessController extends Controller
{
    /**
     * Resolve an owner from their access token and start an owner session.
     * Owners have no password — the unique token (from a QR link) is the credential.
     */
    public function __invoke(Vlasnik $vlasnik): RedirectResponse
    {
        abort_unless($vlasnik->aktivan, 404);

        session()->put([
            EnsureOwner::SESSION_KEY => $vlasnik->id,
            EnsureOwner::SESSION_TOKEN_KEY => $vlasnik->token,
        ]);

        return redirect()->route('spa.home');
    }
}
