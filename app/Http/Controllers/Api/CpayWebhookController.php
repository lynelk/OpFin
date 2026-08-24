<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileMoney\MobileMoneyService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;

class CpayWebhookController extends Controller
{
    public function __invoke(Request $request, MobileMoneyService $mobileMoney): JsonResponse
    {
        $rawBody = $request->getContent();

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ApiResponse::error('Invalid CPay callback payload.', 400);
        }

        if (!is_array($payload)) {
            return ApiResponse::error('Invalid CPay callback payload.', 400);
        }

        try {
            $transaction = $mobileMoney->processWebhook(
                providerName: 'cpay',
                payload: $payload,
                headers: $request->headers->all(),
                rawBody: $rawBody,
            );
        } catch (InvalidArgumentException $exception) {
            $status = $exception->getMessage() === 'Invalid mobile money webhook signature.' ? 401 : 409;

            return ApiResponse::error('CPay callback rejected.', $status);
        } catch (ModelNotFoundException) {
            return ApiResponse::error('CPay transaction reference not found.', 404);
        }

        return ApiResponse::success('CPay callback accepted.', [
            'reference' => $transaction->internal_reference,
            'status' => $transaction->status,
        ]);
    }
}
