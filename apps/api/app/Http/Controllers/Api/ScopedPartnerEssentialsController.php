<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\EssentialsPartnerEligibilityView;
use App\Services\PersonalFinancialSpaceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScopedPartnerEssentialsController extends PartnerEssentialsController
{
    public function eligibility(Request $request, int $customer): JsonResponse
    {
        // The existing controller must complete partner and customer-grant
        // authorisation before any additional subject context is resolved.
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
}
