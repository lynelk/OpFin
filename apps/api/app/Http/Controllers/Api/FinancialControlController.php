<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\ReconciliationItem;
use App\Models\User;
use App\Services\FinancialPolicyService;
use App\Services\ReconciliationExceptionResolutionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FinancialControlController extends Controller
{
    public function requestLoanOverride(
        Loan $loan,
        Request $request,
        FinancialPolicyService $policies,
    ): JsonResponse {
        $validated = $request->validate([
            'control_code' => 'required|in:credit.default_interest_cap',
            'reason' => 'required|string|min:20|max:2000',
            'expires_in_minutes' => 'nullable|integer|min:5|max:10080',
            'evidence' => 'nullable|array',
        ]);

        $expiresAt = isset($validated['expires_in_minutes'])
            ? now()->addMinutes((int) $validated['expires_in_minutes'])
            : now()->addHours(24);

        $override = $policies->requestOverride(
            $validated['control_code'],
            Loan::class,
            (int) $loan->id,
            $request->user(),
            $validated['reason'],
            $validated['evidence'] ?? [],
            $expiresAt,
        );

        return ApiResponse::success('Financial-control override submitted for independent approval.', [
            'override' => $override,
        ], 201);
    }

    public function requestReconciliationWriteOff(
        ReconciliationItem $item,
        Request $request,
        ReconciliationExceptionResolutionService $resolutions,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'required|string|min:20|max:2000',
            'evidence_hash' => 'required|string|size:64|regex:/^[a-fA-F0-9]{64}$/',
            'evidence_reference' => 'nullable|string|max:255',
            'evidence' => 'nullable|array',
        ]);

        try {
            $override = $resolutions->requestWriteOff(
                $item,
                $request->user(),
                $validated['reason'],
                $validated['evidence_hash'],
                $validated['evidence_reference'] ?? null,
                $validated['evidence'] ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Reconciliation write-off submitted for independent approval.', [
            'override' => $override,
        ], 201);
    }

    public function applyReconciliationWriteOff(
        ReconciliationItem $item,
        Request $request,
        ReconciliationExceptionResolutionService $resolutions,
    ): JsonResponse {
        $validated = $request->validate([
            'override_id' => 'required|integer|exists:financial_control_overrides,id',
        ]);

        try {
            $resolved = $resolutions->applyWriteOff(
                $item,
                (int) $validated['override_id'],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Reconciliation exception written off under maker-checker control.', [
            'item' => $resolved,
        ]);
    }

    public function approve(
        int $override,
        Request $request,
        FinancialPolicyService $policies,
    ): JsonResponse {
        $validated = $request->validate([
            'evidence' => 'nullable|array',
        ]);

        try {
            $approved = $policies->approveOverride($override, $request->user(), $validated['evidence'] ?? []);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Financial-control override approved under maker-checker.', [
            'override' => $approved,
        ]);
    }
}
