<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LongRangeGovernanceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function dashboard(): array
    {
        $linked = DB::table('linked_financial_accounts')->where('status', 'pending_verification')->latest('id')->limit(50)->get();
        $assets = DB::table('asset_finance_requests')->where('status', 'submitted')->latest('id')->limit(50)->get();
        $community = DB::table('community_finance_memberships')->where('status', 'pending_verification')->latest('id')->limit(50)->get();
        $participatory = DB::table('participatory_finance_listings')->where('status', 'awaiting_compliance_review')->latest('id')->limit(50)->get();
        $capital = DB::table('capital_mandates')->where('status', 'awaiting_compliance_review')->latest('id')->limit(50)->get();
        $partners = DB::table('partner_distribution_accounts')->where('status', 'pending_due_diligence')->latest('id')->limit(50)->get();
        $referrals = DB::table('referral_events')->where('status', 'pending')->latest('id')->limit(50)->get();
        $offline = DB::table('offline_sync_batches')->where('status', 'needs_review')->latest('id')->limit(50)->get();

        return [
            'linked_accounts_pending' => DB::table('linked_financial_accounts')->where('status', 'pending_verification')->count(),
            'asset_finance_pending' => DB::table('asset_finance_requests')->where('status', 'submitted')->count(),
            'community_memberships_pending' => DB::table('community_finance_memberships')->where('status', 'pending_verification')->count(),
            'participatory_listings_pending' => DB::table('participatory_finance_listings')->where('status', 'awaiting_compliance_review')->count(),
            'capital_mandates_pending' => DB::table('capital_mandates')->where('status', 'awaiting_compliance_review')->count(),
            'partners_pending' => DB::table('partner_distribution_accounts')->where('status', 'pending_due_diligence')->count(),
            'referrals_pending' => DB::table('referral_events')->where('status', 'pending')->count(),
            'financial_intents_awaiting_step_up' => DB::table('financial_action_intents')->where('status', 'awaiting_step_up')->count(),
            'financial_intents_processing' => DB::table('financial_action_intents')->where('status', 'provider_processing')->count(),
            'offline_conflicts' => DB::table('offline_sync_batches')->where('status', 'needs_review')->count(),
            'queues' => [
                'linked_accounts' => $linked,
                'asset_finance' => $assets,
                'community_memberships' => $community,
                'participatory_listings' => $participatory,
                'capital_mandates' => $capital,
                'partners' => $partners,
                'referrals' => $referrals,
                'offline_conflicts' => $offline,
            ],
            'external_activation_gates' => collect(config('opfin.capabilities'))
                ->filter(fn ($capability) => isset($capability['external_gate']))
                ->map(fn ($capability, $code) => ['capability' => $code, 'gate' => $capability['external_gate']])
                ->values(),
        ];
    }

    public function verifyLinkedAccount(User $actor, int $id, array $data): object
    {
        $record = DB::table('linked_financial_accounts')->find($id);
        $this->assertRecord($record, 'Linked account');
        if ((int) $record->user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['id' => ['Maker-checker requires a different verifier.']]);
        }
        DB::table('linked_financial_accounts')->where('id', $id)->update([
            'status' => $data['status'],
            'data_confidence' => $data['status'] === 'verified' ? 'provider_confirmed' : 'user_declared',
            'verified_at' => $data['status'] === 'verified' ? now() : null,
            'last_synced_at' => $data['status'] === 'verified' ? now() : $record->last_synced_at,
            'metadata' => json_encode(array_merge((array) json_decode($record->metadata ?? '{}', true), ['verification_evidence' => $data['evidence'] ?? null, 'verified_by' => $actor->id])),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.linked_account.reviewed', $actor, null, ['linked_account_id' => $id, 'status' => $data['status']]);

        return DB::table('linked_financial_accounts')->find($id);
    }

    public function decideAsset(User $actor, int $id, array $data): object
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $record = DB::table('asset_finance_requests')->where('id', $id)->lockForUpdate()->first();
            $this->assertRecord($record, 'Asset finance request');
            if ((int) $record->user_id === (int) $actor->id) {
                throw ValidationException::withMessages(['id' => ['Requester cannot approve their own asset-finance request.']]);
            }
            if (! in_array($record->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['status' => ['Request is no longer decisionable.']]);
            }

            $assetPrice = (int) $record->asset_price_minor;
            $deposit = (int) $record->deposit_minor;
            $maximumFinance = $assetPrice - $deposit;
            if ($assetPrice <= 0 || $deposit < 0 || $deposit >= $assetPrice || $maximumFinance <= 0) {
                throw ValidationException::withMessages(['asset' => ['Asset price and deposit do not form a valid financeable amount.']]);
            }

            $approvedAmount = array_key_exists('approved_amount_minor', $data) && $data['approved_amount_minor'] !== null
                ? (int) $data['approved_amount_minor']
                : null;
            if ($data['status'] === 'approved') {
                if ($approvedAmount === null || $approvedAmount <= 0) {
                    throw ValidationException::withMessages(['approved_amount_minor' => ['Approved asset finance requires a positive approved amount.']]);
                }
                if ($approvedAmount > $maximumFinance) {
                    throw ValidationException::withMessages(['approved_amount_minor' => ['Approved finance amount cannot exceed asset price less deposit.']]);
                }
            }

            DB::table('asset_finance_requests')->where('id', $id)->update([
                'status' => $data['status'],
                'decision_evidence' => json_encode([
                    'reason' => $data['reason'],
                    'approved_amount_minor' => $approvedAmount,
                    'pricing' => $data['pricing'] ?? null,
                    'asset_price_minor' => $assetPrice,
                    'deposit_minor' => $deposit,
                    'maximum_finance_minor' => $maximumFinance,
                    'privacy_rule' => 'geolocation_remains_optional_and_purpose_bound',
                    'money_movement' => 'cpay_only_after_customer_step_up',
                ]),
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->record('long_range.asset_finance.decided', $actor, null, [
                'reference' => $record->reference,
                'status' => $data['status'],
                'approved_amount_minor' => $approvedAmount,
                'maximum_finance_minor' => $maximumFinance,
            ]);

            return DB::table('asset_finance_requests')->find($id);
        });
    }

    public function verifyCommunity(User $actor, int $id, array $data): object
    {
        $record = DB::table('community_finance_memberships')->find($id);
        $this->assertRecord($record, 'Community membership');
        if ((int) $record->user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['id' => ['Member cannot verify their own membership.']]);
        }
        DB::table('community_finance_memberships')->where('id', $id)->update([
            'status' => $data['status'],
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'permissions' => json_encode(array_merge((array) json_decode($record->permissions ?? '[]', true), ['verification_evidence' => $data['evidence'] ?? null])),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.community.membership_reviewed', $actor, null, ['reference' => $record->reference, 'status' => $data['status']]);

        return DB::table('community_finance_memberships')->find($id);
    }

    public function approveParticipatory(User $actor, int $id, array $data): object
    {
        $record = DB::table('participatory_finance_listings')->find($id);
        $this->assertRecord($record, 'Peer-lending request');
        if ((int) $record->borrower_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['id' => ['Borrower cannot approve their own marketplace request.']]);
        }

        $disclosures = (array) json_decode($record->disclosures ?? '{}', true);
        $lenderOfRecord = $record->lender_of_record;

        if ($data['status'] === 'approved') {
            $lenderOfRecord = $data['lender_of_record'];
            $disclosures = array_merge($disclosures, [
                'loss_allocation' => $data['loss_allocation'],
                'fees' => $data['fees'],
                'guarantee' => $data['guarantee'] ?? null,
                'custody' => $data['custody'],
                'expected_return_percent' => (float) $data['expected_return_percent'],
                'risk_grade' => $data['risk_grade'],
                'risk_summary' => $data['risk_summary'] ?? null,
                'borrower_summary' => $data['borrower_summary'],
                'repayment_frequency' => $data['repayment_frequency'],
                'regulatory_review_required' => true,
                'reviewed_by' => $actor->id,
            ]);

            if (empty($lenderOfRecord)) {
                throw ValidationException::withMessages(['lender_of_record' => ['A lender of record is required before approval.']]);
            }
            if (collect(['loss_allocation', 'fees', 'custody', 'risk_grade', 'borrower_summary', 'repayment_frequency'])->contains(fn ($key) => empty($disclosures[$key]))) {
                throw ValidationException::withMessages(['disclosures' => ['Complete risk, repayment, loss, fee, custody and borrower disclosures are required before approval.']]);
            }
        }

        DB::table('participatory_finance_listings')->where('id', $id)->update([
            'status' => $data['status'] === 'approved' ? 'funding' : 'rejected',
            'lender_of_record' => $lenderOfRecord,
            'disclosures' => json_encode($disclosures),
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.participatory.listing_reviewed', $actor, null, [
            'reference' => $record->reference,
            'status' => $data['status'],
            'lender_of_record' => $lenderOfRecord,
            'risk_grade' => $disclosures['risk_grade'] ?? null,
            'expected_return_percent' => $disclosures['expected_return_percent'] ?? null,
        ]);

        return DB::table('participatory_finance_listings')->find($id);
    }

    public function approveCapital(User $actor, int $id, array $data): object
    {
        $record = DB::table('capital_mandates')->find($id);
        $this->assertRecord($record, 'Capital mandate');
        if ((int) $record->owner_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['id' => ['Mandate owner cannot approve their own mandate.']]);
        }
        if ($data['status'] === 'approved' && empty((array) json_decode($record->investment_policy ?? '[]', true))) {
            throw ValidationException::withMessages(['investment_policy' => ['An investment policy is required before approval.']]);
        }
        DB::table('capital_mandates')->where('id', $id)->update([
            'status' => $data['status'],
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.capital.mandate_reviewed', $actor, null, ['reference' => $record->reference, 'status' => $data['status']]);

        return DB::table('capital_mandates')->find($id);
    }

    public function approvePartner(User $actor, int $id, array $data): object
    {
        $record = DB::table('partner_distribution_accounts')->find($id);
        $this->assertRecord($record, 'Partner');
        if ((int) $record->created_by === (int) $actor->id) {
            throw ValidationException::withMessages(['id' => ['Partner maker cannot approve the same partner record.']]);
        }
        if ($data['status'] === 'approved' && empty((array) json_decode($record->allowed_products ?? '[]', true))) {
            throw ValidationException::withMessages(['allowed_products' => ['At least one allowed product is required before approval.']]);
        }
        DB::table('partner_distribution_accounts')->where('id', $id)->update([
            'status' => $data['status'],
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.partner.reviewed', $actor, null, ['reference' => $record->reference, 'status' => $data['status']]);

        return DB::table('partner_distribution_accounts')->find($id);
    }

    public function approveReferralReward(User $actor, int $id, int $rewardMinor): object
    {
        return DB::transaction(function () use ($actor, $id, $rewardMinor) {
            $record = DB::table('referral_events')->where('id', $id)->lockForUpdate()->first();
            $this->assertRecord($record, 'Referral event');
            if ((int) $record->referrer_user_id === (int) $actor->id) {
                throw ValidationException::withMessages(['id' => ['Referrer cannot approve their own reward.']]);
            }
            if (! $record->referred_user_id || $record->event_type !== 'eligible') {
                throw ValidationException::withMessages(['id' => ['Reward requires an eligible, identity-linked referred user.']]);
            }
            if ($record->status === 'rewarded') {
                return $record;
            }
            if ($rewardMinor <= 0 || $rewardMinor > 10000000) {
                throw ValidationException::withMessages(['reward_minor' => ['Reward amount must be positive and within the configured control limit.']]);
            }

            DB::table('referral_events')->where('id', $id)->update(['status' => 'rewarded', 'reward_minor' => $rewardMinor, 'updated_at' => now()]);
            DB::table('reward_ledger_entries')->insert([
                'reference' => (string) Str::uuid(),
                'user_id' => $record->referrer_user_id,
                'referral_event_id' => $record->id,
                'direction' => 'credit',
                'amount_minor' => $rewardMinor,
                'reason' => 'eligible_referral_reward',
                'status' => 'posted',
                'approved_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->record('long_range.referral.reward_posted', $actor, null, ['reference' => $record->reference, 'reward_minor' => $rewardMinor]);

            return DB::table('referral_events')->find($id);
        });
    }

    private function assertRecord(?object $record, string $label): void
    {
        if (! $record) {
            throw ValidationException::withMessages(['id' => ["{$label} was not found."]]);
        }
    }
}
