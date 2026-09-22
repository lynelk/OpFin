<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\EarlySettlementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class EarlySettlementController extends Controller
{
    public function quote(Loan $loan, Request $request, EarlySettlementService $settlements): JsonResponse
    {
        $validated = $request->validate(['as_of_date' => 'nullable|date']);
        try {
            $quote = $settlements->quote(
                $loan,
                $request->user(),
                isset($validated['as_of_date']) ? Carbon::parse($validated['as_of_date'])->endOfDay() : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Early-settlement quote generated.', ['quote' => $quote], 201);
    }

    public function settle(int $quote, Request $request, EarlySettlementService $settlements): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => 'nullable|string|max:255',
            'wallet_id' => 'nullable|integer|exists:customer_wallets,id',
        ]);
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? '')));
        if ($idempotencyKey === '') {
            return ApiResponse::error('A settlement idempotency key is required.', 422);
        }

        $record = \Illuminate\Support\Facades\DB::table('early_settlement_quotes')->where('id', $quote)->first();
        if (! $record) {
            return ApiResponse::error('Early-settlement quote not found.', 404);
        }

        try {
            $money = $settlements->initiate(
                $record,
                $request->user(),
                $idempotencyKey,
                $request->integer('wallet_id') ?: null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Early-settlement collection requested.', [
            'reference' => $money->internal_reference,
            'status' => $money->status,
            'amount_minor' => $money->amount_minor,
            'currency' => $money->currency,
        ], 202);
    }
}
