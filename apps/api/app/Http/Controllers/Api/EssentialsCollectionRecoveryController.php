<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EssentialsCollectionRecovery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EssentialsCollectionRecoveryController extends Controller
{
    public function __construct(private readonly EssentialsCollectionRecovery $recovery) {}

    public function show(Request $request, int $repayment): JsonResponse
    {
        return ApiResponse::success('Your collection status.', $this->recovery->status($request->user(), $repayment))
            ->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function cancel(Request $request, int $repayment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try {
            return ApiResponse::success('Unsubmitted collection cancellation recorded.',
                $this->recovery->cancelUnsubmitted($request->user(), $repayment, $data['reason']))
                ->withHeaders(['Cache-Control' => 'private, no-store']);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error($error->getMessage(), 422);
        }
    }
}
