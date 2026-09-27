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

    public function deletionReadiness(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Account deletion readiness loaded.',
            $this->deletion->readiness($request->user()),
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['nullable', 'required_without:password', 'string'],
            'password' => ['nullable', 'required_without:pin', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        $credential = (string) ($data['pin'] ?? $data['password']);
        $result = $this->deletion->deleteOrRequest(
            $request->user(),
            $credential,
            $request,
        );

        if ($result['deletion_status'] === 'blocked_obligations') {
            return ApiResponse::error(
                $result['message'],
                409,
                [],
                ['data' => $result],
            );
        }

        return ApiResponse::success($result['message'], $result);
    }

    public function deleteData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['nullable', 'required_without:password', 'string'],
            'password' => ['nullable', 'required_without:pin', 'string'],
            'confirmation' => ['required', 'in:DELETE_DATA'],
            'data_categories' => ['required', 'array', 'min:1', 'max:20'],
            'data_categories.*' => ['required', 'string', 'max:80'],
        ]);

        $credential = (string) ($data['pin'] ?? $data['password']);
        $result = $this->deletion->deleteSelectedData(
            $request->user(),
            $credential,
            $data['data_categories'],
            $request,
        );

        return ApiResponse::success($result['message'], $result);
    }
}
