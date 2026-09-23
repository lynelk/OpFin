<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Models\User;
use App\Services\EssentialsOrchestrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PartnerEssentialsController extends Controller
{
    public function __construct(private readonly EssentialsOrchestrationService $essentials) {}

    public function eligibility(Request $request, int $customer): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);
        $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);
        $customer = User::withoutGlobalScopes()->findOrFail($customer);
        try {
            $this->essentials->assertPartnerCustomerAuthorised(
                $customer,
                (int) $validated['partner_account_id'],
                'eligibility',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
            );
            $result = $this->essentials->refreshEligibility(
                $customer,
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
                (string) ($validated['channel'] ?? 'partner'),
            );
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
        $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);
        $customer = User::withoutGlobalScopes()->findOrFail($customer);
        try {
            $this->essentials->assertPartnerCustomerAuthorised(
                $customer,
                (int) $validated['partner_account_id'],
                'account_write',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $validated['metadata'] = array_merge((array) ($validated['metadata'] ?? []), [
            'source_platform' => $validated['source_platform'],
            'partner_account_id' => (int) $validated['partner_account_id'],
            'customer_consent_reference' => $validated['customer_consent_reference'],
        ]);

        try {
            $account = $this->essentials->createAccount($customer, $validated);
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
        $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);
        $customer = User::withoutGlobalScopes()->findOrFail($customer);
        $account = \App\Models\EssentialsAccount::query()
            ->where('user_id', $customer->id)
            ->findOrFail((int) $validated['essentials_account_id']);
        try {
            $this->essentials->assertPartnerCustomerAuthorised(
                $customer,
                (int) $validated['partner_account_id'],
                'quote_create',
                $account->financial_space_id,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $validated['source_partner_account_id'] = (int) $validated['partner_account_id'];

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
        $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);
        $customerModel = User::withoutGlobalScopes()->findOrFail($customer);

        try {
            $this->essentials->assertPartnerCustomerAuthorised(
                $customerModel,
                (int) $validated['partner_account_id'],
                'status_read',
                isset($validated['financial_space_id']) ? (int) $validated['financial_space_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $advances = EssentialsAdvance::query()
            ->where('user_id', $customerModel->id)
            ->when(isset($validated['financial_space_id']), fn ($q) => $q->where('financial_space_id', (int) $validated['financial_space_id']))
            ->latest()
            ->get([
                'id', 'reference', 'financial_space_id', 'status', 'principal_minor',
                'principal_outstanding_minor', 'total_repayment_minor', 'outstanding_minor',
                'currency', 'next_due_date', 'final_due_date', 'activated_at', 'settled_at',
            ]);

        return ApiResponse::success('Embedded Essentials status loaded.', ['advances' => $advances]);
    }

    public function complete(Request $request, int $quote): JsonResponse
    {
        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'disclosure_hash' => ['required', 'string', 'size:64'],
            'customer_authorisation_token' => ['required', 'string', 'min:32'],
        ]);
        $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);

        $record = EssentialsQuote::query()->findOrFail($quote);
        if ((int) $record->source_partner_account_id !== (int) $validated['partner_account_id']
            || (int) $record->user_id !== (int) $validated['customer_user_id']) {
            return ApiResponse::error('The quote does not belong to this platform/customer context.', 403);
        }

        try {
            $advance = $this->essentials->acceptQuote(
                User::withoutGlobalScopes()->findOrFail((int) $validated['customer_user_id']),
                $record->id,
                (string) $validated['disclosure_hash'],
                (string) $validated['customer_authorisation_token'],
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Embedded Essentials finance completed after customer authorisation.', ['advance' => $advance], 201);
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
