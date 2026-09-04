<?php

namespace App\Services;

use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LongRangeIntegrityService
{
    public function scan(): array
    {
        $findings = [];

        if (Schema::hasTable('financial_action_intents') && Schema::hasTable('mobile_money_transactions')) {
            $settledWithoutProviderFinality = DB::table('financial_action_intents as i')
                ->leftJoin('mobile_money_transactions as m', 'm.internal_reference', '=', DB::raw('CAST(i.reference AS TEXT)'))
                ->where('i.status', 'settled')
                ->groupBy('i.id', 'i.reference')
                ->havingRaw('SUM(CASE WHEN m.status = ? THEN 1 ELSE 0 END) = 0', [MobileMoneyTransaction::STATUS_SUCCESSFUL])
                ->get(['i.reference']);

            foreach ($settledWithoutProviderFinality as $item) {
                $findings[] = $this->finding('critical', 'long_range_false_settlement', $item->reference,
                    'A long-range financial intent is marked settled without successful CPay/provider finality.', ['intent_reference' => $item->reference]);
            }

            $providerSuccessNotApplied = DB::table('financial_action_intents as i')
                ->join('mobile_money_transactions as m', 'm.internal_reference', '=', DB::raw('CAST(i.reference AS TEXT)'))
                ->where('m.status', MobileMoneyTransaction::STATUS_SUCCESSFUL)
                ->where('i.status', '!=', 'settled')
                ->where('m.updated_at', '<=', now()->subMinutes(10))
                ->get(['i.reference', 'i.status as intent_status', 'm.provider_reference']);

            foreach ($providerSuccessNotApplied as $item) {
                $findings[] = $this->finding('high', 'long_range_settlement_lag', $item->reference,
                    'CPay/provider success has not been reflected in the long-range financial intent within the control window.', [
                        'intent_status' => $item->intent_status,
                        'provider_reference' => $item->provider_reference,
                    ]);
            }
        }

        if (Schema::hasTable('participatory_finance_listings') && Schema::hasTable('participatory_finance_commitments')) {
            $listings = DB::table('participatory_finance_listings as l')
                ->leftJoin('participatory_finance_commitments as c', 'c.listing_id', '=', 'l.id')
                ->select('l.id', 'l.reference', 'l.target_amount_minor', 'l.funded_amount_minor')
                ->selectRaw("COALESCE(SUM(CASE WHEN c.status = 'settled' THEN c.amount_minor ELSE 0 END), 0) as settled_commitments_minor")
                ->selectRaw("COALESCE(SUM(CASE WHEN c.status = 'awaiting_step_up' THEN c.amount_minor ELSE 0 END), 0) as reserved_commitments_minor")
                ->groupBy('l.id', 'l.reference', 'l.target_amount_minor', 'l.funded_amount_minor')
                ->get();

            foreach ($listings as $listing) {
                $funded = (int) $listing->funded_amount_minor;
                $settled = (int) $listing->settled_commitments_minor;
                $reserved = (int) $listing->reserved_commitments_minor;
                $target = (int) $listing->target_amount_minor;

                if ($funded !== $settled) {
                    $findings[] = $this->finding('critical', 'participatory_funding_mismatch', $listing->reference,
                        'Participatory listing funded amount does not equal settled commitments.', [
                            'funded_amount_minor' => $funded,
                            'settled_commitments_minor' => $settled,
                        ]);
                }
                if ($funded > $target) {
                    $findings[] = $this->finding('critical', 'participatory_overfunded', $listing->reference,
                        'Participatory listing is funded above its approved target.', [
                            'target_amount_minor' => $target,
                            'funded_amount_minor' => $funded,
                        ]);
                }
                if ($funded + $reserved > $target) {
                    $findings[] = $this->finding('critical', 'participatory_over_reserved', $listing->reference,
                        'Settled funding plus active payment reservations exceed the approved target.', [
                            'target_amount_minor' => $target,
                            'funded_amount_minor' => $funded,
                            'reserved_commitments_minor' => $reserved,
                            'combined_minor' => $funded + $reserved,
                        ]);
                }
            }
        }

        if (Schema::hasTable('referral_events') && Schema::hasTable('reward_ledger_entries')) {
            $referrals = DB::table('referral_events as r')
                ->leftJoin('reward_ledger_entries as e', 'e.referral_event_id', '=', 'r.id')
                ->where('r.status', 'rewarded')
                ->select('r.id', 'r.reference', 'r.reward_minor')
                ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'credit' AND e.status = 'posted' THEN e.amount_minor ELSE 0 END), 0) as ledger_reward_minor")
                ->groupBy('r.id', 'r.reference', 'r.reward_minor')
                ->get();

            foreach ($referrals as $referral) {
                if ((int) $referral->reward_minor !== (int) $referral->ledger_reward_minor) {
                    $findings[] = $this->finding('critical', 'referral_reward_ledger_mismatch', $referral->reference,
                        'Referral reward state does not agree with the controlled reward ledger.', [
                            'reward_minor' => (int) $referral->reward_minor,
                            'ledger_reward_minor' => (int) $referral->ledger_reward_minor,
                        ]);
                }
            }
        }

        if (Schema::hasTable('asset_finance_requests') && Schema::hasTable('financial_action_intents')) {
            $assetDepositsWithoutSettlement = DB::table('asset_finance_requests as a')
                ->leftJoin('financial_action_intents as i', function ($join) {
                    $join->on('i.source_id', '=', 'a.id')->where('i.source_type', '=', 'asset_finance_deposit')->where('i.status', '=', 'settled');
                })
                ->where('a.status', 'deposit_settled')
                ->whereNull('i.id')
                ->get(['a.reference']);

            foreach ($assetDepositsWithoutSettlement as $asset) {
                $findings[] = $this->finding('critical', 'asset_deposit_false_settlement', $asset->reference,
                    'Asset-finance deposit is marked settled without a settled governed financial intent.', ['asset_reference' => $asset->reference]);
            }
        }

        return $findings;
    }

    private function finding(string $severity, string $type, ?string $reference, string $description, array $evidence): array
    {
        return compact('severity', 'type', 'reference', 'description', 'evidence');
    }
}
