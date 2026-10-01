<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EssentialsBillPlanningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class EssentialsBillPlanningController extends Controller
{
    public function __construct(private readonly EssentialsBillPlanningService $planning) {}

    public function plans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
        ]);

        try {
            $plans = $this->planning->plans(
                $request->user(),
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Bill plans loaded.', ['plans' => $plans]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'essentials_account_id' => ['required', 'integer', 'exists:essentials_accounts,id'],
            'expected_amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'frequency' => ['required', Rule::in(['once', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'annually'])],
            'next_due_date' => ['required', 'date_format:Y-m-d'],
            'necessary' => ['nullable', 'boolean'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:60'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $plan = $this->planning->createPlan($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Bill plan saved.', ['plan' => $plan], 201);
    }

    public function updatePlan(Request $request, int $plan): JsonResponse
    {
        $validated = $request->validate([
            'essentials_account_id' => ['sometimes', 'integer', 'exists:essentials_accounts,id'],
            'expected_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'frequency' => ['sometimes', Rule::in(['once', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'annually'])],
            'next_due_date' => ['sometimes', 'date_format:Y-m-d'],
            'necessary' => ['sometimes', 'boolean'],
            'reminder_days_before' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],
        ]);

        try {
            $saved = $this->planning->updatePlan($request->user(), $plan, $validated);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Bill plan updated.', ['plan' => $saved]);
    }

    public function deletePlan(Request $request, int $plan): JsonResponse
    {
        try {
            $this->planning->deletePlan($request->user(), $plan);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Bill plan removed.');
    }

    public function assess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'essentials_account_id' => ['required', 'integer', 'exists:essentials_accounts,id'],
            'bill_plan_id' => ['nullable', 'integer', 'exists:essentials_bill_plans,id'],
            'bill_amount_minor' => ['required', 'integer', 'min:1'],
            'bill_due_date' => ['required', 'date_format:Y-m-d'],
            'horizon_end' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'size:3'],
            'proposed_repayment_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $assessment = $this->planning->assess($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Affordability check completed.', [
            'assessment' => $assessment,
            'guidance' => [
                'own_money_first' => true,
                'financing_is_optional' => true,
                'message' => 'Pay from your own money where it is safe to do so. Any financing gap is assessed separately and never assumed.',
            ],
        ], 201);
    }

    public function payments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
        ]);

        try {
            $payments = $this->planning->payments(
                $request->user(),
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Own-money bill payments loaded.', ['payments' => $payments]);
    }

    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'essentials_account_id' => ['required', 'integer', 'exists:essentials_accounts,id'],
            'affordability_assessment_id' => ['required', 'integer', 'exists:essentials_affordability_assessments,id'],
            'wallet_id' => ['nullable', 'integer', 'exists:customer_wallets,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:160'],
        ]);

        try {
            $payment = $this->planning->startOwnMoneyPayment($request->user(), $validated);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Own-money bill payment recorded.', ['payment' => $payment], 201);
    }

    public function reconcile(Request $request, int $payment): JsonResponse
    {
        try {
            $saved = $this->planning->reconcileOwnMoneyPayment($request->user(), $payment);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Own-money bill payment status refreshed.', ['payment' => $saved]);
    }
}
