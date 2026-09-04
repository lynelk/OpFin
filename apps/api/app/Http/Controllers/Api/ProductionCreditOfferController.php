<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditOffer;
use App\Models\LoanApplication;
use App\Services\AppStoreCreditPolicy;
use App\Services\ProductionCreditOfferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ProductionCreditOfferController extends Controller
{
    public function __construct(
        private readonly ProductionCreditOfferService $offerService,
        private readonly AppStoreCreditPolicy $appStorePolicy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        CreditOffer::query()
            ->where('user_id', $request->user()->id)
            ->where('status', CreditOffer::STATUS_OFFERED)
            ->where('expires_at', '<=', now())
            ->update(['status' => CreditOffer::STATUS_EXPIRED]);

        $offers = CreditOffer::query()
            ->where('user_id', $request->user()->id)
            ->latest('offered_at')
            ->limit(50)
            ->get();

        return ApiResponse::success('Credit offers loaded.', ['offers' => $offers]);
    }

    public function show(CreditOffer $offer, Request $request): JsonResponse
    {
        if ($offer->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        if ($offer->status === CreditOffer::STATUS_OFFERED && $offer->expires_at->isPast()) {
            $offer->update(['status' => CreditOffer::STATUS_EXPIRED]);
        }

        return ApiResponse::success('Credit offer loaded.', [
            'offer' => $offer->fresh(),
            'disclosure_hash' => $this->disclosureHash($offer),
        ]);
    }

    public function store(LoanApplication $application, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_fee_minor' => 'nullable|integer|min:0',
            'disbursement_fee_minor' => 'nullable|integer|min:0',
            'fee_treatment' => ['nullable', Rule::in(['financed', 'deducted'])],
            'expires_in_minutes' => 'nullable|integer|min:5|max:10080',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        try {
            $validated = $validator->validated();
            $appStoreDisclosure = $this->appStorePolicy->validateOffer($application, $validated);
            $offer = $this->offerService->createOffer($application, $request->user(), $validated);

            if ($appStoreDisclosure !== []) {
                $offer->forceFill([
                    'pricing_snapshot' => array_merge($offer->pricing_snapshot ?? [], $appStoreDisclosure),
                    'disclosure_snapshot' => array_merge($offer->disclosure_snapshot ?? [], $appStoreDisclosure),
                ])->save();
                $offer = $offer->fresh();
            }
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Credit offer generated.', [
            'offer' => $offer,
            'disclosure_hash' => $this->disclosureHash($offer),
        ], 201);
    }

    public function accept(CreditOffer $offer, Request $request): JsonResponse
    {
        if ($offer->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $validator = Validator::make($request->all(), [
            'accept_disclosures' => 'required|accepted',
            'disclosure_hash' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Explicit acceptance of the disclosed offer is required.', 422, $validator->errors()->toArray());
        }

        $expectedHash = $this->disclosureHash($offer);
        if (! hash_equals($expectedHash, (string) $request->input('disclosure_hash'))) {
            return ApiResponse::error('The offer disclosure has changed or the supplied disclosure hash is invalid. Reload the offer before accepting it.', 409, ['disclosure_hash' => ['DISCLOSURE_HASH_MISMATCH']]);
        }

        try {
            $result = $this->offerService->acceptOffer($offer, $request->user(), [
                'disclosure_hash' => $expectedHash,
                'offer_reference' => $offer->offer_reference,
                'offer_version' => $offer->version,
                'policy_version' => $offer->policy_version,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'accepted_at' => now()->toISOString(),
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::success('Credit offer accepted and disbursement initiated.', [
            ...$result,
            'next_state' => $result['loan'] ? 'active_loan' : 'disbursement_pending',
        ]);
    }

    private function disclosureHash(CreditOffer $offer): string
    {
        return hash('sha256', json_encode([
            'offer_reference' => $offer->offer_reference,
            'version' => $offer->version,
            'policy_version' => $offer->policy_version,
            'pricing' => $offer->pricing_snapshot,
            'disclosures' => $offer->disclosure_snapshot,
            'expires_at' => $offer->expires_at?->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
