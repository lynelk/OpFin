<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cito\CitoOperationsSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CitoOperationsController extends Controller
{
    public function __invoke(Request $request, CitoOperationsSnapshotService $operations): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole([
            User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS,
        ]), 403);

        return response()->json(['data' => $operations->snapshot()]);
    }
}
