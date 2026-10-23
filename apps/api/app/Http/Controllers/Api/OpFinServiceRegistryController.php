<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpFinServiceRegistryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Discovery only: callers MUST use authoritative policy, membership,
        // entitlement and regulatory checks before exposing or mutating data.
        return response()->json([
            'success' => true,
            'data' => [
                'registry' => config('opfin_service_registry'),
                'discovery_only' => true,
                'activation_authoritative' => false,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
