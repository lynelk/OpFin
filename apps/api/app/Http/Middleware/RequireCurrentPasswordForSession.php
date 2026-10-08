<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The legacy back office cannot change a one-time password, so a session for an account that
 * must change it is ended and sent to the Web app, where the change is made.
 */
class RequireCurrentPasswordForSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->password_change_required === true) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $web = rtrim((string) config('services.opfin.web_url'), '/');

            return $web !== ''
                ? redirect()->away($web.'/login?message='.rawurlencode('Sign in here to choose a new password.'))
                : redirect('/login')->with('error', 'Choose a new password in the OpFin Web app before using the back office.');
        }

        return $next($request);
    }
}
