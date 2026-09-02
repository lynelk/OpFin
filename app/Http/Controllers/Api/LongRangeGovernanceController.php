<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LongRangeGovernanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LongRangeGovernanceController extends Controller
{
    public function __construct(private readonly LongRangeGovernanceService $governance) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success('Long-range governance dashboard loaded.', $this->governance->dashboard());
    }

    public function linkedAccount(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:verified,rejected', 'evidence' => 'nullable|string|max:1000']);

        return ApiResponse::success('Linked account review completed.', ['linked_account' => $this->governance->verifyLinkedAccount($request->user(), $id, $data)]);
    }

    public function assetFinance(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,declined',
            'reason' => 'required|string|max:1000',
            'approved_amount_minor' => 'nullable|integer|min:0',
            'pricing' => 'nullable|array',
        ]);

        return ApiResponse::success('Asset-finance decision recorded.', ['asset_finance_request' => $this->governance->decideAsset($request->user(), $id, $data)]);
    }

    public function community(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:verified,rejected', 'evidence' => 'nullable|string|max:1000']);

        return ApiResponse::success('Community membership review completed.', ['membership' => $this->governance->verifyCommunity($request->user(), $id, $data)]);
    }

    public function participatory(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'lender_of_record' => 'required_if:status,approved|nullable|string|max:160',
            'loss_allocation' => 'required_if:status,approved|nullable|string|max:500',
            'fees' => 'required_if:status,approved|nullable|string|max:500',
            'custody' => 'required_if:status,approved|nullable|string|max:500',
            'guarantee' => 'nullable|string|max:500',
            'expected_return_percent' => 'required_if:status,approved|nullable|numeric|min:0|max:100',
            'risk_grade' => 'required_if:status,approved|nullable|string|max:20',
            'risk_summary' => 'nullable|string|max:500',
            'borrower_summary' => 'required_if:status,approved|nullable|string|max:500',
            'repayment_frequency' => 'required_if:status,approved|nullable|string|max:80',
        ]);

        return ApiResponse::success('Peer-lending listing review completed.', ['listing' => $this->governance->approveParticipatory($request->user(), $id, $data)]);
    }

    public function capital(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected']);

        return ApiResponse::success('Capital mandate review completed.', ['capital_mandate' => $this->governance->approveCapital($request->user(), $id, $data)]);
    }

    public function partner(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected']);

        return ApiResponse::success('Partner due-diligence decision recorded.', ['partner' => $this->governance->approvePartner($request->user(), $id, $data)]);
    }

    public function referralReward(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reward_minor' => 'required|integer|min:0|max:10000000']);

        return ApiResponse::success('Referral reward posted through the controlled reward ledger.', ['referral' => $this->governance->approveReferralReward($request->user(), $id, $data['reward_minor'])]);
    }
}
