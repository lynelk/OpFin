<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use App\Services\PaymentReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProductionReconciliationController extends Controller
{
    public function __construct(private readonly PaymentReconciliationService $reconciliation) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|max:64',
            'business_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $run = $this->reconciliation->createRun(
                provider: $validator->validated()['provider'],
                businessDate: $validator->validated()['business_date'],
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Reconciliation run created.', [
            'run' => $run,
            'item_count' => $run->items->count(),
        ], 201);
    }

    public function ingest(ReconciliationRun $run, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'records' => 'required|array|min:1|max:500',
            'records.*.provider_reference' => 'required|string|max:255',
            'records.*.internal_reference' => 'nullable|string|max:255',
            'records.*.amount_minor' => 'required|integer|min:0',
            'records.*.currency' => 'required|string|size:3',
            'records.*.direction' => ['required', Rule::in(['collection', 'disbursement', 'reversal'])],
            'records.*.provider_status' => 'required|string|max:64',
            'records.*.occurred_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $records = $this->reconciliation->ingestProviderRecords(
                run: $run,
                records: $validator->validated()['records'],
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Provider statement records ingested.', [
            'run' => $run->fresh(),
            'records' => $records,
        ], 201);
    }

    public function complete(ReconciliationRun $run, Request $request): JsonResponse
    {
        try {
            $completed = $this->reconciliation->completeRun($run, $request->user());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Reconciliation run completed.', [
            'run' => $completed,
        ]);
    }
}
