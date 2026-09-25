<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlatformCreditRoutingService
{
    public function __construct(private readonly CreditDistributionService $distribution) {}

    public function strategy(): array
    {
        $strategy = DB::table('platform_credit_strategies')->where('effective_from', '<=', now())->latest('id')->first();
        if (! $strategy || ($strategy->effective_to && now()->gte($strategy->effective_to))) {
            return ['id' => null, 'mode' => 'withhold', 'reason' => 'Affiliated credit is withheld until a platform administrator enables its deployment.'];
        }

        return (array) $strategy;
    }

    public function canManage(User $user): bool
    {
        return $user->hasRole(User::ROLE_PLATFORM_ADMIN)
            || ($user->hasRole(User::ROLE_OPERATIONS) && $user->can_manage_platform_credit);
    }

    public function assertManager(User $user, ?Institution $institution): void
    {
        if ($institution?->lender_relationship === 'affiliated' && ! $this->canManage($user)) {
            abort(403, 'Affiliated lender management requires delegated platform credit access.');
        }
    }

    public function options(string $channel, string $country, ?int $amount = null, ?string $purpose = null): Collection
    {
        $this->distribution->channel($channel);
        $candidates = LoanProduct::query()->where('status', 'Active')->where('country', $country)
            ->whereNotNull('institution_id')->with(['institution', 'terms' => fn ($q) => $q->where('status', 'Active')->orderBy('duration')->orderBy('id')])
            ->orderBy('id')->get()->flatMap(function (LoanProduct $product) use ($channel, $amount, $purpose) {
                if ($amount !== null && ($amount < $product->min_amount_minor || ($product->max_amount_minor && $amount > $product->max_amount_minor))) {
                    return [];
                }
                if ($purpose !== null && $product->borrower_purposes && ! in_array($purpose, $product->borrower_purposes, true)) {
                    return [];
                }
                if ($amount !== null && $product->funding_pool_id) {
                    try {
                        app(FundingPoolService::class)->validateSelection($product->funding_pool_id, $amount);
                        app(FundingPoolService::class)->validateLender($product->funding_pool_id, $product->institution);
                    } catch (\RuntimeException|InvalidArgumentException) {
                        return [];
                    }
                }

                return $product->terms->filter(fn ($term) => $this->distribution->assess($product, $term, $channel)['available'])
                    ->map(fn ($term) => ['product' => $product, 'term' => $term, 'affiliated' => $product->institution->lender_relationship === 'affiliated']);
            });
        $strategy = $this->strategy();
        $external = $candidates->where('affiliated', false);
        $affiliated = $candidates->where('affiliated', true)->filter(fn ($item) => ! isset($strategy['max_affiliated_loan_minor']) || $amount === null || $amount <= $strategy['max_affiliated_loan_minor']);

        return match ($strategy['mode']) {
            'affiliated_first' => $affiliated->concat($external)->values(),
            'external_first' => ($external->isNotEmpty() ? $external : $affiliated)->values(),
            default => $external->values(),
        };
    }

    public function rankPartnerCandidates(Collection $candidates, int $amount): Collection
    {
        $strategy = $this->strategy();
        $grouped = $candidates->groupBy(function ($candidate) {
            $id = $candidate['partner']->institution_id ?? null;

            return $id && Institution::find($id)?->lender_relationship === 'affiliated' ? 'affiliated' : 'independent';
        });
        $external = $grouped->get('independent', collect());
        $affiliated = $grouped->get('affiliated', collect());
        if (isset($strategy['max_affiliated_loan_minor']) && $amount > $strategy['max_affiliated_loan_minor']) {
            $affiliated = collect();
        }

        return match ($strategy['mode']) {
            'affiliated_first' => $affiliated->concat($external)->values(),
            'external_first' => ($external->isNotEmpty() ? $external : $affiliated)->values(),
            default => $external->values(),
        };
    }

    public function assertOrigination(LoanApplication $application): void
    {
        $application->loadMissing(['loanProduct.institution', 'loanProductTerm']);
        $product = $application->loanProduct;
        if (! $product || ! $application->loanProductTerm || (int) $product->institution_id !== (int) $application->institution_id
            || (int) $application->loanProductTerm->loan_product_id !== (int) $product->id) {
            throw new InvalidArgumentException('The product, term and responsible lender must match.');
        }
        $this->distribution->requireAvailable($product, $application->loanProductTerm, $application->distribution_channel ?? 'web');
        $match = $this->options($application->distribution_channel ?? 'web', $product->country, (int) $application->amount, $application->reason)
            ->contains(fn ($option) => $option['term']->id === $application->loan_product_term_id);
        if (! $match) {
            throw new InvalidArgumentException('This lender route is not available under the current platform credit deployment strategy or product eligibility.');
        }
    }
}
