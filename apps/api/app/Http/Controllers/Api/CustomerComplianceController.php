<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditDecision;
use App\Models\CreditTermVariation;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\Otp;
use App\Models\TransactionReceipt;
use App\Services\AppStoreCreditPolicy;
use App\Services\CreditTermVariationService;
use App\Services\GuarantorService;
use App\Services\ProductionCreditDecisionService;
use App\Services\ProductionCreditOfferService;
use App\Services\SmsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CustomerComplianceController extends Controller
{
    public function __construct(
        private readonly GuarantorService $guarantors,
        private readonly CreditTermVariationService $variations,
        private readonly ProductionCreditDecisionService $decisionService,
        private readonly ProductionCreditOfferService $offerService,
        private readonly AppStoreCreditPolicy $appStorePolicy,
        private readonly SmsService $smsService,
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

    public function requestGuarantorCode(LoanApplication $application, Request $request): JsonResponse
    {
        if ((int) $application->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $application->loadMissing(['loanProductTerm', 'user']);
        $required = min(2, max(0, (int) ($application->loanProductTerm?->guarantors_required ?? 0)));

        if ($required === 0) {
            return ApiResponse::error('This credit product does not require a guarantor.', 409);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:32',
            'name' => 'nullable|string|max:160',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $phone = trim((string) $request->input('phone'));
        if ($phone === '' || $phone === (string) $request->user()->phone) {
            return ApiResponse::error('Use a guarantor phone number different from the borrower.', 422);
        }

        $existing = LoanGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('phone', $phone)
            ->first();

        $recorded = LoanGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->count();

        if (! $existing && $recorded >= 2) {
            return ApiResponse::error('A loan may have at most two guarantor contacts.', 409);
        }

        $guarantor = $existing ?: new LoanGuarantor;
        $guarantor->fill([
            'loan_application_id' => $application->id,
            'borrower_user_id' => $request->user()->id,
            'position' => $existing?->position ?: ($recorded + 1),
            'phone' => $phone,
            'name' => $request->input('name'),
            'status' => LoanGuarantor::STATUS_PENDING,
            'verification_method' => 'otp_pending',
            'consent_evidence' => array_merge($existing?->consent_evidence ?? [], [
                'request_sent_at' => now()->toIso8601String(),
                'message_purpose' => 'guarantor_consent',
                'loan_application_reference' => (string) $application->id,
                'notice' => 'Sharing the OTP confirms the stated contact agrees to be recorded as guarantor for this loan application.',
            ]),
        ]);
        $guarantor->save();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Otp::updateOrCreate(
            ['phone' => $phone],
            [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5),
                'verified_at' => null,
                'verification_token_hash' => null,
            ],
        );

        $borrowerName = trim((string) ($application->user?->first_name ?: $application->user?->name ?: 'an OpFin customer'));
        $this->smsService->queueSms(
            $phone,
            "OpFin guarantor request: {$borrowerName} nominated this number for loan application {$application->id}. If you agree, give code {$otp} to the borrower. If you do not agree, do not share it. Code expires in 5 minutes.",
        );

        return ApiResponse::success('A consent code was sent to the guarantor.', [
            'guarantor' => $guarantor,
            'guarantors_required' => $required,
            'maximum_guarantor_contacts' => 2,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
        ]);
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

        $application->loadMissing('loanProductTerm');
        $required = min(2, max(0, (int) ($application->loanProductTerm?->guarantors_required ?? 0)));
        $verified = LoanGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->where('status', LoanGuarantor::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->whereNotNull('consented_at')
            ->count();

        $progression = $verified >= $required
            ? $this->progressApplication($application->fresh(), $request)
            : [
                'next_state' => 'guarantors_required',
                'guarantors_required' => $required,
                'guarantors_verified' => $verified,
                'guarantors_remaining' => max(0, $required - $verified),
            ];

        return ApiResponse::success('Guarantor contact verified and recorded.', [
            'guarantor' => $guarantor,
            'maximum_guarantor_contacts' => 2,
            ...$progression,
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

    private function progressApplication(LoanApplication $application, Request $request): array
    {
        $decision = $this->decisionService->decide($application, null);
        $application->update([
            'status' => match ($decision->status) {
                CreditDecision::STATUS_APPROVED => 'Approved',
                CreditDecision::STATUS_DECLINED => 'Declined',
                default => 'Referred',
            },
            'approved_at' => $decision->status === CreditDecision::STATUS_APPROVED ? now() : null,
        ]);

        $offer = null;
        $nextState = match ($decision->status) {
            CreditDecision::STATUS_APPROVED => 'offer_ready',
            CreditDecision::STATUS_DECLINED => 'declined',
            default => 'assessment',
        };

        if ($decision->status === CreditDecision::STATUS_APPROVED) {
            $amountMinor = (int) $application->amount;
            $pricing = [
                'access_fee_minor' => (int) round(
                    $amountMinor * ((float) config('opfin.credit.default_pricing.access_fee_percent', 0) / 100),
                ),
                'disbursement_fee_minor' => (int) config('opfin.credit.default_pricing.disbursement_fee_minor', 0),
                'fee_treatment' => (string) config('opfin.credit.default_pricing.fee_treatment', 'financed'),
                'expires_in_minutes' => (int) config('opfin.credit.default_pricing.expires_in_minutes', 1440),
            ];

            try {
                $appStoreDisclosure = $this->appStorePolicy->validateOffer($application->fresh(), $pricing);
                $offer = $this->offerService->createOffer($application->fresh(), $request->user(), $pricing);

                if ($appStoreDisclosure !== []) {
                    $offer->forceFill([
                        'pricing_snapshot' => array_merge($offer->pricing_snapshot ?? [], $appStoreDisclosure),
                        'disclosure_snapshot' => array_merge($offer->disclosure_snapshot ?? [], $appStoreDisclosure),
                    ])->save();
                    $offer = $offer->fresh();
                }
            } catch (InvalidArgumentException $exception) {
                $application->update(['status' => 'Referred']);
                $nextState = 'assessment';
                report($exception);
            }
        }

        return [
            'next_state' => $nextState,
            'decision' => $decision,
            'offer' => $offer,
            'guarantors_required' => min(2, max(0, (int) ($application->loanProductTerm?->guarantors_required ?? 0))),
            'guarantors_verified' => LoanGuarantor::query()
                ->where('loan_application_id', $application->id)
                ->where('status', LoanGuarantor::STATUS_VERIFIED)
                ->count(),
            'guarantors_remaining' => 0,
        ];
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
