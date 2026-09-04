<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSensitiveAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Request $request, Closure $next, string $event): Response
    {
        $response = $next($request);

        if ($request->user() && $response->getStatusCode() < 400) {
            $this->auditLogger->record(
                event: $event,
                actor: $request->user(),
                subject: $request->user(),
                request: $request
            );
        }

        return $response;
    }
}
