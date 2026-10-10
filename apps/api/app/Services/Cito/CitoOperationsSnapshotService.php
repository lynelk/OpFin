<?php

namespace App\Services\Cito;

use Illuminate\Support\Facades\DB;

class CitoOperationsSnapshotService
{
    /**
     * Locally recorded operational evidence only. Does not purport to prove
     * external provider settlement, actual charged rates or live entitlement.
     */
    public function snapshot(): array
    {
        $start = now('UTC')->startOfDay();
        $environment = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        $billing = DB::table('cito_baas_operation_intents')->where('environment', $environment);
        $counts = (clone $billing)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
        $economics = DB::table('service_economics_events')
            ->where('route', 'CITO_MANAGED')
            ->where('environment', $environment)
            ->where('occurred_at', '>=', $start)
            ->where('currency', 'UGX');
        $cost = (int) (clone $economics)->sum('provider_net_cost_minor');
        $unpriced = (clone $economics)->whereNull('provider_net_cost_minor')->count();
        $limit = (int) config('services.cito.daily_cost_alert_minor', 0);

        return [
            'environment' => $environment,
            'observed_at' => now('UTC')->toIso8601String(),
            'financial_finality_certified' => false,
            'billing' => [
                'intent_counts' => $counts,
                'daily_write_reservations' => app(CitoBaasWriteBudgetService::class)->today(),
            ],
            'recorded_provider_costs' => [
                'currency' => 'UGX',
                'net_cost_minor_today' => $cost,
                'unpriced_events_today' => $unpriced,
                'alert_threshold_minor' => $limit,
                'alert' => $limit < 1 ? 'UNCONFIGURED' : ($cost >= $limit ? 'THRESHOLD_REACHED' : 'BELOW_THRESHOLD'),
            ],
            'release_controls' => [
                'payments_feature_enabled' => app(CitoFeatureGate::class)->enabled('payments'),
                'billing_feature_enabled' => app(CitoFeatureGate::class)->enabled('billing'),
                'whatsapp_otp_feature_enabled' => app(CitoFeatureGate::class)->enabled('otp_whatsapp'),
                'ussd_external_cito_contract_verified' => false,
                'external_provider_certification' => 'REQUIRES_INDEPENDENT_EVIDENCE',
            ],
        ];
    }
}
