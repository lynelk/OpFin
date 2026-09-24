<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Services\EssentialsOrchestrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class EssentialsController extends Controller
{
    public function __construct(private readonly EssentialsOrchestrationService $essentials) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success('OpFin Essentials loaded.', $this->essentials->summary($request->user()));
    }

    public function catalogue(): JsonResponse
    {
        return ApiResponse::success('Essentials catalogue loaded.', $this->essentials->catalogue());
    }

    public function partnerAuthorisations(Request $request): JsonResponse
    {
        return ApiResponse::success('Essentials partner permissions loaded.', [
            'authorisations' => $this->essentials->partnerAuthorisations($request->user()),
        ]);
    }

    public function authorisePartnerPlatform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:eligibility,account_write,quote_create,status_read'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        try {
            $authorisation = $this->essentials->authorisePartnerPlatform(
                $request->user(),
                (int) $validated['partner_account_id'],
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
                $validated['scopes'],
                (int) ($validated['valid_days'] ?? 30),
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Platform access authorised for OpFin Essentials.', ['authorisation' => $authorisation], 201);
    }

    public function revokePartnerPlatform(Request $request, int $authorisation): JsonResponse
    {
        $revoked = $this->essentials->revokePartnerPlatform($request->user(), $authorisation);

        return ApiResponse::success('Platform access revoked.', ['authorisation' => $revoked]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'biller_id' => ['required', 'integer', 'exists:essentials_billers,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'account_reference' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $account = $this->essentials->createAccount($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Essential-service account saved.', ['account' => $account], 201);
    }

    public function verifyAccount(Request $request, int $account): JsonResponse
    {
        try {
            $verified = $this->essentials->verifyAccount($request->user(), $account);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Account verification updated.', ['account' => $verified]);
    }

    public function eligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $result = $this->essentials->refreshEligibility(
                $request->user(),
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
                (string) ($validated['channel'] ?? 'android'),
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Participating lender eligibility refreshed.', $result);
    }

    public function quotes(Request $request): JsonResponse
    {
        return ApiResponse::success('Essentials quotes loaded.', [
            'quotes' => EssentialsQuote::query()->where('user_id', $request->user()->id)->latest()->limit(50)->get(),
        ]);
    }

    public function createQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'essentials_account_id' => ['required', 'integer', 'exists:essentials_accounts,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:40'],
            'source_partner_account_id' => ['nullable', 'integer', 'exists:partner_distribution_accounts,id'],
            'source_platform' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $quote = $this->essentials->createQuote($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Third-party lender quote created.', [
            'quote' => $quote,
            'disclosure_hash' => $quote->disclosure_hash,
        ], 201);
    }

    public function authorisePartner(Request $request, int $quote): JsonResponse
    {
        try {
            $result = $this->essentials->authorisePartnerCompletion($request->user(), $quote);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Partner completion authorised.', $result);
    }

    public function accept(Request $request, int $quote): JsonResponse
    {
        $validated = $request->validate([
            'disclosure_hash' => ['required', 'string', 'size:64'],
            'accept_disclosures' => ['required', 'accepted'],
        ]);

        try {
            $advance = $this->essentials->acceptQuote(
                $request->user(),
                $quote,
                (string) $validated['disclosure_hash'],
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Essentials finance accepted and purpose-bound settlement initiated.', ['advance' => $advance], 201);
    }

    public function advances(Request $request): JsonResponse
    {
        return ApiResponse::success('Essentials advances loaded.', [
            'advances' => EssentialsAdvance::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function repay(Request $request, int $advance): JsonResponse
    {
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'wallet_id' => ['nullable', 'integer', 'exists:customer_wallets,id'],
        ]);

        try {
            $repayment = $this->essentials->repay(
                $request->user(),
                $advance,
                (int) $validated['amount_minor'],
                (string) $validated['idempotency_key'],
                isset($validated['wallet_id']) ? (int) $validated['wallet_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Essentials repayment submitted.', ['repayment' => $repayment], 201);
    }
}
