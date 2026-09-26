<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Models\User;
use App\Services\EssentialsOrchestrationService;
use App\Services\EssentialsPartnerScopeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PartnerEssentialsController extends Controller
{
    public function __construct(
        private readonly EssentialsOrchestrationService $essentials,
        private readonly EssentialsPartnerScopeService $spaces,
    ) {}

    public function eligibility(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);
        $partnerId = (int) $validated['partner_account_id'];
        $this->assertPartnerAccess($request, $partnerId);
        $customer = $this->customer($customer);
        try {
            $spaceId = $this->spaces->resolve($customer, $partnerId, 'eligibility',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null);
            $result = $this->essentials->refreshEligibility($customer, $spaceId, (string) ($validated['channel'] ?? 'partner'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Customer Essentials eligibility refreshed.', $result);
    }

    public function storeAccount(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'biller_id' => ['required', 'integer', 'exists:essentials_billers,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'account_reference' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:120'],
            'source_platform' => ['required', 'string', 'max:80'],
            'customer_consent_reference' => ['required', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
        ]);
        $partnerId = (int) $validated['partner_account_id'];
        $this->assertPartnerAccess($request, $partnerId);
        $customer = $this->customer($customer);
        try {
            $spaceId = $this->spaces->resolve($customer, $partnerId, 'account_write',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
        $validated['financial_space_id'] = $spaceId;
        $validated['metadata'] = array_merge((array) ($validated['metadata'] ?? []), [
            'source_platform' => $validated['source_platform'],
            'partner_account_id' => $partnerId,
            'customer_consent_reference' => $validated['customer_consent_reference'],
        ]);

        try {
            $account = DB::transaction(function () use ($customer, $validated, $spaceId) {
                DB::table('users')->where('id', $customer->id)->lockForUpdate()->first();
                $hash = hash('sha256', strtoupper(preg_replace('/\s+/', '', trim((string) $validated['account_reference'])) ?? ''));
                $existing = EssentialsAccount::query()->where('user_id', $customer->id)
                    ->where('biller_id', $validated['biller_id'])->where('account_reference_hash', $hash)
                    ->lockForUpdate()->first();
                if ($existing && (int) $existing->financial_space_id !== $spaceId) {
                    throw new InvalidArgumentException('An embedded platform cannot move a service account from another Financial Space.');
                }

                return $this->essentials->createAccount($customer, $validated);
            });
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Embedded Essentials account saved.', ['account' => $account], 201);
    }

    public function createQuote(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'essentials_account_id' => ['required', 'integer', 'exists:essentials_accounts,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:40'],
            'source_platform' => ['required', 'string', 'max:80'],
        ]);
        $partnerId = (int) $validated['partner_account_id'];
        $this->assertPartnerAccess($request, $partnerId);
        $customer = $this->customer($customer);
        $account = EssentialsAccount::query()->where('user_id', $customer->id)
            ->findOrFail((int) $validated['essentials_account_id']);
        if (! $account->financial_space_id) {
            return ApiResponse::error('This service account needs an explicit Financial Space before partner financing.', 403);
        }
        try {
            $this->spaces->resolve($customer, $partnerId, 'quote_create', (int) $account->financial_space_id);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
        $validated['source_partner_account_id'] = $partnerId;
        try {
            $quote = $this->essentials->createQuote($customer, $validated);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Embedded third-party lender quote created. Customer confirmation is still required.', [
            'quote' => $quote,
            'disclosure_hash' => $quote->disclosure_hash,
            'customer_confirmation_required' => true,
        ], 201);
    }

    public function status(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
        ]);
        $partnerId = (int) $validated['partner_account_id'];
        $this->assertPartnerAccess($request, $partnerId);
        $customerModel = $this->customer($customer);
        try {
            $spaceId = $this->spaces->resolve($customerModel, $partnerId, 'status_read',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
        $advances = EssentialsAdvance::query()
            ->where('user_id', $customerModel->id)
            ->where('financial_space_id', $spaceId)
            ->latest()
            ->get([
                'id', 'reference', 'financial_space_id', 'status', 'principal_minor',
                'principal_outstanding_minor', 'total_repayment_minor', 'outstanding_minor',
                'currency', 'next_due_date', 'final_due_date', 'activated_at', 'settled_at',
            ]);

        return ApiResponse::success('Embedded Essentials status loaded.', [
            'financial_space_id' => $spaceId,
            'advances' => $advances,
        ]);
    }

    public function complete(Request $request, int $quote): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'disclosure_hash' => ['required', 'string', 'size:64'],
            'customer_authorisation_token' => ['required', 'string', 'min:32'],
        ]);
        $partnerId = (int) $validated['partner_account_id'];
        $this->assertPartnerAccess($request, $partnerId);
        $record = EssentialsQuote::query()->findOrFail($quote);
        if ((int) $record->source_partner_account_id !== $partnerId
            || (int) $record->user_id !== (int) $validated['customer_user_id']
            || ! $record->financial_space_id) {
            return ApiResponse::error('The quote does not belong to this platform/customer/Financial Space context.', 403);
        }
        $customer = $this->customer((int) $validated['customer_user_id']);
        try {
            $this->spaces->resolve($customer, $partnerId, 'quote_create', (int) $record->financial_space_id);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }
        try {
            $advance = $this->essentials->acceptQuote($customer, $record->id,
                (string) $validated['disclosure_hash'], (string) $validated['customer_authorisation_token']);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Embedded Essentials instruction accepted after customer authorisation; inspect its settlement state.', ['advance' => $advance], 201);
    }

    private function customer(int $id): User
    {
        return User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
    }

    private function assertPartnerAccess(Request $request, int $partnerAccountId): void
    {
        if (in_array($request->user()->role, ['platform_admin', 'operations'], true)) {
            return;
        }
        $allowed = DB::table('partner_distribution_accounts')
            ->where('id', $partnerAccountId)
            ->where('created_by', $request->user()->id)
            ->whereIn('status', ['active', 'approved'])
            ->exists();
        abort_unless($allowed, 403);
    }
}
