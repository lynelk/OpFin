<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return ApiResponse::success('Service is alive.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
        ]);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error('Service is not ready.', 503, [
                'status' => 'degraded',
                'service' => 'opfin-backend',
                'database' => 'unavailable',
            ]);
        }

        return ApiResponse::success('Service is ready.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
            'database' => 'ready',
            'queue' => (string) config('queue.default'),
        ]);
    }

    public function show(): JsonResponse
    {
        return $this->ready();
    }
}
