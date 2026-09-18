<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTermVariation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\TransactionReceipt;
use App\Services\CreditTermVariationService;
use App\Services\GuarantorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerComplianceController extends Controller
{
    public function __construct(
        private readonly GuarantorService $guarantors,
        private readonly CreditTermVariationService $variations,
    ) {}

    public function receipts(Request $request): JsonResponse
    {
        return ApiResponse::success('Transaction receipts loaded.', [
            'receipts' => TransactionReceipt::query()
                ->where('user_id', $request->user()->id)
                ->latest('issued_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function receipt(TransactionReceipt $receipt, Request $request): JsonResponse
    {
        if ((int) $receipt->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return ApiResponse::success('Transaction receipt loaded.', ['receipt' => $receipt]);
    }

    public function attachGuarantor(LoanApplication $application, Request $request): JsonResponse
    {
        if ((int) $application->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:32',
            'name' => 'nullable|string|max:160',
            'verification_token' => 'required|string|size:64',
            'consent_confirmed' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $guarantor = $this->guarantors->attachVerified(
                $application,
                $request->user()->id,
                (string) $request->input('phone'),
                (string) $request->input('verification_token'),
                true,
                $request->input('name'),
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Guarantor contact verified and recorded.', [
            'guarantor' => $guarantor,
            'maximum_guarantor_contacts' => 2,
        ], 201);
    }

    public function variations(Request $request): JsonResponse
    {
        $loanIds = Loan::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->pluck('id');

        return ApiResponse::success('Credit-term variations loaded.', [
            'variations' => CreditTermVariation::query()
                ->whereIn('loan_id', $loanIds)
                ->latest()
                ->get()
                ->map(fn (CreditTermVariation $variation) => [
                    ...$variation->toArray(),
                    'consent_hash' => $this->variationHash($variation),
                ]),
        ]);
    }

    public function acceptVariation(CreditTermVariation $variation, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'accept_terms' => 'required|accepted',
            'variation_hash' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Explicit customer consent is required.', 422, $validator->errors()->toArray());
        }

        $expected = $this->variationHash($variation);
        if (! hash_equals($expected, (string) $request->input('variation_hash'))) {
            return ApiResponse::error('The proposed term variation changed. Reload it before accepting.', 409);
        }

        try {
            $updated = $this->variations->customerConsent($variation, $request->user(), [
                'variation_hash' => $expected,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'channel' => 'api',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Credit-term variation consent recorded.', ['variation' => $updated]);
    }

    private function variationHash(CreditTermVariation $variation): string
    {
        return hash('sha256', json_encode([
            'id' => $variation->id,
            'loan_id' => $variation->loan_id,
            'proposed_changes' => $variation->proposed_changes,
            'reason' => $variation->reason,
            'requires_umra_approval' => $variation->requires_umra_approval,
            'umra_approval_reference' => $variation->umra_approval_reference,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
