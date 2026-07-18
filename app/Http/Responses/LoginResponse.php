<?php

namespace App\Http\Responses;

use App\Models\User;
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
        $target = $this->redirectTarget($request->user());

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $target])
            : redirect()->intended($target);
    }

    /**
     * Attendants land on their mobile desk; everyone else on the web dashboard.
     */
    private function redirectTarget(?User $user): string
    {
        if ($user?->isAttendant()) {
            return route('domar.home');
        }

        return config('fortify.home', '/dashboard');
    }
}
