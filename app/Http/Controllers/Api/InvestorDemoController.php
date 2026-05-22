<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemoConsent;
use App\Models\DemoLoanDecision;
use App\Models\DemoLoanOffer;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\InvestorDemoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvestorDemoController extends Controller
{
    public function __construct(private readonly InvestorDemoService $demoService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success('Investor demo dashboard loaded.', [
            'mock_integrations' => ['affordability', 'decisioning', 'mobile_money_disbursement'],
            'profile' => $user,
            'kyc' => [
                'status' => $user->nin_status,
                'national_id' => $user->national_id,
                'date_of_birth' => $user->date_of_birth,
                'mock_integration' => false,
            ],
            'consent' => DemoConsent::where('user_id', $user->id)
                ->where('purpose', DemoConsent::PURPOSE_CREDIT_PROCESSING)
                ->first(),
            'latest_application' => LoanApplication::with(['demoDecision', 'demoOffer', 'loan.schedules'])
                ->where('user_id', $user->id)
                ->latest()
                ->first(),
        ]);
    }

    public function grantConsent(Request $request): JsonResponse
    {
        $consent = $this->demoService->grantConsent($request->user(), $request);

        return ApiResponse::success('Mock investor-demo consent granted.', [
            'mock_integration' => true,
            'status' => $consent->status,
            'consent' => $consent,
        ]);
    }

    public function revokeConsent(Request $request): JsonResponse
    {
        $consent = $this->demoService->revokeConsent($request->user(), $request);

        return ApiResponse::success('Mock investor-demo consent revoked.', [
            'mock_integration' => true,
            'status' => $consent->status,
            'consent' => $consent,
        ]);
    }

    public function submitApplication(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|exists:loan_products,id',
            'loan_product_term_id' => 'required|exists:loan_product_terms,id',
            'institution_id' => 'required|exists:institutions,id',
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            return ApiResponse::success(
                'Investor demo application submitted and mock decision completed.',
                $this->demoService->createApplicationWithDecision($request->user(), $validator->validated(), $request),
                201,
            );
        } catch (AccessDeniedHttpException $exception) {
            return ApiResponse::error($exception->getMessage(), 403);
        }
    }

    public function decision(LoanApplication $application, Request $request): JsonResponse
    {
        if (!$this->canViewApplication($request->user(), $application)) {
            return ApiResponse::error('You cannot view this decision.', 403);
        }

        return ApiResponse::success('Investor demo decision loaded.', [
            'mock_integration' => true,
            'decision' => DemoLoanDecision::where('loan_application_id', $application->id)->first(),
        ]);
    }

    public function offer(LoanApplication $application, Request $request): JsonResponse
    {
        if (!$this->canViewApplication($request->user(), $application)) {
            return ApiResponse::error('You cannot view this offer.', 403);
        }

        return ApiResponse::success('Investor demo offer loaded.', [
            'mock_integration' => true,
            'offer' => DemoLoanOffer::where('loan_application_id', $application->id)->first(),
        ]);
    }

    public function acceptOffer(DemoLoanOffer $offer, Request $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                'Investor demo offer accepted, loan created, schedule generated, and mock disbursement recorded.',
                $this->demoService->acceptOffer($request->user(), $offer, $request),
            );
        } catch (AccessDeniedHttpException $exception) {
            return ApiResponse::error($exception->getMessage(), 403);
        } catch (BadRequestHttpException $exception) {
            return ApiResponse::error($exception->getMessage(), 400);
        }
    }

    public function adminSnapshot(Request $request): JsonResponse
    {
        if (!$request->user()?->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS, User::ROLE_SUPPORT])) {
            return ApiResponse::error('Admin access is required for the investor demo snapshot.', 403);
        }

        return ApiResponse::success('Investor demo admin snapshot loaded.', $this->demoService->snapshot());
    }

    private function canViewApplication(User $user, LoanApplication $application): bool
    {
        return $application->user_id === $user->id
            || $user->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS, User::ROLE_SUPPORT]);
    }
}
