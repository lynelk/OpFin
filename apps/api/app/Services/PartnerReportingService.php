<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PartnerReportingService
{
    public function serviceEconomics(Carbon $start, Carbon $end, array $filters = []): array
    {
        $query = DB::table('service_economics_events')->whereBetween('occurred_at', [$start, $end]);
        foreach (['service_code', 'provider', 'route', 'environment'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $summary = (clone $query)->selectRaw('
            COUNT(*) as events,
            SUM(CASE WHEN status IN (\'PASS\', \'SUCCESS\', \'SUCCESSFUL\', \'COMPLETED\') THEN 1 ELSE 0 END) as successful_events,
            SUM(CASE WHEN status IN (\'FAIL\', \'FAILED\', \'ERROR\') THEN 1 ELSE 0 END) as failed_events,
            SUM(CASE WHEN status = \'PENDING\' THEN 1 ELSE 0 END) as pending_events,
            SUM(COALESCE(provider_net_cost_minor, 0)) as provider_net_cost_minor,
            SUM(COALESCE(customer_service_charge_minor, 0)) as customer_service_charge_minor,
            SUM(COALESCE(customer_platform_fee_minor, 0)) as customer_platform_fee_minor,
            SUM(COALESCE(partner_commission_minor, 0)) as partner_commission_minor,
            SUM(COALESCE(cito_platform_fee_minor, 0)) as cito_platform_fee_minor,
            SUM(COALESCE(opfin_platform_fee_minor, 0)) as opfin_platform_fee_minor,
            SUM(COALESCE(tax_amount_minor, 0)) as tax_amount_minor,
            SUM(COALESCE(net_settlement_to_provider_minor, 0)) as net_settlement_to_provider_minor,
            SUM(COALESCE(net_revenue_minor, 0)) as net_revenue_minor,
            SUM(COALESCE(gross_margin_minor, 0)) as gross_margin_minor,
            SUM(CASE WHEN provider_net_cost_minor IS NULL THEN 1 ELSE 0 END) as unknown_provider_cost_events,
            SUM(CASE WHEN net_revenue_minor IS NULL THEN 1 ELSE 0 END) as incomplete_revenue_events
        ')->first();

        $breakdown = (clone $query)
            ->select(
                'service_code',
                'capability_code',
                'provider',
                'route',
                'environment',
                'currency',
                DB::raw('COUNT(*) as events'),
                DB::raw('SUM(COALESCE(provider_net_cost_minor, 0)) as provider_net_cost_minor'),
                DB::raw('SUM(COALESCE(customer_service_charge_minor, 0)) as customer_service_charge_minor'),
                DB::raw('SUM(COALESCE(customer_platform_fee_minor, 0)) as customer_platform_fee_minor'),
                DB::raw('SUM(COALESCE(partner_commission_minor, 0)) as partner_commission_minor'),
                DB::raw('SUM(COALESCE(cito_platform_fee_minor, 0)) as cito_platform_fee_minor'),
                DB::raw('SUM(COALESCE(opfin_platform_fee_minor, 0)) as opfin_platform_fee_minor'),
                DB::raw('SUM(COALESCE(tax_amount_minor, 0)) as tax_amount_minor'),
                DB::raw('SUM(COALESCE(net_revenue_minor, 0)) as net_revenue_minor'),
                DB::raw('SUM(COALESCE(gross_margin_minor, 0)) as gross_margin_minor'),
                DB::raw('SUM(CASE WHEN provider_net_cost_minor IS NULL THEN 1 ELSE 0 END) as unknown_provider_cost_events'),
            )
            ->groupBy('service_code', 'capability_code', 'provider', 'route', 'environment', 'currency')
            ->orderBy('service_code')
            ->orderBy('provider')
            ->get();

        return [
            'period' => $this->period($start, $end),
            'summary' => $summary,
            'breakdown' => $breakdown,
            'reporting_rule' => 'Known zero values are stored as 0. Null means the commercial amount has not yet been supplied or reconciled.',
        ];
    }

    public function capitalAndLoanBook(Carbon $start, Carbon $end): array
    {
        $loans = DB::table('loans')->whereBetween('created_at', [$start, $end]);
        $collections = DB::table('mobile_money_transactions')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('loan_id')
            ->where('direction', 'collection')
            ->where('status', 'successful');

        $fundingPools = DB::table('capital_mandates')
            ->leftJoin('loans', 'loans.funding_pool_id', '=', 'capital_mandates.id')
            ->select(
                'capital_mandates.id',
                'capital_mandates.reference',
                'capital_mandates.name',
                'capital_mandates.mandate_type',
                'capital_mandates.status',
                'capital_mandates.committed_capital_minor',
                'capital_mandates.deployed_capital_minor',
                'capital_mandates.reserved_capital_minor',
                DB::raw('GREATEST(capital_mandates.committed_capital_minor - capital_mandates.deployed_capital_minor - capital_mandates.reserved_capital_minor, 0) as available_capital_minor'),
                DB::raw('COUNT(loans.id) as loan_count'),
                DB::raw('COALESCE(SUM(loans.amount), 0) as originated_principal_minor'),
                DB::raw('SUM(CASE WHEN loans.non_performing_at IS NOT NULL THEN 1 ELSE 0 END) as non_performing_loans'),
            )
            ->groupBy(
                'capital_mandates.id',
                'capital_mandates.reference',
                'capital_mandates.name',
                'capital_mandates.mandate_type',
                'capital_mandates.status',
                'capital_mandates.committed_capital_minor',
                'capital_mandates.deployed_capital_minor',
                'capital_mandates.reserved_capital_minor',
            )
            ->orderBy('capital_mandates.id')
            ->get();

        return [
            'period' => $this->period($start, $end),
            'capital' => [
                'pools' => DB::table('capital_mandates')->count(),
                'committed_capital_minor' => (int) DB::table('capital_mandates')->sum('committed_capital_minor'),
                'deployed_capital_minor' => (int) DB::table('capital_mandates')->sum('deployed_capital_minor'),
                'reserved_capital_minor' => (int) DB::table('capital_mandates')->sum('reserved_capital_minor'),
                'available_capital_minor' => max(
                    0,
                    (int) DB::table('capital_mandates')->sum('committed_capital_minor')
                    - (int) DB::table('capital_mandates')->sum('deployed_capital_minor')
                    - (int) DB::table('capital_mandates')->sum('reserved_capital_minor'),
                ),
            ],
            'origination' => [
                'loans' => (clone $loans)->count(),
                'principal_minor' => (int) (clone $loans)->sum('amount'),
                'assigned_funding_pool_loans' => (clone $loans)->whereNotNull('funding_pool_id')->count(),
                'unassigned_funding_source_loans' => (clone $loans)->whereNull('funding_pool_id')->count(),
            ],
            'portfolio' => [
                'current_loans' => DB::table('loans')->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])->count(),
                'non_performing_loans' => DB::table('loans')->whereNotNull('non_performing_at')->count(),
                'collections_minor' => (int) (clone $collections)->sum('amount_minor'),
                'collections_count' => (clone $collections)->count(),
            ],
            'funding_pool_breakdown' => $fundingPools,
            'economics' => $this->serviceEconomics($start, $end, ['service_code' => 'credit']),
            'control_exceptions' => [
                'unassigned_funding_source_loans' => DB::table('loans')->whereNull('funding_pool_id')->count(),
                'rule' => 'Every new funded loan must be attributable to one contractual funding pool before production disbursement.',
            ],
        ];
    }

    public function insurance(Carbon $start, Carbon $end): array
    {
        $policies = DB::table('protection_policies')->whereBetween('enrolled_at', [$start, $end]);
        $premiums = DB::table('protection_premium_payments')->whereBetween('requested_at', [$start, $end]);
        $claims = DB::table('protection_claims')->whereBetween('submitted_at', [$start, $end]);

        return [
            'period' => $this->period($start, $end),
            'policies' => [
                'enrolled' => (clone $policies)->count(),
                'issued' => (clone $policies)->whereNotNull('issued_at')->count(),
                'active' => (clone $policies)->where('status', 'active')->count(),
                'lapsed' => (clone $policies)->where('status', 'lapsed')->count(),
                'cancelled' => (clone $policies)->where('status', 'cancelled')->count(),
            ],
            'premiums' => [
                'requested_minor' => (int) (clone $premiums)->sum('amount_minor'),
                'confirmed_minor' => (int) (clone $premiums)->where('status', 'confirmed')->sum('amount_minor'),
                'pending_partner_minor' => (int) (clone $premiums)->where('status', 'collected_pending_partner')->sum('amount_minor'),
                'failed_minor' => (int) (clone $premiums)->where('status', 'failed')->sum('amount_minor'),
            ],
            'claims' => [
                'submitted' => (clone $claims)->count(),
                'approved' => (clone $claims)->where('status', 'approved')->count(),
                'declined' => (clone $claims)->where('status', 'declined')->count(),
                'paid' => (clone $claims)->where('status', 'paid')->count(),
                'disputed' => (clone $claims)->where('status', 'disputed')->count(),
                'claimed_amount_minor' => (int) (clone $claims)->sum('claimed_amount_minor'),
                'approved_amount_minor' => (int) (clone $claims)->sum('approved_amount_minor'),
            ],
            'economics' => $this->serviceEconomics($start, $end, ['service_code' => 'insurance']),
        ];
    }

    public function savingsAndInvestments(Carbon $start, Carbon $end): array
    {
        $savings = DB::table('savings_movements')->whereBetween('requested_at', [$start, $end]);
        $investments = DB::table('participatory_finance_commitments')->whereBetween('created_at', [$start, $end]);

        return [
            'period' => $this->period($start, $end),
            'savings' => [
                'movements' => (clone $savings)->count(),
                'amount_minor' => (int) (clone $savings)->sum('amount_minor'),
                'by_type' => (clone $savings)
                    ->select('movement_type', DB::raw('COUNT(*) as movements'), DB::raw('SUM(amount_minor) as amount_minor'))
                    ->groupBy('movement_type')
                    ->orderBy('movement_type')
                    ->get(),
                'confirmed_partner_custody_minor' => (int) (clone $savings)->where('status', 'confirmed')->sum('amount_minor'),
            ],
            'investments' => [
                'participatory_commitments' => (clone $investments)->count(),
                'committed_amount_minor' => (int) (clone $investments)->sum('amount_minor'),
                'settled_amount_minor' => (int) (clone $investments)->whereNotNull('settled_at')->sum('amount_minor'),
                'active_partner_products' => DB::table('partner_products')
                    ->where('status', 'active')
                    ->whereIn('product_type', ['investment', 'fund', 'unit_trust', 'participatory_finance'])
                    ->count(),
            ],
            'savings_economics' => $this->serviceEconomics($start, $end, ['service_code' => 'savings']),
            'investment_economics' => $this->serviceEconomics($start, $end, ['service_code' => 'investment']),
        ];
    }

    public function employmentPositiveBehaviour(Carbon $start, Carbon $end): array
    {
        $signals = DB::table('alternative_data_signals')
            ->where('source_type', 'employer')
            ->whereBetween(DB::raw('COALESCE(observed_at, created_at)'), [$start, $end]);

        return [
            'period' => $this->period($start, $end),
            'verified_risk_eligible_signals' => (clone $signals)->where('verified', true)->where('risk_eligible', true)->count(),
            'customers_with_verified_signals' => (clone $signals)->where('verified', true)->where('risk_eligible', true)->distinct('user_id')->count('user_id'),
            'by_signal' => (clone $signals)
                ->where('verified', true)
                ->where('risk_eligible', true)
                ->select('signal_key', DB::raw('COUNT(*) as signals'), DB::raw('COUNT(DISTINCT user_id) as customers'))
                ->groupBy('signal_key')
                ->orderBy('signal_key')
                ->get(),
            'policy' => [
                'positive_only' => true,
                'missing_information_effect' => 'neutral',
                'negative_information_effect' => 'neutral',
                'maximum_uplift_points' => (float) config('opfin.credit.positive_employment_behaviour.max_uplift_points', 5),
            ],
        ];
    }

    public function financialAccountBehaviour(Carbon $start, Carbon $end): array
    {
        $accounts = DB::table('linked_financial_accounts')->whereBetween('created_at', [$start, $end]);
        $signals = DB::table('alternative_data_signals')
            ->whereIn('source_type', ['mno', 'transactional', 'partner'])
            ->whereIn('purpose', ['credit_assessment', 'affordability'])
            ->where('verified', true)
            ->whereBetween(DB::raw('COALESCE(observed_at, created_at)'), [$start, $end]);

        return [
            'period' => $this->period($start, $end),
            'linked_accounts' => [
                'new_accounts' => (clone $accounts)->count(),
                'by_provider_and_type' => (clone $accounts)
                    ->select('provider', 'account_type', 'status', 'data_confidence', DB::raw('COUNT(*) as accounts'))
                    ->groupBy('provider', 'account_type', 'status', 'data_confidence')
                    ->orderBy('provider')
                    ->get(),
            ],
            'verified_behaviour_signals' => [
                'signals' => (clone $signals)->count(),
                'customers' => (clone $signals)->distinct('user_id')->count('user_id'),
                'by_source' => (clone $signals)
                    ->select('source_type', DB::raw('COUNT(*) as signals'), DB::raw('COUNT(DISTINCT user_id) as customers'))
                    ->groupBy('source_type')
                    ->orderBy('source_type')
                    ->get(),
            ],
            'privacy_rule' => 'This report exposes aggregate provenance and coverage, not raw customer transaction histories.',
        ];
    }

    private function period(Carbon $start, Carbon $end): array
    {
        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }
}
