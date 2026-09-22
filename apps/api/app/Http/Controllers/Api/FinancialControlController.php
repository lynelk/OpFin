<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\User;
use App\Services\FinancialPolicyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function approve(
        int $override,
        Request $request,
        FinancialPolicyService $policies,
    ): JsonResponse {
        $validated = $request->validate([
            'evidence' => 'nullable|array',
        ]);

        $approved = $policies->approveOverride($override, $request->user(), $validated['evidence'] ?? []);

        return ApiResponse::success('Financial-control override approved under maker-checker.', [
            'override' => $approved,
        ]);
    }
}
