<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProtectClubAccountingErrors
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 409 && $response->getStatusCode() !== 422) {
            // Database and internal invariant exceptions can contain SQL bindings.
            // Input-validation messages remain separate; never echo raw internals.
            $status = $response->getStatusCode();
            $response->setData(['success' => false,
                'message' => $status >= 500 ? 'The accounting service could not complete this request.'
                    : 'The accounting state changed or failed an integrity check. Refresh the record and review its status before retrying.',
                'errors' => [], 'request_reference' => (string) Str::uuid()]);
        }
        return $response;
    }
}
