<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LongRangePlatformService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function overview(User $user): array
    {
        return [
            'linked_accounts' => DB::table('linked_financial_accounts')->where('user_id', $user->id)->latest()->get(),
            'household' => DB::table('household_finance_profiles')->where('user_id', $user->id)->first(),
            'microbusiness' => DB::table('microbusiness_profiles')->where('user_id', $user->id)->first(),
            'asset_finance' => DB::table('asset_finance_requests')->where('user_id', $user->id)->latest()->get(),
            'community_memberships' => DB::table('community_finance_memberships')->where('user_id', $user->id)->latest()->get(),
            'participatory_listings' => DB::table('participatory_finance_listings')->where('borrower_user_id', $user->id)->latest()->get(),
            'participatory_commitments' => DB::table('participatory_finance_commitments')->where('investor_user_id', $user->id)->latest()->get(),
            'referrals' => DB::table('referral_events')->where('referrer_user_id', $user->id)->latest()->get(),
            'offline_sync' => DB::table('offline_sync_batches')->where('user_id', $user->id)->latest()->limit(20)->get(),
            'capital_mandates' => DB::table('capital_mandates')->where('owner_user_id', $user->id)->latest()->get(),
            'capabilities' => config('opfin.capabilities'),
        ];
    }

    public function linkAccount(User $user, array $data): object
    {
        $identifier = preg_replace('/\s+/', '', (string) $data['identifier']);
        $masked = strlen($identifier) > 4 ? str_repeat('•', max(0, strlen($identifier) - 4)).substr($identifier, -4) : $identifier;
        $id = DB::table('linked_financial_accounts')->insertGetId([
            'user_id' => $user->id,
            'account_type' => $data['account_type'],
            'provider' => $data['provider'],
            'masked_identifier' => $masked,
            'consent_reference' => $data['consent_reference'] ?? null,
            'status' => 'pending_verification',
            'data_confidence' => 'user_declared',
            'metadata' => json_encode(['link_method' => $data['link_method'] ?? 'provider_authorisation']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.linked_account.created', $user, null, ['linked_account_id' => $id, 'provider' => $data['provider']]);
        return DB::table('linked_financial_accounts')->find($id);
    }

    public function saveHousehold(User $user, array $data): object
    {
        DB::table('household_finance_profiles')->updateOrInsert(['user_id' => $user->id], [
            'household_size' => $data['household_size'],
            'monthly_income_minor' => $data['monthly_income_minor'] ?? null,
            'monthly_fixed_costs_minor' => $data['monthly_fixed_costs_minor'] ?? null,
            'emergency_target_minor' => $data['emergency_target_minor'] ?? null,
            'shared_goals' => json_encode($data['shared_goals'] ?? []),
            'dependants' => json_encode($data['dependants'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.household.updated', $user);
        return DB::table('household_finance_profiles')->where('user_id', $user->id)->first();
    }

    public function saveMicrobusiness(User $user, array $data): object
    {
        DB::table('microbusiness_profiles')->updateOrInsert(['user_id' => $user->id], [
            'business_name' => $data['business_name'],
            'business_type' => $data['business_type'],
            'registration_reference' => $data['registration_reference'] ?? null,
            'monthly_revenue_minor' => $data['monthly_revenue_minor'] ?? null,
            'monthly_expense_minor' => $data['monthly_expense_minor'] ?? null,
            'operating_data' => json_encode($data['operating_data'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.microbusiness.updated', $user);
        return DB::table('microbusiness_profiles')->where('user_id', $user->id)->first();
    }

    public function requestAssetFinance(User $user, array $data): object
    {
        $assetPrice = (int) $data['asset_price_minor'];
        $deposit = (int) ($data['deposit_minor'] ?? 0);
        if ($assetPrice <= 0 || $deposit < 0 || $deposit >= $assetPrice) {
            throw ValidationException::withMessages(['deposit_minor' => ['Deposit must be non-negative and strictly less than the asset price.']]);
        }

        $reference = (string) Str::uuid();
        $id = DB::table('asset_finance_requests')->insertGetId([
            'reference' => $reference,
            'user_id' => $user->id,
            'asset_category' => $data['asset_category'],
            'asset_description' => $data['asset_description'],
            'asset_price_minor' => $assetPrice,
            'deposit_minor' => $deposit,
            'requested_term_months' => $data['requested_term_months'],
            'status' => 'submitted',
            'geolocation_consent' => (bool) ($data['geolocation_consent'] ?? false),
            'decision_evidence' => json_encode([
                'decision_state' => 'pending_assessment',
                'privacy_rule' => 'geolocation_is_optional_and_purpose_bound',
                'money_movement' => 'cpay_only_after_offer_acceptance',
                'maximum_finance_minor' => $assetPrice - $deposit,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.asset_finance.requested', $user, null, [
            'reference' => $reference,
            'asset_price_minor' => $assetPrice,
            'deposit_minor' => $deposit,
            'maximum_finance_minor' => $assetPrice - $deposit,
        ]);
        return DB::table('asset_finance_requests')->find($id);
    }

    public function joinCommunity(User $user, array $data): object
    {
        $reference = (string) Str::uuid();
        $id = DB::table('community_finance_memberships')->insertGetId([
            'reference' => $reference,
            'user_id' => $user->id,
            'institution_type' => $data['institution_type'],
            'institution_name' => $data['institution_name'],
            'member_reference' => $data['member_reference'] ?? null,
            'status' => 'pending_verification',
            'permissions' => json_encode($data['permissions'] ?? ['membership_verification']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.community.membership_created', $user, null, ['reference' => $reference, 'institution_type' => $data['institution_type']]);
        return DB::table('community_finance_memberships')->find($id);
    }

    public function createParticipatoryListing(User $user, array $data): object
    {
        $reference = (string) Str::uuid();
        $id = DB::table('participatory_finance_listings')->insertGetId([
            'reference' => $reference,
            'borrower_user_id' => $user->id,
            'purpose' => $data['purpose'],
            'target_amount_minor' => $data['target_amount_minor'],
            'funded_amount_minor' => 0,
            'term_days' => $data['term_days'],
            'status' => 'awaiting_compliance_review',
            'lender_of_record' => $data['lender_of_record'] ?? null,
            'disclosures' => json_encode([
                'loss_allocation' => $data['loss_allocation'] ?? null,
                'fees' => $data['fees'] ?? null,
                'guarantee' => $data['guarantee'] ?? null,
                'custody' => $data['custody'] ?? null,
                'regulatory_review_required' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.participatory.listing_created', $user, null, ['reference' => $reference]);
        return DB::table('participatory_finance_listings')->find($id);
    }

    public function commitParticipatory(User $user, array $data): object
    {
        return DB::transaction(function () use ($user, $data) {
            $listing = DB::table('participatory_finance_listings')->where('id', $data['listing_id'])->lockForUpdate()->first();
            if (! $listing || ! in_array($listing->status, ['approved', 'funding'], true)) {
                throw ValidationException::withMessages(['listing_id' => ['Listing is not open for funding.']]);
            }
            if ((int) $listing->borrower_user_id === (int) $user->id) {
                throw ValidationException::withMessages(['listing_id' => ['You cannot fund your own listing.']]);
            }

            $reservedMinor = (int) DB::table('participatory_finance_commitments')
                ->where('listing_id', $listing->id)
                ->where('status', 'awaiting_step_up')
                ->sum('amount_minor');
            $remainingMinor = (int) $listing->target_amount_minor - (int) $listing->funded_amount_minor - $reservedMinor;
            $amountMinor = (int) $data['amount_minor'];
            if ($amountMinor <= 0 || $amountMinor > $remainingMinor) {
                throw ValidationException::withMessages(['amount_minor' => ['Commitment exceeds the unreserved remaining funding amount.']]);
            }

            $reference = (string) Str::uuid();
            $id = DB::table('participatory_finance_commitments')->insertGetId([
                'reference' => $reference,
                'listing_id' => $listing->id,
                'investor_user_id' => $user->id,
                'amount_minor' => $amountMinor,
                'status' => 'awaiting_step_up',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->auditLogger->record('long_range.participatory.commitment_created', $user, null, [
                'reference' => $reference,
                'listing_reference' => $listing->reference,
                'step_up_required' => true,
                'reserved_amount_minor' => $amountMinor,
                'remaining_unreserved_minor' => $remainingMinor - $amountMinor,
            ]);
            return DB::table('participatory_finance_commitments')->find($id);
        });
    }

    public function createReferral(User $user, array $data): object
    {
        $code = strtoupper(substr(hash('sha256', $user->id.'|'.$user->phone), 0, 10));
        $reference = (string) Str::uuid();
        $id = DB::table('referral_events')->insertGetId([
            'reference' => $reference,
            'referrer_user_id' => $user->id,
            'referred_user_id' => $data['referred_user_id'] ?? null,
            'referral_code' => $code,
            'event_type' => $data['event_type'] ?? 'invited',
            'status' => 'pending',
            'reward_minor' => 0,
            'abuse_checks' => json_encode([
                'self_referral_blocked' => true,
                'device_reuse_check' => 'required_before_reward',
                'identity_uniqueness_check' => 'required_before_reward',
                'reward_posting' => 'ledger_only_after_eligibility',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.referral.created', $user, null, ['reference' => $reference]);
        return DB::table('referral_events')->find($id);
    }

    public function syncOffline(User $user, array $data): object
    {
        $canonical = json_encode($data['events'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $canonical);
        $existing = DB::table('offline_sync_batches')->where('batch_reference', $data['batch_reference'])->first();
        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id || $existing->device_reference !== $data['device_reference'] || $existing->payload_hash !== $hash) {
                throw ValidationException::withMessages(['batch_reference' => ['Batch reference was already used for a different user, device or payload.']]);
            }
            return $existing;
        }

        $conflicts = collect($data['events'])->filter(fn ($event) => ! isset($event['event_id']) || ! isset($event['occurred_at']))->values()->all();
        $id = DB::table('offline_sync_batches')->insertGetId([
            'batch_reference' => $data['batch_reference'],
            'user_id' => $user->id,
            'device_reference' => $data['device_reference'],
            'status' => count($conflicts) ? 'needs_review' : 'processed',
            'event_count' => count($data['events']),
            'payload_hash' => $hash,
            'events' => $canonical,
            'conflicts' => json_encode($conflicts),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.offline_sync.processed', $user, null, ['batch_reference' => $data['batch_reference'], 'event_count' => count($data['events'])]);
        return DB::table('offline_sync_batches')->find($id);
    }

    public function createCapitalMandate(User $user, array $data): object
    {
        $reference = (string) Str::uuid();
        $id = DB::table('capital_mandates')->insertGetId([
            'reference' => $reference,
            'owner_user_id' => $user->id,
            'mandate_type' => $data['mandate_type'],
            'name' => $data['name'],
            'committed_capital_minor' => $data['committed_capital_minor'] ?? 0,
            'deployed_capital_minor' => 0,
            'status' => 'awaiting_compliance_review',
            'investment_policy' => json_encode($data['investment_policy'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.capital.mandate_created', $user, null, ['reference' => $reference]);
        return DB::table('capital_mandates')->find($id);
    }

    public function createPartner(User $user, array $data): object
    {
        $reference = (string) Str::uuid();
        $id = DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => $reference,
            'created_by' => $user->id,
            'partner_name' => $data['partner_name'],
            'partner_type' => $data['partner_type'],
            'status' => 'pending_due_diligence',
            'allowed_products' => json_encode($data['allowed_products'] ?? []),
            'commercial_terms' => json_encode($data['commercial_terms'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditLogger->record('long_range.partner.created', $user, null, ['reference' => $reference, 'partner_type' => $data['partner_type']]);
        return DB::table('partner_distribution_accounts')->find($id);
    }

    public function ussd(string $sessionId, string $phone, string $text): array
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone);
        $user = User::query()->where('phone', $normalizedPhone)->first();
        $session = DB::table('ussd_sessions')->where('session_id', $sessionId)->first();
        if (! $session) {
            DB::table('ussd_sessions')->insert([
                'session_id' => $sessionId,
                'user_id' => $user?->id,
                'phone' => $normalizedPhone,
                'state' => 'menu',
                'context' => json_encode([]),
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $input = trim($text);
        if ($input === '') {
            return ['continue' => true, 'message' => "Welcome to OpFin\n1. Money status\n2. Borrow\n3. Save\n4. Support\n5. Security"];
        }
        if (! $user) {
            return ['continue' => false, 'message' => 'This phone is not registered with OpFin.'];
        }

        $choice = collect(explode('*', $input))->last();
        return match ((string) $choice) {
            '1' => ['continue' => false, 'message' => 'Money status is available in your OpFin account. No balance is treated as confirmed unless provider evidence exists.'],
            '2' => ['continue' => false, 'message' => 'Borrowing requires secure authentication. Continue in OpFin or WhatsApp after verification.'],
            '3' => ['continue' => false, 'message' => 'Savings instructions require secure authentication and provider confirmation.'],
            '4' => ['continue' => false, 'message' => 'Support: use OpFin Support or verified WhatsApp to create a traceable case.'],
            '5' => ['continue' => false, 'message' => 'Security changes require step-up authentication. Open Security Centre in OpFin.'],
            default => ['continue' => false, 'message' => 'Invalid choice. Dial again to restart.'],
        };
    }
}
