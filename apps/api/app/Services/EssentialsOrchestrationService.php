<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CustomerWallet;
use App\Models\CreditProfile;
use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsBiller;
use App\Models\EssentialsCreditLine;
use App\Models\EssentialsQuote;
use App\Models\EssentialsPartnerAuthorisation;
use App\Models\EssentialsRepayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class EssentialsOrchestrationService
{
    private const LENDER_PARTNER_TYPES = ['lender', 'bank', 'mfi', 'sacco', 'credit_provider', 'financial_institution'];

    public function __construct(
        private readonly CpayEssentialsClient $cpay,
        private readonly CitoEssentialsLendingClient $citoLending,
        private readonly FinancialPolicyService $policies,
        private readonly ServiceEconomicsService $economics,
        private readonly AuditLogger $audit,
    ) {}

    public function summary(User $user): array
    {
        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $outstanding = (int) EssentialsAdvance::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['funding_reserved', 'fulfilment_pending', 'active', 'overdue'])
            ->sum('outstanding_minor');

        $maxLine = (int) EssentialsCreditLine::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->max('available_limit_minor');

        $profileHeadroom = max(0, (int) ($profile?->available_to_borrow_minor ?? 0));
        $overallAvailable = min($maxLine, $profileHeadroom);

        $accounts = EssentialsAccount::query()
            ->with('biller:id,code,name,category,account_label,status')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn (EssentialsAccount $account) => [
                'id' => $account->id,
                'public_id' => $account->public_id,
                'financial_space_id' => $account->financial_space_id,
                'biller' => $account->biller,
                'nickname' => $account->nickname,
                'reference_last4' => substr((string) $account->account_reference, -4),
                'verification_status' => $account->verification_status,
                'verified_at' => $account->verified_at?->toISOString(),
                'metadata' => $account->metadata,
            ]);

        $advances = EssentialsAdvance::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();

        return [
            'product' => [
                'name' => 'OpFin Essentials',
                'positioning' => 'Purpose-bound access to essential services through approved third-party lenders.',
                'opfin_role' => 'orchestrator_and_servicer',
                'opfin_is_primary_lender' => false,
            ],
            'overall_available_limit_minor' => $overallAvailable,
            'outstanding_minor' => $outstanding,
            'currency' => 'UGX',
            'accounts' => $accounts,
            'advances' => $advances,
            'profile_status' => $profile?->status ?? 'not_ready',
        ];
    }

    public function catalogue(): array
    {
        return [
            'billers' => EssentialsBiller::query()
                ->where('status', 'active')
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'category', 'account_label', 'route', 'metadata']),
            'categories' => [
                'electricity', 'water', 'internet', 'television', 'energy', 'rent',
            ],
            'platforms' => DB::table('partner_distribution_accounts')
                ->whereIn('status', ['active', 'approved'])
                ->orderBy('partner_name')
                ->get(['id', 'partner_name', 'partner_type', 'allowed_products'])
                ->filter(function ($partner) {
                    $allowed = $this->json($partner->allowed_products);
                    return in_array('essentials', array_map('strtolower', $allowed), true);
                })
                ->values()
                ->map(fn ($partner) => [
                    'id' => $partner->id,
                    'name' => $partner->partner_name,
                    'type' => $partner->partner_type,
                ]),
        ];
    }

    public function partnerAuthorisations(User $user): array
    {
        return EssentialsPartnerAuthorisation::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function (EssentialsPartnerAuthorisation $authorisation) {
                $partner = DB::table('partner_distribution_accounts')->where('id', $authorisation->partner_account_id)->first();

                return [
                    'id' => $authorisation->id,
                    'reference' => $authorisation->reference,
                    'partner_account_id' => $authorisation->partner_account_id,
                    'partner_name' => $partner?->partner_name,
                    'financial_space_id' => $authorisation->financial_space_id,
                    'scopes' => $authorisation->scopes,
                    'status' => $authorisation->status,
                    'granted_at' => $authorisation->granted_at?->toISOString(),
                    'expires_at' => $authorisation->expires_at?->toISOString(),
                    'revoked_at' => $authorisation->revoked_at?->toISOString(),
                ];
            })
            ->all();
    }

    public function authorisePartnerPlatform(User $user, int $partnerAccountId, ?int $financialSpaceId, array $scopes, int $validDays = 30): EssentialsPartnerAuthorisation
    {
        $spaceId = $this->resolveSpaceId($user, $financialSpaceId);
        $partner = $this->assertPartnerAccount($partnerAccountId, null);
        $allowedScopes = ['eligibility', 'account_write', 'quote_create', 'status_read'];
        $scopes = array_values(array_unique(array_intersect($allowedScopes, array_map('strtolower', $scopes))));
        if ($scopes === []) {
            throw new InvalidArgumentException('Choose at least one valid Essentials partner permission.');
        }

        EssentialsPartnerAuthorisation::query()
            ->where('user_id', $user->id)
            ->where('partner_account_id', $partnerAccountId)
            ->where('financial_space_id', $spaceId)
            ->where('status', 'active')
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        $authorisation = EssentialsPartnerAuthorisation::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'partner_account_id' => $partnerAccountId,
            'financial_space_id' => $spaceId,
            'scopes' => $scopes,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => now()->addDays(max(1, min($validDays, 90))),
            'metadata' => ['partner_name' => $partner->partner_name],
        ]);

        $this->audit->record('essentials.partner.authorised', $user, $authorisation, [
            'partner_account_id' => $partnerAccountId,
            'financial_space_id' => $spaceId,
            'scopes' => $scopes,
        ]);

        return $authorisation;
    }

    public function revokePartnerPlatform(User $user, int $authorisationId): EssentialsPartnerAuthorisation
    {
        $authorisation = EssentialsPartnerAuthorisation::query()
            ->where('user_id', $user->id)
            ->findOrFail($authorisationId);

        if ($authorisation->status === 'active') {
            $authorisation->update(['status' => 'revoked', 'revoked_at' => now()]);
            $this->audit->record('essentials.partner.revoked', $user, $authorisation);
        }

        return $authorisation->fresh();
    }

    public function assertPartnerCustomerAuthorised(User $customer, int $partnerAccountId, string $scope, ?int $financialSpaceId = null): EssentialsPartnerAuthorisation
    {
        $scope = strtolower(trim($scope));
        $query = EssentialsPartnerAuthorisation::query()
            ->where('user_id', $customer->id)
            ->where('partner_account_id', $partnerAccountId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($financialSpaceId) {
            $query->where('financial_space_id', $financialSpaceId);
        }

        $authorisation = $query->latest('granted_at')->first();
        if (! $authorisation || ! in_array($scope, array_map('strtolower', (array) $authorisation->scopes), true)) {
            throw new InvalidArgumentException('The customer has not authorised this platform for the requested Essentials action.');
        }

        return $authorisation;
    }

    public function createAccount(User $user, array $data): EssentialsAccount
    {
        $spaceId = $this->resolveSpaceId($user, isset($data['financial_space_id']) ? (int) $data['financial_space_id'] : null);
        $biller = EssentialsBiller::query()->whereKey($data['biller_id'])->where('status', 'active')->firstOrFail();
        $reference = trim((string) $data['account_reference']);
        if ($reference === '') {
            throw new InvalidArgumentException('An account reference is required.');
        }

        $metadata = (array) ($data['metadata'] ?? []);
        if ($biller->category === 'rent') {
            foreach (['landlord_name', 'beneficiary_name', 'beneficiary_channel'] as $required) {
                if (trim((string) ($metadata[$required] ?? '')) === '') {
                    throw new InvalidArgumentException("Rental finance requires {$required} before beneficiary verification.");
                }
            }
        }

        $hash = hash('sha256', strtoupper(preg_replace('/\s+/', '', $reference)));

        $account = EssentialsAccount::query()->firstOrCreate(
            ['user_id' => $user->id, 'biller_id' => $biller->id, 'account_reference_hash' => $hash],
            [
                'public_id' => (string) Str::uuid(),
                'financial_space_id' => $spaceId,
                'account_reference' => $reference,
                'nickname' => $data['nickname'] ?? null,
                'verification_status' => 'pending',
                'metadata' => $metadata,
            ],
        );

        if (! $account->wasRecentlyCreated) {
            $account->update([
                'financial_space_id' => $spaceId,
                'nickname' => $data['nickname'] ?? $account->nickname,
                'metadata' => array_merge((array) $account->metadata, $metadata),
            ]);
        }

        $this->audit->record('essentials.account.saved', $user, $account, ['biller_code' => $biller->code]);

        return $account->fresh();
    }

    public function verifyAccount(User $user, int $accountId): EssentialsAccount
    {
        $account = EssentialsAccount::query()->where('user_id', $user->id)->findOrFail($accountId);
        $biller = EssentialsBiller::query()->findOrFail($account->biller_id);

        if ($biller->route === 'manual_verification') {
            $account->update(['verification_status' => 'pending_manual_review']);
            return $account->fresh();
        }

        $result = $this->cpay->lookup($account);
        $status = strtoupper((string) ($result['status'] ?? $result['result'] ?? ''));
        $verified = in_array($status, ['PASS', 'VERIFIED', 'ACTIVE', 'SUCCESS', 'SUCCESSFUL'], true);
        $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? '');

        $account->update([
            'verification_status' => $verified ? 'verified' : 'failed',
            'provider_reference' => $providerReference !== '' ? $providerReference : null,
            'verified_at' => $verified ? now() : null,
            'metadata' => array_merge((array) $account->metadata, ['verification' => $this->redact($result)]),
        ]);

        $this->audit->record('essentials.account.verified', $user, $account, [
            'biller_code' => $biller->code,
            'verified' => $verified,
            'provider_reference' => $providerReference ?: null,
        ]);

        return $account->fresh();
    }

    public function refreshEligibility(User $user, ?int $financialSpaceId, string $channel = 'android'): array
    {
        $spaceId = $this->resolveSpaceId($user, $financialSpaceId);
        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        if (! $profile || ! in_array($profile->status, [CreditProfile::STATUS_READY, CreditProfile::STATUS_PROVISIONAL], true)) {
            throw new InvalidArgumentException('Complete or refresh your OpFin credit profile before checking Essentials eligibility.');
        }

        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();
        if (! $consent) {
            throw new InvalidArgumentException('Credit-processing consent is required before checking lender eligibility.');
        }

        $products = DB::table('partner_products as pp')
            ->join('partners as p', 'p.id', '=', 'pp.partner_id')
            ->where('pp.status', 'active')
            ->where('p.status', 'active')
            ->whereIn('p.partner_type', self::LENDER_PARTNER_TYPES)
            ->whereIn('pp.product_type', ['essentials_credit', 'utility_credit', 'rent_credit', 'sme_essentials_credit'])
            ->where('pp.country', strtoupper((string) config('opfin.default_country', 'UG')))
            ->select('pp.*', 'p.code as partner_code', 'p.name as partner_name', 'p.partner_type')
            ->get();

        $created = [];
        foreach ($products as $product) {
            if (strtolower((string) $product->partner_code) === 'opfin' || strcasecmp((string) $product->partner_name, 'OpFin') === 0) {
                continue;
            }

            $eligibility = $this->json($product->eligibility_rules);
            if ((float) $profile->composite_score < (float) ($eligibility['min_score'] ?? 0)) {
                continue;
            }
            if ((float) $profile->coverage_percent < (float) ($eligibility['min_coverage_percent'] ?? 0)) {
                continue;
            }

            $integration = $this->json($product->integration_config);
            $route = strtolower((string) ($integration['decision_route'] ?? 'capital_mandate'));
            $approvedLimit = 0;
            $decisionReference = null;
            $decisionSnapshot = [
                'opfin_profile_model_version' => $profile->model_version,
                'opfin_profile_score' => $profile->composite_score,
                'opfin_profile_coverage_percent' => $profile->coverage_percent,
                'lender' => $product->partner_name,
                'route' => $route,
            ];
            $fundingPoolId = isset($integration['funding_pool_id']) ? (int) $integration['funding_pool_id'] : null;

            if ($route === 'capital_mandate') {
                if (! $fundingPoolId) {
                    continue;
                }
                $pool = DB::table('capital_mandates')->where('id', $fundingPoolId)->first();
                if (! $pool || (int) $pool->partner_id !== (int) $product->partner_id || ! $pool->approved_at || strtolower((string) $pool->status) !== 'active') {
                    continue;
                }
                $poolAvailable = max(0, (int) $pool->committed_capital_minor - (int) $pool->deployed_capital_minor - (int) ($pool->reserved_capital_minor ?? 0));
                $approvedLimit = min(
                    (int) $profile->available_to_borrow_minor,
                    (int) ($eligibility['max_limit_minor'] ?? PHP_INT_MAX),
                    $poolAvailable,
                );
                $decisionReference = 'mandate:'.$pool->reference;
                $decisionSnapshot['funding_pool_reference'] = $pool->reference;
            } elseif ($route === 'cito') {
                $partner = (object) ['code' => $product->partner_code, 'name' => $product->partner_name];
                $result = $this->citoLending->requestCreditLine(
                    $user,
                    $profile,
                    $partner,
                    $product,
                    $spaceId,
                    'consent:'.$consent->id,
                );
                $status = strtoupper((string) ($result['status'] ?? ''));
                if (! in_array($status, ['APPROVED', 'PASS', 'ELIGIBLE'], true)) {
                    continue;
                }
                $approvedLimit = min(
                    (int) ($result['approvedLimitMinor'] ?? 0),
                    (int) $profile->available_to_borrow_minor,
                    (int) ($eligibility['max_limit_minor'] ?? PHP_INT_MAX),
                );
                $decisionReference = (string) ($result['providerReference'] ?? $result['reference'] ?? '');
                $decisionSnapshot['provider_result'] = $this->redact($result);
            } else {
                continue;
            }

            $minimum = (int) ($eligibility['min_limit_minor'] ?? 1);
            if ($approvedLimit < $minimum) {
                continue;
            }

            $line = EssentialsCreditLine::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'partner_product_id' => $product->id,
                    'financial_space_id' => $spaceId,
                ],
                [
                    'reference' => (string) Str::uuid(),
                    'lender_partner_id' => $product->partner_id,
                    'funding_pool_id' => $fundingPoolId,
                    'approved_limit_minor' => $approvedLimit,
                    'available_limit_minor' => max(0, $approvedLimit - (int) EssentialsAdvance::query()
                        ->where('user_id', $user->id)
                        ->where('lender_partner_id', $product->partner_id)
                        ->whereIn('status', ['funding_reserved', 'fulfilment_pending', 'active', 'overdue'])
                        ->sum('principal_outstanding_minor')),
                    'currency' => (string) $product->currency,
                    'status' => 'active',
                    'decision_route' => $route,
                    'decision_reference' => $decisionReference ?: null,
                    'decision_snapshot' => $decisionSnapshot,
                    'expires_at' => now()->addDays((int) ($eligibility['line_valid_days'] ?? 30)),
                ],
            );
            $created[] = $line;
        }

        return [
            'lines' => $created,
            'overall' => $this->summary($user),
            'channel' => $channel,
        ];
    }

    public function createQuote(User $user, array $data): EssentialsQuote
    {
        $account = EssentialsAccount::query()
            ->with('biller')
            ->where('user_id', $user->id)
            ->findOrFail((int) $data['essentials_account_id']);
        if ($account->verification_status !== 'verified') {
            throw new InvalidArgumentException('Verify the utility, service or rental beneficiary before requesting finance.');
        }

        $amount = (int) $data['amount_minor'];
        if ($amount <= 0) {
            throw new InvalidArgumentException('The finance amount must be positive.');
        }

        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $profileHeadroom = max(0, (int) ($profile?->available_to_borrow_minor ?? 0));
        if ($amount > $profileHeadroom) {
            throw new InvalidArgumentException('This request exceeds your current overall responsible-credit headroom.');
        }

        $channel = strtolower((string) ($data['channel'] ?? 'android'));
        $sourcePartnerAccountId = isset($data['source_partner_account_id']) ? (int) $data['source_partner_account_id'] : null;
        if ($sourcePartnerAccountId) {
            $this->assertPartnerAccount($sourcePartnerAccountId, $data['source_platform'] ?? null);
        }

        $lines = EssentialsCreditLine::query()
            ->where('user_id', $user->id)
            ->where('financial_space_id', $account->financial_space_id)
            ->where('status', 'active')
            ->where('available_limit_minor', '>=', $amount)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $best = null;
        foreach ($lines as $line) {
            $product = DB::table('partner_products')->where('id', $line->partner_product_id)->where('status', 'active')->first();
            $partner = DB::table('partners')->where('id', $line->lender_partner_id)->where('status', 'active')->first();
            if (! $product || ! $partner) {
                continue;
            }
            $eligibility = $this->json($product->eligibility_rules);
            $categories = array_map('strtolower', (array) ($eligibility['categories'] ?? []));
            if ($categories && ! in_array(strtolower((string) $account->biller->category), $categories, true)) {
                continue;
            }
            $pricing = $this->price($amount, $this->json($product->pricing), $channel);
            if (! $pricing) {
                continue;
            }
            $candidate = compact('line', 'product', 'partner', 'pricing');
            if (! $best || $pricing['total_repayment_minor'] < $best['pricing']['total_repayment_minor']) {
                $best = $candidate;
            }
        }

        if (! $best) {
            throw new InvalidArgumentException('No approved lender offer currently fits this amount, category and channel.');
        }

        $disclosures = [
            'product_family' => 'OpFin Essentials',
            'purpose_bound' => true,
            'cash_disbursement_to_customer' => false,
            'biller' => ['name' => $account->biller->name, 'category' => $account->biller->category],
            'lender' => ['name' => $best['partner']->name, 'partner_code' => $best['partner']->code],
            'opfin_role' => 'orchestrator_and_servicer_not_primary_lender',
            'principal_amount_minor' => $amount,
            'interest_minor' => $best['pricing']['interest_minor'],
            'fees_minor' => $best['pricing']['fees_minor'],
            'total_repayment_minor' => $best['pricing']['total_repayment_minor'],
            'term_days' => $best['pricing']['term_days'],
            'currency' => (string) $best['product']->currency,
            'channel' => $channel,
            'pricing_method' => $best['pricing']['method'],
            'regulatory_policy' => $best['pricing']['regulatory_policy'],
            'customer_confirmation_required' => true,
        ];
        $hash = hash('sha256', json_encode($disclosures, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $quote = EssentialsQuote::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $account->financial_space_id,
            'essentials_account_id' => $account->id,
            'credit_line_id' => $best['line']->id,
            'source_partner_account_id' => $sourcePartnerAccountId,
            'source_platform' => $data['source_platform'] ?? null,
            'purpose_category' => $account->biller->category,
            'amount_minor' => $amount,
            'interest_minor' => $best['pricing']['interest_minor'],
            'fees_minor' => $best['pricing']['fees_minor'],
            'total_repayment_minor' => $best['pricing']['total_repayment_minor'],
            'term_days' => $best['pricing']['term_days'],
            'currency' => (string) $best['product']->currency,
            'lender_partner_id' => $best['partner']->id,
            'partner_product_id' => $best['product']->id,
            'funding_pool_id' => $best['line']->funding_pool_id,
            'status' => 'offered',
            'disclosure_snapshot' => $disclosures,
            'disclosure_hash' => $hash,
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->audit->record('essentials.quote.created', $user, $quote, [
            'amount_minor' => $amount,
            'lender_partner_id' => $quote->lender_partner_id,
            'source_platform' => $quote->source_platform,
        ]);

        return $quote;
    }

    public function authorisePartnerCompletion(User $user, int $quoteId): array
    {
        $quote = EssentialsQuote::query()->where('user_id', $user->id)->findOrFail($quoteId);
        if (! $quote->source_partner_account_id) {
            throw new InvalidArgumentException('This quote was not initiated by an embedded partner.');
        }
        $this->assertOfferable($quote);

        $token = Str::random(64);
        $quote->update([
            'partner_authorisation_hash' => hash('sha256', $token),
            'partner_authorisation_expires_at' => now()->addMinutes(10),
        ]);

        $this->audit->record('essentials.quote.partner_authorised', $user, $quote);

        return ['authorisation_token' => $token, 'expires_at' => $quote->partner_authorisation_expires_at?->toISOString()];
    }

    public function acceptQuote(User $user, int $quoteId, string $disclosureHash, ?string $partnerAuthorisationToken = null): EssentialsAdvance
    {
        $quote = EssentialsQuote::query()->where('user_id', $user->id)->findOrFail($quoteId);
        $this->assertOfferable($quote);
        if (! hash_equals($quote->disclosure_hash, trim($disclosureHash))) {
            throw new InvalidArgumentException('The lender disclosure changed or the supplied disclosure hash is invalid. Reload the quote.');
        }
        if ($partnerAuthorisationToken !== null) {
            if (! $quote->partner_authorisation_hash
                || ! $quote->partner_authorisation_expires_at
                || $quote->partner_authorisation_expires_at->isPast()
                || ! hash_equals($quote->partner_authorisation_hash, hash('sha256', $partnerAuthorisationToken))) {
                throw new InvalidArgumentException('The embedded-partner authorisation is invalid or expired.');
            }
        }

        $settlementAccount = EssentialsAccount::query()->where('user_id', $user->id)->findOrFail($quote->essentials_account_id);
        $settlementBiller = EssentialsBiller::query()->findOrFail($settlementAccount->biller_id);
        if (! $this->cpay->configuredForBiller($settlementBiller)) {
            throw new RuntimeException(
                $settlementBiller->route === 'manual_verification'
                    ? 'Purpose-bound beneficiary settlement is not activated.'
                    : 'Purpose-bound bill settlement is not activated.'
            );
        }

        $advance = DB::transaction(function () use ($user, $quote) {
            $lockedQuote = EssentialsQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $this->assertOfferable($lockedQuote);
            $line = EssentialsCreditLine::query()->lockForUpdate()->findOrFail($lockedQuote->credit_line_id);
            $profile = CreditProfile::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $profile || $profile->available_to_borrow_minor < $lockedQuote->amount_minor) {
                throw new InvalidArgumentException('Your overall responsible-credit headroom changed. Refresh eligibility before accepting this offer.');
            }
            if ($line->status !== 'active' || $line->available_limit_minor < $lockedQuote->amount_minor) {
                throw new InvalidArgumentException('The selected lender line no longer has enough available capacity.');
            }

            if (! $lockedQuote->funding_pool_id && $line->decision_route === 'capital_mandate') {
                throw new RuntimeException('A third-party funding mandate is required for this lender route.');
            }

            if ($lockedQuote->funding_pool_id) {
                $pool = DB::table('capital_mandates')->where('id', $lockedQuote->funding_pool_id)->lockForUpdate()->first();
                if (! $pool || (int) $pool->partner_id !== (int) $lockedQuote->lender_partner_id || strtolower((string) $pool->status) !== 'active') {
                    throw new RuntimeException('The lender funding mandate is no longer available.');
                }
                $available = (int) $pool->committed_capital_minor - (int) $pool->deployed_capital_minor - (int) ($pool->reserved_capital_minor ?? 0);
                if ($available < $lockedQuote->amount_minor) {
                    throw new InvalidArgumentException('The lender funding mandate no longer has enough unreserved capital.');
                }
                DB::table('capital_mandates')->where('id', $pool->id)->update([
                    'reserved_capital_minor' => (int) ($pool->reserved_capital_minor ?? 0) + $lockedQuote->amount_minor,
                    'updated_at' => now(),
                ]);
            }

            $line->decrement('available_limit_minor', $lockedQuote->amount_minor);
            $profile->update([
                'available_to_borrow_minor' => max(0, (int) $profile->available_to_borrow_minor - (int) $lockedQuote->amount_minor),
            ]);
            $lockedQuote->update(['status' => 'accepted', 'accepted_at' => now()]);

            return EssentialsAdvance::create([
                'reference' => (string) Str::uuid(),
                'quote_id' => $lockedQuote->id,
                'user_id' => $user->id,
                'financial_space_id' => $lockedQuote->financial_space_id,
                'essentials_account_id' => $lockedQuote->essentials_account_id,
                'lender_partner_id' => $lockedQuote->lender_partner_id,
                'partner_product_id' => $lockedQuote->partner_product_id,
                'funding_pool_id' => $lockedQuote->funding_pool_id,
                'principal_minor' => $lockedQuote->amount_minor,
                'principal_outstanding_minor' => $lockedQuote->amount_minor,
                'total_repayment_minor' => $lockedQuote->total_repayment_minor,
                'outstanding_minor' => $lockedQuote->total_repayment_minor,
                'currency' => $lockedQuote->currency,
                'status' => 'funding_reserved',
                'lender_contract_reference' => $line->decision_reference,
                'repayment_schedule' => $this->schedule($lockedQuote->amount_minor, $lockedQuote->interest_minor, $lockedQuote->fees_minor, $lockedQuote->term_days),
                'next_due_date' => now()->addDays(min(30, $lockedQuote->term_days))->toDateString(),
                'final_due_date' => now()->addDays($lockedQuote->term_days)->toDateString(),
            ]);
        });

        $account = $settlementAccount;
        $biller = $settlementBiller;

        try {
            $result = $this->cpay->payBill($advance, $account, $biller);
        } catch (\Throwable $exception) {
            report($exception);
            $advance->update([
                'status' => 'fulfilment_pending',
                'fulfilment_payload' => [
                    'provider_state' => 'unknown',
                    'reconcile_by' => 'request_reference',
                    'request_reference' => $advance->reference,
                    'error_type' => $exception::class,
                ],
            ]);
            $this->safeAudit('essentials.advance.fulfilment_ambiguous', $user, $advance, [
                'biller_code' => $biller->code,
                'request_reference' => $advance->reference,
            ]);

            return $advance->fresh();
        }

        $status = strtoupper((string) ($result['status'] ?? ''));
        $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? '');
        $advance->update([
            'status' => 'fulfilment_pending',
            'biller_payment_reference' => $providerReference ?: null,
            'fulfilment_payload' => $this->redact($result),
        ]);

        if (in_array($status, ['FAILED', 'REVERSED', 'CANCELLED'], true)) {
            $advance = $this->releaseReservation($advance, 'fulfilment_failed');
        } elseif (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'], true)) {
            try {
                $advance = $this->activateAdvance($advance, $result);
            } catch (\Throwable $exception) {
                report($exception);
                $advance = $advance->fresh();
            }
        }

        $this->safeAudit('essentials.advance.accepted', $user, $advance, [
            'lender_partner_id' => $advance->lender_partner_id,
            'biller_code' => $biller->code,
            'provider_reference' => $providerReference ?: null,
            'provider_status' => $status ?: 'UNKNOWN',
        ]);

        return $advance->fresh();
    }

    public function repay(
        User $user,
        int $advanceId,
        int $amountMinor,
        string $idempotencyKey,
        ?int $walletId = null,
    ): EssentialsRepayment {

        $advance = EssentialsAdvance::query()->where('user_id', $user->id)->findOrFail($advanceId);
        if (! in_array($advance->status, ['active', 'overdue'], true)) {
            throw new InvalidArgumentException('Only an active Essentials advance can be repaid.');
        }
        if ($amountMinor <= 0 || $amountMinor > $advance->outstanding_minor) {
            throw new InvalidArgumentException('Repayment amount must be positive and cannot exceed the outstanding amount.');
        }

        $existing = EssentialsRepayment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ((int) $existing->advance_id !== (int) $advance->id || (int) $existing->amount_minor !== $amountMinor) {
                throw new InvalidArgumentException('This repayment idempotency key was already used for a different request.');
            }

            return $existing;
        }
        if (EssentialsRepayment::query()
            ->where('advance_id', $advance->id)
            ->whereIn('status', ['pending', 'pending_provider_confirmation'])
            ->exists()) {
            throw new InvalidArgumentException('A repayment collection is already awaiting provider confirmation for this advance.');
        }
        if (! $this->cpay->configured('lender_repayment_path')) {
            throw new RuntimeException('The CPay lender-repayment route is not configured.');
        }

        $walletQuery = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at');
        $wallet = $walletId
            ? (clone $walletQuery)->whereKey($walletId)->first()
            : (clone $walletQuery)->where('is_default_repayment', true)->first();
        if ($walletId && ! $wallet) {
            throw new InvalidArgumentException('Choose a verified repayment wallet that belongs to your OpFin profile.');
        }
        $wallet ??= (clone $walletQuery)->orderByDesc('is_default_repayment')->first();
        $payerMsisdn = $wallet?->msisdn ?: $user->phone;
        if (trim((string) $payerMsisdn) === '') {
            throw new InvalidArgumentException('A verified repayment phone or wallet is required.');
        }

        $repayment = EssentialsRepayment::create([
            'reference' => (string) Str::uuid(),
            'advance_id' => $advance->id,
            'user_id' => $user->id,
            'amount_minor' => $amountMinor,
            'currency' => $advance->currency,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $result = $this->cpay->collectRepayment($advance, $repayment, (string) $payerMsisdn, $wallet?->provider);
        } catch (\Throwable $exception) {
            report($exception);
            $repayment->update([
                'status' => 'pending_provider_confirmation',
                'metadata' => [
                    'provider_state' => 'unknown',
                    'reconcile_by' => 'request_reference',
                    'request_reference' => $repayment->reference,
                    'error_type' => $exception::class,
                ],
            ]);

            return $repayment->fresh();
        }

        $status = strtoupper((string) ($result['status'] ?? ''));
        $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? '');
        if (in_array($status, ['FAILED', 'REVERSED', 'CANCELLED'], true)) {
            $repayment->update([
                'status' => 'failed',
                'cpay_reference' => $providerReference ?: null,
                'provider_reference' => $providerReference ?: null,
                'metadata' => $this->redact($result),
            ]);

            return $repayment->fresh();
        }
        if (! in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'], true)) {
            $repayment->update([
                'status' => 'pending_provider_confirmation',
                'cpay_reference' => $providerReference ?: null,
                'provider_reference' => $providerReference ?: null,
                'metadata' => $this->redact($result),
            ]);

            return $repayment->fresh();
        }

        return $this->applySuccessfulRepayment($advance, $repayment, $result);
    }

    public function reconcileAdvance(EssentialsAdvance $advance): EssentialsAdvance
    {
        if (! in_array($advance->status, ['fulfilment_pending', 'funding_reserved'], true)) {
            return $advance;
        }
        $result = $advance->biller_payment_reference
            ? $this->cpay->status($advance->biller_payment_reference, 'provider')
            : $this->cpay->status($advance->reference, 'internal');
        $status = strtoupper((string) ($result['status'] ?? ''));
        if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'], true)) {
            return $this->activateAdvance($advance, $result);
        }
        if (in_array($status, ['FAILED', 'REVERSED', 'CANCELLED'], true)) {
            return $this->releaseReservation($advance, 'fulfilment_failed');
        }

        return $advance;
    }

    public function reconcileRepayment(EssentialsRepayment $repayment): EssentialsRepayment
    {
        if (! in_array($repayment->status, ['pending', 'pending_provider_confirmation'], true)) {
            return $repayment;
        }

        $advance = EssentialsAdvance::query()->findOrFail($repayment->advance_id);
        $reference = $repayment->provider_reference ?: $repayment->cpay_reference ?: $repayment->reference;
        $referenceType = ($repayment->provider_reference || $repayment->cpay_reference) ? 'provider' : 'internal';
        $result = $this->cpay->status($reference, $referenceType);
        $status = strtoupper((string) ($result['status'] ?? ''));

        if (in_array($status, ['FAILED', 'REVERSED', 'CANCELLED'], true)) {
            $repayment->update([
                'status' => 'failed',
                'provider_reference' => (string) ($result['providerReference'] ?? $repayment->provider_reference),
                'metadata' => $this->redact($result),
            ]);

            return $repayment->fresh();
        }
        if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED'], true)) {
            return $this->applySuccessfulRepayment($advance, $repayment, $result);
        }

        return $repayment;
    }

    public function adminVerifyAccount(EssentialsAccount $account, User $actor, string $status, ?string $providerReference = null): EssentialsAccount
    {
        if (! in_array($status, ['verified', 'failed'], true)) {
            throw new InvalidArgumentException('Account verification status must be verified or failed.');
        }
        $account->update([
            'verification_status' => $status,
            'provider_reference' => $providerReference,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
        $this->audit->record('essentials.account.admin_verified', $actor, $account, ['status' => $status]);

        return $account->fresh();
    }

    private function activateAdvance(EssentialsAdvance $advance, array $result): EssentialsAdvance
    {
        $activated = DB::transaction(function () use ($advance, $result) {
            $locked = EssentialsAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            if (in_array($locked->status, ['active', 'settled'], true)) {
                return $locked;
            }
            if ($locked->status === 'fulfilment_failed') {
                throw new RuntimeException('A failed Essentials fulfilment cannot be activated without a new governed request.');
            }

            $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? $locked->biller_payment_reference ?? '');

            if ($locked->funding_pool_id) {
                $pool = DB::table('capital_mandates')->where('id', $locked->funding_pool_id)->lockForUpdate()->first();
                if (! $pool || (int) ($pool->reserved_capital_minor ?? 0) < $locked->principal_minor) {
                    throw new RuntimeException('Funding reservation cannot be committed safely; operations review is required.');
                }
                DB::table('capital_mandates')->where('id', $pool->id)->update([
                    'reserved_capital_minor' => (int) $pool->reserved_capital_minor - $locked->principal_minor,
                    'deployed_capital_minor' => (int) $pool->deployed_capital_minor + $locked->principal_minor,
                    'updated_at' => now(),
                ]);
            }

            $quote = EssentialsQuote::query()->findOrFail($locked->quote_id);
            $line = EssentialsCreditLine::query()->lockForUpdate()->findOrFail($quote->credit_line_id);
            $line->increment('outstanding_minor', $locked->principal_minor);

            $activatedAt = now();
            $schedule = (array) ($locked->repayment_schedule ?? []);
            if (! DB::table('essentials_repayment_schedule_items')->where('advance_id', $locked->id)->exists()) {
                foreach ($schedule as $item) {
                    $dueDate = $activatedAt->copy()->addDays((int) ($item['due_offset_days'] ?? 0))->toDateString();
                    DB::table('essentials_repayment_schedule_items')->insert([
                        'advance_id' => $locked->id,
                        'installment_number' => (int) ($item['position'] ?? 1),
                        'due_date' => $dueDate,
                        'principal_original_minor' => (int) ($item['principal_minor'] ?? 0),
                        'principal_outstanding_minor' => (int) ($item['principal_minor'] ?? 0),
                        'interest_original_minor' => (int) ($item['interest_minor'] ?? 0),
                        'interest_outstanding_minor' => (int) ($item['interest_minor'] ?? 0),
                        'fees_original_minor' => (int) ($item['fees_minor'] ?? 0),
                        'fees_outstanding_minor' => (int) ($item['fees_minor'] ?? 0),
                        'total_original_minor' => (int) ($item['total_minor'] ?? 0),
                        'total_outstanding_minor' => (int) ($item['total_minor'] ?? 0),
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $nextDueDate = DB::table('essentials_repayment_schedule_items')
                ->where('advance_id', $locked->id)
                ->where('total_outstanding_minor', '>', 0)
                ->orderBy('due_date')
                ->value('due_date');

            $partner = DB::table('partners')->where('id', $locked->lender_partner_id)->first();
            $obligationId = $locked->financial_obligation_id;
            if (! $obligationId) {
                $obligationId = DB::table('financial_obligations')->insertGetId([
                    'financial_space_id' => $locked->financial_space_id,
                    'created_by_user_id' => $locked->user_id,
                    'kind' => 'essentials_credit',
                    'direction' => 'i_owe',
                    'counterparty_name' => $partner?->name ?? 'Third-party lender',
                    'original_amount_minor' => $locked->total_repayment_minor,
                    'outstanding_amount_minor' => $locked->total_repayment_minor,
                    'currency' => $locked->currency,
                    'due_date' => $locked->final_due_date,
                    'status' => 'open',
                    'metadata' => json_encode([
                        'essentials_advance_reference' => $locked->reference,
                        'lender_partner_id' => $locked->lender_partner_id,
                        'purpose_bound' => true,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $locked->update([
                'financial_obligation_id' => $obligationId,
                'status' => 'active',
                'biller_payment_reference' => $providerReference ?: $locked->biller_payment_reference,
                'fulfilment_payload' => $this->redact($result),
                'next_due_date' => $nextDueDate,
                'activated_at' => $locked->activated_at ?: $activatedAt,
            ]);

            return $locked->fresh();
        });

        $this->syncCreditProfileFinancials((int) $activated->user_id);

        try {
            $quote = EssentialsQuote::query()->findOrFail($activated->quote_id);
            $partner = DB::table('partners')->where('id', $activated->lender_partner_id)->first();
            $product = DB::table('partner_products')->where('id', $activated->partner_product_id)->first();
            $pricing = $this->json($product?->pricing ?? null);
            $this->economics->record([
                'user_id' => $activated->user_id,
                'financial_space_id' => $activated->financial_space_id,
                'partner_id' => $activated->lender_partner_id,
                'partner_product_id' => $activated->partner_product_id,
                'service_code' => 'essentials_finance',
                'capability_code' => 'PURPOSE_BOUND_CREDIT',
                'provider' => $partner?->name ?? 'Third-party lender',
                'route' => $quote->funding_pool_id ? 'CAPITAL_MANDATE' : 'CITO_MANAGED',
                'environment' => strtoupper((string) config('services.cpay.environment', 'sandbox')),
                'request_reference' => $activated->reference,
                'provider_reference' => $activated->biller_payment_reference,
                'status' => 'ACTIVE',
                'currency' => $activated->currency,
                'opfin_platform_fee_minor' => isset($pricing['opfin_servicing_fee_minor']) ? (int) $pricing['opfin_servicing_fee_minor'] : null,
                'partner_commission_minor' => isset($pricing['partner_commission_minor']) ? (int) $pricing['partner_commission_minor'] : null,
                'metadata' => ['purpose_category' => $quote->purpose_category, 'cash_disbursed_to_customer' => false],
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->safeAudit('essentials.advance.activated', null, $activated, [
            'provider_reference' => $activated->biller_payment_reference,
        ]);

        return $activated->fresh();
    }

    private function applySuccessfulRepayment(
        EssentialsAdvance $advance,
        EssentialsRepayment $repayment,
        array $result,
    ): EssentialsRepayment {
        $applied = DB::transaction(function () use ($advance, $repayment, $result) {
            $lockedRepayment = EssentialsRepayment::query()->lockForUpdate()->findOrFail($repayment->id);
            if ($lockedRepayment->status === 'successful') {
                return $lockedRepayment;
            }

            $lockedAdvance = EssentialsAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            if (! in_array($lockedAdvance->status, ['active', 'overdue'], true)) {
                throw new InvalidArgumentException('The Essentials advance is no longer open for repayment allocation.');
            }

            $remaining = (int) $lockedRepayment->amount_minor;
            $principalApplied = 0;
            $scheduleItems = DB::table('essentials_repayment_schedule_items')
                ->where('advance_id', $lockedAdvance->id)
                ->where('total_outstanding_minor', '>', 0)
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($scheduleItems as $item) {
                if ($remaining <= 0) {
                    break;
                }

                $interest = min($remaining, (int) $item->interest_outstanding_minor);
                $remaining -= $interest;
                $fees = min($remaining, (int) $item->fees_outstanding_minor);
                $remaining -= $fees;
                $principal = min($remaining, (int) $item->principal_outstanding_minor);
                $remaining -= $principal;
                $principalApplied += $principal;

                $principalOutstanding = (int) $item->principal_outstanding_minor - $principal;
                $interestOutstanding = (int) $item->interest_outstanding_minor - $interest;
                $feesOutstanding = (int) $item->fees_outstanding_minor - $fees;
                $totalOutstanding = $principalOutstanding + $interestOutstanding + $feesOutstanding;

                DB::table('essentials_repayment_schedule_items')->where('id', $item->id)->update([
                    'principal_outstanding_minor' => $principalOutstanding,
                    'interest_outstanding_minor' => $interestOutstanding,
                    'fees_outstanding_minor' => $feesOutstanding,
                    'total_outstanding_minor' => $totalOutstanding,
                    'status' => $totalOutstanding === 0 ? 'settled' : 'partial',
                    'settled_at' => $totalOutstanding === 0 ? now() : null,
                    'updated_at' => now(),
                ]);
            }

            if ($remaining !== 0) {
                throw new RuntimeException('Repayment allocation did not reconcile to the current Essentials schedule.');
            }

            $newOutstanding = (int) DB::table('essentials_repayment_schedule_items')
                ->where('advance_id', $lockedAdvance->id)
                ->sum('total_outstanding_minor');
            $newPrincipal = (int) DB::table('essentials_repayment_schedule_items')
                ->where('advance_id', $lockedAdvance->id)
                ->sum('principal_outstanding_minor');
            $nextDueDate = DB::table('essentials_repayment_schedule_items')
                ->where('advance_id', $lockedAdvance->id)
                ->where('total_outstanding_minor', '>', 0)
                ->orderBy('due_date')
                ->value('due_date');
            $settled = $newOutstanding === 0;

            $lockedAdvance->update([
                'outstanding_minor' => $newOutstanding,
                'principal_outstanding_minor' => $newPrincipal,
                'repaid_minor' => min($lockedAdvance->total_repayment_minor, $lockedAdvance->repaid_minor + $lockedRepayment->amount_minor),
                'status' => $settled ? 'settled' : 'active',
                'next_due_date' => $nextDueDate,
                'settled_at' => $settled ? now() : null,
            ]);

            $quote = EssentialsQuote::query()->findOrFail($lockedAdvance->quote_id);
            $line = EssentialsCreditLine::query()->lockForUpdate()->findOrFail($quote->credit_line_id);
            if ($principalApplied > 0) {
                $line->update([
                    'outstanding_minor' => max(0, $line->outstanding_minor - $principalApplied),
                    'available_limit_minor' => min($line->approved_limit_minor, $line->available_limit_minor + $principalApplied),
                ]);

                if ($lockedAdvance->funding_pool_id) {
                    $pool = DB::table('capital_mandates')->where('id', $lockedAdvance->funding_pool_id)->lockForUpdate()->first();
                    if ($pool) {
                        DB::table('capital_mandates')->where('id', $pool->id)->update([
                            'deployed_capital_minor' => max(0, (int) $pool->deployed_capital_minor - $principalApplied),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            if ($lockedAdvance->financial_obligation_id) {
                DB::table('financial_obligations')
                    ->where('id', $lockedAdvance->financial_obligation_id)
                    ->update([
                        'outstanding_amount_minor' => $newOutstanding,
                        'status' => $settled ? 'settled' : 'open',
                        'updated_at' => now(),
                    ]);
            }

            $providerReference = (string) ($result['providerReference'] ?? $result['reference'] ?? $lockedRepayment->provider_reference ?? '');
            $lockedRepayment->update([
                'status' => 'successful',
                'principal_applied_minor' => $principalApplied,
                'cpay_reference' => $providerReference ?: $lockedRepayment->cpay_reference,
                'provider_reference' => $providerReference ?: $lockedRepayment->provider_reference,
                'paid_at' => now(),
                'metadata' => array_merge($this->redact($result), [
                    'allocation_policy' => 'oldest_due_interest_fees_principal_v1',
                ]),
            ]);

            return $lockedRepayment->fresh();
        });

        $this->syncCreditProfileFinancials((int) $advance->user_id);
        $this->safeAudit('essentials.repayment.successful', null, $applied, [
            'advance_id' => $advance->id,
            'amount_minor' => $applied->amount_minor,
            'principal_applied_minor' => $applied->principal_applied_minor,
        ]);

        return $applied->fresh();
    }

    private function releaseReservation(EssentialsAdvance $advance, string $status): EssentialsAdvance
    {
        $released = DB::transaction(function () use ($advance, $status) {
            $locked = EssentialsAdvance::query()->lockForUpdate()->findOrFail($advance->id);
            if (in_array($locked->status, ['active', 'settled', 'fulfilment_failed'], true)) {
                return $locked;
            }

            $quote = EssentialsQuote::query()->findOrFail($locked->quote_id);
            $line = EssentialsCreditLine::query()->lockForUpdate()->findOrFail($quote->credit_line_id);
            $line->update([
                'available_limit_minor' => min($line->approved_limit_minor, $line->available_limit_minor + $locked->principal_minor),
            ]);

            $profile = CreditProfile::query()->where('user_id', $locked->user_id)->lockForUpdate()->first();
            if ($profile) {
                $profile->update([
                    'available_to_borrow_minor' => min(
                        (int) $profile->credit_limit_minor,
                        (int) $profile->available_to_borrow_minor + (int) $locked->principal_minor,
                    ),
                ]);
            }

            if ($locked->funding_pool_id) {
                $pool = DB::table('capital_mandates')->where('id', $locked->funding_pool_id)->lockForUpdate()->first();
                if ($pool && (int) ($pool->reserved_capital_minor ?? 0) >= $locked->principal_minor) {
                    DB::table('capital_mandates')->where('id', $pool->id)->update([
                        'reserved_capital_minor' => (int) $pool->reserved_capital_minor - $locked->principal_minor,
                        'updated_at' => now(),
                    ]);
                }
            }

            $locked->update(['status' => $status]);

            return $locked->fresh();
        });

        $this->syncCreditProfileFinancials((int) $released->user_id);

        return $released;
    }

    private function syncCreditProfileFinancials(int $userId): void
    {
        $profile = CreditProfile::query()->where('user_id', $userId)->first();
        if (! $profile) {
            return;
        }

        $productionOutstanding = Schema::hasTable('credit_repayment_schedule_items')
            ? (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->sum('schedule.total_outstanding_minor')
            : 0;
        $legacyOutstanding = Schema::hasTable('loan_schedules')
            ? (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.total_outstanding', '>', 0)
                ->sum('schedule.total_outstanding'))
            : 0;
        $essentialsOutstanding = Schema::hasTable('essentials_repayment_schedule_items')
            ? (int) DB::table('essentials_repayment_schedule_items as schedule')
                ->join('essentials_advances as advance', 'advance.id', '=', 'schedule.advance_id')
                ->where('advance.user_id', $userId)
                ->whereIn('advance.status', ['active', 'overdue'])
                ->sum('schedule.total_outstanding_minor')
            : 0;
        $pendingReserved = Schema::hasTable('essentials_advances')
            ? (int) DB::table('essentials_advances')
                ->where('user_id', $userId)
                ->whereIn('status', ['funding_reserved', 'fulfilment_pending'])
                ->sum('principal_outstanding_minor')
            : 0;

        $totalOutstanding = $productionOutstanding + $legacyOutstanding + $essentialsOutstanding;
        $currentExposure = $totalOutstanding + $pendingReserved;
        $today = now()->toDateString();

        $amountDue = 0;
        $dates = [];
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $amountDue += (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '<=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->sum('schedule.total_outstanding_minor');
            $date = DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '>=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->orderBy('schedule.due_date')
                ->value('schedule.due_date');
            if ($date) {
                $dates[] = (string) $date;
            }
        }
        if (Schema::hasTable('loan_schedules')) {
            $amountDue += (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '<=', $today)
                ->where('schedule.total_outstanding', '>', 0)
                ->sum('schedule.total_outstanding'));
            $date = DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $userId)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '>=', $today)
                ->where('schedule.total_outstanding', '>', 0)
                ->orderBy('schedule.due_date')
                ->value('schedule.due_date');
            if ($date) {
                $dates[] = (string) $date;
            }
        }
        if (Schema::hasTable('essentials_repayment_schedule_items')) {
            $amountDue += (int) DB::table('essentials_repayment_schedule_items as schedule')
                ->join('essentials_advances as advance', 'advance.id', '=', 'schedule.advance_id')
                ->where('advance.user_id', $userId)
                ->whereIn('advance.status', ['active', 'overdue'])
                ->where('schedule.due_date', '<=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->sum('schedule.total_outstanding_minor');
            $date = DB::table('essentials_repayment_schedule_items as schedule')
                ->join('essentials_advances as advance', 'advance.id', '=', 'schedule.advance_id')
                ->where('advance.user_id', $userId)
                ->whereIn('advance.status', ['active', 'overdue'])
                ->where('schedule.due_date', '>=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->orderBy('schedule.due_date')
                ->value('schedule.due_date');
            if ($date) {
                $dates[] = (string) $date;
            }
        }
        sort($dates);

        $hasActiveOrdinaryLoan = DB::table('loans')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->exists();

        $profile->update([
            'current_exposure_minor' => $currentExposure,
            'total_outstanding_minor' => $totalOutstanding,
            'available_to_borrow_minor' => $hasActiveOrdinaryLoan
                ? 0
                : max(0, (int) $profile->credit_limit_minor - $currentExposure),
            'amount_due_minor' => $amountDue,
            'next_due_date' => $dates[0] ?? null,
        ]);
    }

    private function safeAudit(
        string $event,
        ?User $actor,
        \Illuminate\Database\Eloquent\Model $subject,
        array $metadata = [],
    ): void {
        try {
            $this->audit->record($event, $actor, $subject, $metadata);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function price(int $amount, array $pricing, string $channel): ?array
    {
        $termDays = (int) ($pricing['term_days'] ?? AppStoreCreditPolicy::PREFERRED_FULL_REPAYMENT_DAYS);
        if ($termDays <= 0) {
            throw new InvalidArgumentException('Essentials lender term must be positive.');
        }
        if (in_array($channel, AppStoreCreditPolicy::STORE_CHANNELS, true) && $termDays < AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS) {
            return null;
        }

        $monthlyRate = max(0, (float) ($pricing['monthly_interest_rate_percent'] ?? $pricing['interest_rate_percent'] ?? 0));
        $feePercent = max(0, (float) ($pricing['fee_percent'] ?? 0));
        $fixedFee = max(0, (int) ($pricing['fixed_fee_minor'] ?? 0));

        $policy = $this->policies->active('regulatory_pricing', 'essentials');
        $rules = $this->policies->rules($policy);
        if (isset($rules['max_rate_percent'])) {
            $cycle = strtolower((string) ($rules['rate_cycle'] ?? 'monthly'));
            $cycleDays = match ($cycle) {
                'daily', 'day' => 1,
                'weekly', 'week' => 7,
                'monthly', 'month' => 30,
                'annual', 'annually', 'yearly', 'year' => 365,
                default => throw new InvalidArgumentException('The active regulatory pricing policy has an unsupported rate cycle.'),
            };
            $maximumMonthlyEquivalent = ((float) $rules['max_rate_percent'] / $cycleDays) * 30;
            if ($monthlyRate > $maximumMonthlyEquivalent + 0.0000001) {
                throw new InvalidArgumentException('The lender interest rate exceeds the active effective-dated regulatory pricing policy.');
            }
        }

        $feeCaps = (array) ($rules['fee_caps'] ?? []);
        if (isset($feeCaps['access_fee_percent']) && $feePercent > (float) $feeCaps['access_fee_percent'] + 0.0000001) {
            throw new InvalidArgumentException('The lender percentage fee exceeds the active regulatory pricing policy.');
        }
        if (isset($feeCaps['disbursement_fee_minor']) && $fixedFee > (int) $feeCaps['disbursement_fee_minor']) {
            throw new InvalidArgumentException('The lender fixed fee exceeds the active regulatory pricing policy.');
        }

        $interest = (int) round($amount * ($monthlyRate / 100) * ($termDays / 30));
        $fees = $fixedFee + (int) round($amount * ($feePercent / 100));
        $total = $amount + $interest + $fees;
        $annualised = $amount > 0 ? (($total - $amount) / $amount) * (365 / max(1, $termDays)) * 100 : 0;
        if ($channel === 'app_store' && $annualised > AppStoreCreditPolicy::MAX_APR_PERCENT) {
            return null;
        }

        return [
            'interest_minor' => $interest,
            'fees_minor' => $fees,
            'total_repayment_minor' => $total,
            'term_days' => $termDays,
            'method' => 'partner_pricing_snapshot_simple_term_cost_v2',
            'simple_annualised_cost_percent' => round($annualised, 6),
            'regulatory_policy' => [
                'id' => $policy->id,
                'code' => $policy->code,
                'version' => $policy->version,
                'licence_class' => $policy->licence_class,
                'effective_from' => $policy->effective_from,
            ],
        ];
    }

    private function schedule(int $principal, int $interest, int $fees, int $termDays): array
    {
        $count = max(1, (int) ceil($termDays / 30));
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $p = $this->allocate($principal, $count, $i);
            $in = $this->allocate($interest, $count, $i);
            $f = $this->allocate($fees, $count, $i);
            $offset = min($termDays, $i * 30);
            $items[] = [
                'position' => $i,
                'due_offset_days' => $offset,
                'principal_minor' => $p,
                'interest_minor' => $in,
                'fees_minor' => $f,
                'total_minor' => $p + $in + $f,
            ];
        }

        return $items;
    }

    private function allocate(int $total, int $count, int $position): int
    {
        $base = intdiv($total, $count);
        $remainder = $total % $count;
        return $base + ($position <= $remainder ? 1 : 0);
    }

    private function assertOfferable(EssentialsQuote $quote): void
    {
        if ($quote->status !== 'offered') {
            throw new InvalidArgumentException('This Essentials quote is no longer available.');
        }
        if ($quote->expires_at->isPast()) {
            $quote->update(['status' => 'expired']);
            throw new InvalidArgumentException('This Essentials quote has expired.');
        }
    }

    private function resolveSpaceId(User $user, ?int $spaceId): int
    {
        if ($spaceId) {
            $exists = DB::table('financial_space_memberships')
                ->where('financial_space_id', $spaceId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();
            if (! $exists) {
                throw new InvalidArgumentException('You do not have active access to that Financial Space.');
            }
            return $spaceId;
        }

        $personal = DB::table('financial_space_memberships as m')
            ->join('financial_spaces as s', 's.id', '=', 'm.financial_space_id')
            ->where('m.user_id', $user->id)
            ->where('m.status', 'active')
            ->where('s.type', 'personal')
            ->where('s.status', 'active')
            ->value('s.id');

        if (! $personal) {
            throw new InvalidArgumentException('A Personal Financial Space is required before using OpFin Essentials.');
        }

        return (int) $personal;
    }

    private function assertPartnerAccount(int $partnerAccountId, ?string $sourcePlatform): object
    {
        $account = DB::table('partner_distribution_accounts')->where('id', $partnerAccountId)->first();
        if (! $account || ! in_array(strtolower((string) $account->status), ['active', 'approved'], true)) {
            throw new InvalidArgumentException('The embedded platform partner is not approved for Essentials distribution.');
        }
        $allowed = $this->json($account->allowed_products);
        if ($allowed && ! in_array('essentials', array_map('strtolower', $allowed), true)) {
            throw new InvalidArgumentException('This platform partner is not approved to distribute OpFin Essentials.');
        }
        if ($sourcePlatform && ! str_contains(strtolower((string) $account->partner_name), strtolower((string) $sourcePlatform))) {
            // Do not reject legitimate white-label names; preserve the supplied platform only as attribution.
        }

        return $account;
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function redact(array $payload): array
    {
        foreach (['token', 'accessToken', 'secret', 'privateKey', 'accountReference'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[REDACTED]';
            }
        }
        return $payload;
    }
}
