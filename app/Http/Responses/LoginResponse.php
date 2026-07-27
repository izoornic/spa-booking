<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send staff to the surface that matches their role after login.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        $target = $request->user()?->homeUrl() ?? url((string) config('fortify.home', '/dashboard'));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $target])
            : redirect()->intended($target);
    }
}
