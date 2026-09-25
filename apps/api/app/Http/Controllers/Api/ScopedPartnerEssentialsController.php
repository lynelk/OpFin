<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\EssentialsPartnerEligibilityView;
use App\Services\PersonalFinancialSpaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ScopedPartnerEssentialsController extends PartnerEssentialsController
{
    public function eligibility(Request $request, int $customer): JsonResponse
    {
        // Complete the existing partner/customer-grant authorisation before
        // resolving any additional subject context for the response projection.
        $response = parent::eligibility($request, $customer);
        if ($response->getStatusCode() >= 400) {
            return $response;
        }
        $spaceId = $request->input('financial_space_id');
        if ($spaceId === null) {
            $subject = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($customer);
            $spaceId = app(PersonalFinancialSpaceService::class)->find($subject)?->id;
        }
        if (! $spaceId || (int) $spaceId <= 0) {
            return ApiResponse::error('The customer has not authorised this operation in an active Financial Space.', 403);
        }
        $body = $response->getData(true);
        $body['data'] = app(EssentialsPartnerEligibilityView::class)->forSpace((array) ($body['data'] ?? []), (int) $spaceId);
        $response->setData($body);

        return $response;
    }

    public function storeAccount(Request $request, int $customer): JsonResponse
    {
        try {
            return parent::storeAccount($request, $customer);
        } catch (RuntimeException) {
            // A competing customer operation is a retry-after-status conflict,
            // not permission to drop the mutex or disclose raw database errors.
            return ApiResponse::error('This account instruction could not complete while the customer state was changing. Refresh its status before retrying.', 409);
        }
    }
}
