<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success('Service is healthy.', [
            'status' => 'ok',
            'service' => 'opfin-backend',
        ]);
    }
}
