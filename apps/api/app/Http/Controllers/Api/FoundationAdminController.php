<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoundationAdminController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        return ApiResponse::success('Role check passed.', [
            'role' => $request->user()->role,
        ]);
    }
}
