<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an account must replace a one-time password, its token can only change the password,
 * read its profile or sign out. Applied to every API route group; anonymous requests pass through.
 */
class RequireCurrentPassword
{
    private const ALLOWED = ['api/account/password', 'api/logout', 'api/profile'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->bearerToken() !== null ? $request->user('sanctum') : null;
        if ($user !== null && $user->password_change_required === true && ! in_array($request->path(), self::ALLOWED, true)) {
            return ApiResponse::error('Choose a new password before continuing.', 403, [], ['code' => 'password_change_required']);
        }

        return $next($request);
    }
}
