<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsGoal;
use App\Models\SavingsProduct;
use App\Services\SavingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SavingsController extends Controller
{
    public function __construct(private readonly SavingsService $savings) {}

    public function products(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', config('opfin.default_country', 'UG')));

        return ApiResponse::success('Savings products loaded.', [
            'products' => $this->savings->activeProducts($country),
            'custody_notice' => 'Savings positions are held by the disclosed regulated partner. OpFin orchestrates the customer journey and does not represent these positions as an OpFin stored-value wallet.',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Savings goals loaded.', [
            'goals' => $this->savings->goalsFor($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'savings_product_id' => 'required|integer|exists:savings_products,id',
            'name' => 'required|string|min:2|max:120',
            'target_amount_minor' => 'nullable|integer|min:1',
            'target_date' => 'nullable|date|after:today',
            'scheduled_amount_minor' => 'nullable|integer|min:1',
            'contribution_frequency' => ['nullable', Rule::in(['weekly', 'fortnightly', 'monthly', 'payday'])],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $goal = $this->savings->createGoal(
                $request->user(),
                SavingsProduct::findOrFail((int) $request->input('savings_product_id')),
                $validator->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings goal created.', [
            'goal' => $this->savings->presentGoal($goal),
        ], 201);
    }

    public function show(SavingsGoal $goal, Request $request): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Savings goal loaded.', [
            'goal' => $this->savings->presentGoal($goal),
            'movements' => $goal->movements()->with('mobileMoneyTransaction')->latest()->limit(100)->get(),
        ]);
    }

    public function schedule(SavingsGoal $goal, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scheduled_amount_minor' => 'nullable|integer|min:1',
            'contribution_frequency' => ['nullable', Rule::in(['weekly', 'fortnightly', 'monthly', 'payday'])],
            'autopilot_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $goal = $this->savings->updateSchedule($goal, $request->user(), $validator->validated());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings schedule updated.', [
            'goal' => $this->savings->presentGoal($goal),
            'collection_mode' => 'reminder_manual_until_mandate_certified',
        ]);
    }

    public function pause(SavingsGoal $goal, Request $request): JsonResponse
    {
        try {
            $goal = $this->savings->pause($goal, $request->user());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings goal paused.', ['goal' => $this->savings->presentGoal($goal)]);
    }

    public function resume(SavingsGoal $goal, Request $request): JsonResponse
    {
        try {
            $goal = $this->savings->resume($goal, $request->user());
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings goal resumed.', ['goal' => $this->savings->presentGoal($goal)]);
    }

    public function contribute(SavingsGoal $goal, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount_minor' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $movement = $this->savings->contribute(
                $goal,
                $request->user(),
                (int) $request->input('amount_minor'),
                (string) $request->input('idempotency_key'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings contribution initiated.', [
            'movement' => $movement,
            'position_state' => $movement->status === 'confirmed' ? 'partner_confirmed' : 'not_yet_partner_confirmed',
        ], 202);
    }

    public function withdraw(SavingsGoal $goal, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount_minor' => 'required|integer|min:1',
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $movement = $this->savings->requestWithdrawal(
                $goal,
                $request->user(),
                (int) $request->input('amount_minor'),
                (string) $request->input('idempotency_key'),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Savings withdrawal requested.', [
            'movement' => $movement,
            'next_state' => 'partner_release_required',
        ], 202);
    }
}
