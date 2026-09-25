<?php

namespace App\Services;

use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Models\EssentialsRepayment;
use App\Models\User;

/** Keep the existing domain/provider implementation and its commit boundaries. */
class SerialisedEssentialsOrchestrationService extends EssentialsOrchestrationService
{
    public function createAccount(User $user, array $data): EssentialsAccount
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): EssentialsAccount => parent::createAccount($current, $data));
    }

    public function refreshEligibility(User $user, ?int $financialSpaceId = null, string $channel = 'android',
        ?int $requestedAmountMinor = null, ?string $purposeCategory = null): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): array => parent::refreshEligibility($current, $financialSpaceId, $channel, $requestedAmountMinor, $purposeCategory));
    }

    public function createQuote(User $user, array $data): EssentialsQuote
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): EssentialsQuote => parent::createQuote($current, $data));
    }

    public function authorisePartnerCompletion(User $user, int $quoteId): array
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): array => parent::authorisePartnerCompletion($current, $quoteId));
    }

    public function acceptQuote(User $user, int $quoteId, string $disclosureHash, ?string $partnerAuthorisationToken = null): EssentialsAdvance
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): EssentialsAdvance => parent::acceptQuote($current, $quoteId, $disclosureHash, $partnerAuthorisationToken));
    }

    public function repay(User $user, int $advanceId, int $amountMinor, string $idempotencyKey, ?int $walletId = null): EssentialsRepayment
    {
        return app(EssentialsCustomerMutex::class)->run($user->id,
            fn (User $current): EssentialsRepayment => parent::repay($current, $advanceId, $amountMinor, $idempotencyKey, $walletId));
    }
}
