<?php

namespace App\Services;

use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Models\EssentialsRepayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SerialisedEssentialsOrchestrationService extends EssentialsOrchestrationService
{
    public function summary(User $user, string $channel = 'web'): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($channel): array {
            $financials = DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            $result = parent::summary($current, $channel);
            $result['outstanding_minor'] = $financials['essentials_debt_minor'];
            $result['reserved_financing_minor'] = $financials['essentials_reserved_minor'];
            $result['reserved_financing_is_not_due_debt'] = true;
            return $result;
        });
    }

    public function createAccount(User $user, array $data): EssentialsAccount
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): EssentialsAccount => parent::createAccount($current, $data));
    }

    public function refreshEligibility(User $user, ?int $financialSpaceId = null, string $channel = 'android',
        ?int $requestedAmountMinor = null, ?string $purposeCategory = null): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($financialSpaceId, $channel, $requestedAmountMinor, $purposeCategory): array {
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return parent::refreshEligibility($current, $financialSpaceId, $channel, $requestedAmountMinor, $purposeCategory);
        });
    }

    public function createQuote(User $user, array $data): EssentialsQuote
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($data): EssentialsQuote {
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return parent::createQuote($current, $data);
        });
    }

    public function authorisePartnerCompletion(User $user, int $quoteId): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): array => parent::authorisePartnerCompletion($current, $quoteId));
    }

    public function acceptQuote(User $user, int $quoteId, string $disclosureHash, ?string $partnerAuthorisationToken = null): EssentialsAdvance
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($quoteId, $disclosureHash, $partnerAuthorisationToken): EssentialsAdvance {
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            $advance = parent::acceptQuote($current, $quoteId, $disclosureHash, $partnerAuthorisationToken);
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return $advance;
        });
    }

    public function repay(User $user, int $advanceId, int $amountMinor, string $idempotencyKey, ?int $walletId = null): EssentialsRepayment
    {
        return app(EssentialsCustomerMutex::class)->run($user->id, function (User $current) use ($advanceId, $amountMinor, $idempotencyKey, $walletId): EssentialsRepayment {
            $repayment = app(EssentialsDurableCollections::class)->request($current, $advanceId, $amountMinor, $idempotencyKey, $walletId);
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return $repayment;
        });
    }

    public function reconcileRepayment(EssentialsRepayment $repayment): EssentialsRepayment
    {
        return app(EssentialsCustomerMutex::class)->runRetainedServicing($repayment->user_id, function (User $current) use ($repayment): EssentialsRepayment {
            $result = app(EssentialsDurableCollections::class)->reconcile($repayment);
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return $result;
        });
    }

    public function reconcileAdvance(EssentialsAdvance $advance): EssentialsAdvance
    {
        return app(EssentialsCustomerMutex::class)->run($advance->user_id, function (User $current) use ($advance): EssentialsAdvance {
            $result = parent::reconcileAdvance($advance);
            DB::transaction(fn () => app(EssentialsExposureSynchroniser::class)->synchronise($current->id));
            return $result;
        });
    }
}
