<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Stolets\BusinessPassportShadowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Operator-only, read-only proof of an optional merchant data connector.
 * No customer-facing outcome may depend on this shadow endpoint.
 */
final class BusinessPassportShadowController extends Controller
{
    public function __invoke(Request $request, BusinessPassportShadowService $shadow): JsonResponse
    {
        abort_unless($request->user()?->hasRole(User::ROLE_PLATFORM_ADMIN), 403);

        $references = $request->validate([
            'borrowerUserId' => ['required', 'integer', 'min:1'],
            'loanProductId' => ['required', 'integer', 'min:1'],
            'financialSpaceId' => ['required', 'integer', 'min:1'],
            'tenantId' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D'],
            'consentGrantId' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D'],
            'passportShareId' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D'],
            'featureSnapshotId' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D'],
        ]);

        try {
            return ApiResponse::success(
                'Third-party merchant indicators received for manual shadow review only.',
                $shadow->inspect($references)
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 503);
        }
    }
}
