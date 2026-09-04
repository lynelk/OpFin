<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', config('opfin.default_country', 'UG')));
        $countries = config('opfin.countries', []);

        if (! array_key_exists($country, $countries)) {
            return ApiResponse::error('Unsupported country policy.', 404);
        }

        return ApiResponse::success('OpFin capability registry loaded.', [
            'country' => $country,
            'country_policy' => $countries[$country],
            'capabilities' => config('opfin.capabilities', []),
        ]);
    }
}
