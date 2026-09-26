<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FinancialIntelligenceRequestGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('financial_intelligence.enabled', false), 503, 'Financial Intelligence is not activated.');
        $max = (int) config('financial_intelligence.max_upload_bytes');
        $length = (int) $request->header('Content-Length', '0');
        abort_if($length > $max || strlen($request->getContent()) > $max, 413, 'Request exceeds the Financial Intelligence upload limit.');
        $bytes = 0;
        foreach (\Illuminate\Support\Arr::flatten($request->allFiles()) as $file) {
            $bytes += $file->getSize();
        }
        abort_if($bytes > $max, 413, 'Combined upload size exceeds the limit.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
