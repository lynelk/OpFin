<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\ProductionRepaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class LoanRepaymentController extends Controller
{
    public function __construct(private readonly ProductionRepaymentService $repayments) {}

    public function repay(Request $request, int $loan_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount_minor' => 'nullable|required_without:amount|integer|min:1',
            'amount' => 'nullable|required_without:amount_minor|integer|min:1',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $loan = Loan::query()->findOrFail($loan_id);
        if ((int) $loan->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validated = $validator->validated();
        $amountMinor = (int) ($validated['amount_minor'] ?? $validated['amount']);
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? '')));
        if ($idempotencyKey === '') {
            return ApiResponse::error('A repayment idempotency key is required.', 422, [
                'idempotency_key' => ['Provide Idempotency-Key header or idempotency_key body field.'],
            ]);
        }

        try {
            $mobileMoney = $this->repayments->initiate(
                loan: $loan,
                user: $request->user(),
                amountMinor: $amountMinor,
                idempotencyKey: $idempotencyKey,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Repayment collection request accepted.', [
            'loan_id' => $loan->id,
            'reference' => $mobileMoney->internal_reference,
            'provider' => $mobileMoney->provider,
            'status' => $mobileMoney->status,
            'amount_minor' => $mobileMoney->amount_minor,
            'currency' => $mobileMoney->currency,
            'reconciliation_status' => $mobileMoney->reconciliation_status,
        ], 202);
    }
}
