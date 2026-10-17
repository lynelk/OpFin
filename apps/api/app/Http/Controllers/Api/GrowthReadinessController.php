<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapitalMandate;
use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GrowthReadinessController extends Controller
{
    /**
     * Operational inventory, never an attestation of regulator, provider, payment
     * or investor approval. Visible only to the platform administrator.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::ROLE_PLATFORM_ADMIN), 403);

        $institutions = Institution::query()->get()->keyBy('id');
        $products = LoanProduct::withoutGlobalScopes()->with('terms')->get()->map(function (LoanProduct $product) use ($institutions): array {
            $lender = $institutions->get($product->institution_id);
            $authorityConfigured = $lender && $lender->status === 'Active'
                && in_array($lender->authority_basis, ['licensed', 'other_authority', 'exempt'], true)
                && filled($lender->authority_reference)
                && (! $lender->authority_valid_until || $lender->authority_valid_until->endOfDay()->isFuture());
            $terms = $product->terms->where('status', 'Active')->pluck('duration')->values()->all();
            $configured = $product->status === 'Active' && $authorityConfigured && $terms !== [];

            return [
                'product_id' => $product->id,
                'institution_id' => $product->institution_id,
                'country' => $product->country,
                'currency' => $product->currency,
                'product_category' => $product->product_category,
                'terms_days' => $terms,
                'configuration_status' => $configured ? 'configured' : 'incomplete',
                'funding_and_settlement_status' => 'independent_verification_required',
                'available_to_customer' => null, // Not knowable from product settings alone.
            ];
        });

        $mandates = CapitalMandate::query()->get()->map(function (CapitalMandate $mandate): array {
            $available = max(0, $mandate->committed_capital_minor - $mandate->deployed_capital_minor - $mandate->reserved_capital_minor);

            return [
                'mandate_id' => $mandate->id,
                'mandate_type' => $mandate->mandate_type,
                'status' => $mandate->status,
                'remaining_committed_capital_minor' => $available,
                'configured_for_review' => in_array($mandate->status, ['approved', 'active'], true)
                    && $mandate->approved_at !== null && $available > 0,
                'settled_funding_verified' => null, // Requires externally reconciled evidence.
                'investor_participation_live' => null, // Do not infer contractual/custody permissions.
            ];
        });

        return ApiResponse::success('Growth configuration inventory; not proof of financial activation.', [
            'as_of' => now()->toIso8601String(),
            'products' => $products,
            'capital_mandates' => $mandates,
            'summary' => [
                'configured_products' => $products->where('configuration_status', 'configured')->count(),
                'mandates_configured_for_review' => $mandates->where('configured_for_review', true)->count(),
            ],
            'verification_required' => [
                'actual_lender_authority', 'approved_loan_terms_and_disclosures',
                'borrower_affordability', 'funding_and_custody',
                'payment_settlement', 'investor_contract_and_suitability',
            ],
            'growth_policy' => 'No universal waiting period, cohort-count or vintage-count prerequisite.',
            'statement_provider_readiness' => config('statement_provider_readiness.UG', []),
        ]);
    }
}
