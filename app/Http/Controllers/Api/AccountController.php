<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletion) {}

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        $result = $this->deletion->deleteOrRequest(
            $request->user(),
            (string) $data['password'],
            $request,
        );

        $status = $result['deletion_status'] === 'completed' ? 200 : 202;

        return ApiResponse::success($result['message'], $result, $status);
    }
}
