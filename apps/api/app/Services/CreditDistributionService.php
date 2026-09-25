<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditDistributionService
{
    public function channels(): array
    {
        return config('credit_distribution.channels', []);
    }

    public function channel(string $channel): string
    {
        if (! in_array($channel, $this->channels(), true)) {
            throw new InvalidArgumentException('Unknown distribution channel.');
        }

        return config('credit_distribution.aliases.'.$channel, $channel);
    }

    public function policy(LoanProduct $product, string $channel): array
    {
        $channel = $this->channel($channel);
        $category = $product->product_category ?: 'personal_loan';
        // The newest effective revision of a scope supersedes earlier revisions.
        // An expired revision does not resurrect an older exception.
        $rules = DB::table('credit_distribution_rules')
            ->where('channel', $channel)
            ->whereIn('country', ['*', $product->country])
            ->whereIn('product_category', ['*', $category])
            ->where(fn ($q) => $q->whereNull('institution_id')->orWhere('institution_id', $product->institution_id))
            ->where(fn ($q) => $q->whereNull('loan_product_id')->orWhere('loan_product_id', $product->id))
            ->where(fn ($q) => $q->whereNull('partner_product_id')->orWhere('partner_product_id', $product->distribution_partner_product_id))
            ->where('effective_from', '<=', now())
            ->orderByDesc('version')->orderByDesc('id')->get()
            ->unique('scope_key')
            ->filter(fn ($rule) => ! $rule->effective_to || now()->lt($rule->effective_to))
            ->sortByDesc(fn ($rule) => (($rule->loan_product_id || $rule->partner_product_id) ? 16 : 0) + ($rule->institution_id ? 8 : 0) + ($rule->country !== '*' ? 4 : 0) + ($rule->product_category !== '*' ? 2 : 0));

        if ($rule = $rules->first()) {
            return (array) $rule;
        }

        $defaults = config('credit_distribution.defaults.'.$channel, []);

        return $defaults[$category] ?? $defaults['*'] ?? [
            'availability' => 'available', 'version' => 'non-store-v1',
            'reason' => 'Lender and country product requirements apply.',
        ];
    }

    public function assess(LoanProduct $product, LoanProductTerm $term, string $channel, ?float $apr = null): array
    {
        $policy = $this->policy($product, $channel);
        $code = null;
        $reason = $policy['reason'];
        if (! in_array($product->country, config('credit_distribution.enabled_countries', []), true)) {
            $code = 'COUNTRY_NOT_ACTIVATED';
            $reason = 'This market is configured but its operational country pack is not activated.';
        } elseif ($product->currency !== config('services.mobile_money.currency', 'UGX')) {
            $code = 'CURRENCY_ROUTE_NOT_ACTIVATED';
            $reason = 'A certified payment and accounting route for this currency is not activated.';
        } elseif (strcasecmp((string) $product->status, 'Active') !== 0 || strcasecmp((string) $term->status, 'Active') !== 0 || ! $product->institution || strcasecmp((string) $product->institution->status, 'Active') !== 0) {
            $code = 'CREDIT_ROUTE_INACTIVE';
            $reason = 'The lender, product or term is not active.';
        } elseif ($product->institution->authority_valid_until?->isBefore(today())) {
            $code = 'LENDER_AUTHORITY_EXPIRED';
            $reason = 'The lender authority record requires renewal.';
        } elseif ($policy['availability'] !== 'available') {
            $code = $policy['availability'] === 'review' ? 'CHANNEL_REVIEW_REQUIRED' : 'CHANNEL_PRODUCT_UNAVAILABLE';
        } elseif (isset($policy['min_duration_days']) && $term->duration < $policy['min_duration_days']) {
            $code = 'STORE_TERM_TOO_SHORT';
            $reason = 'This term is below the minimum repayment period configured for this product and channel.';
        } elseif ($apr !== null && isset($policy['max_apr_percent']) && $apr > (float) $policy['max_apr_percent'] + 0.000001) {
            $code = 'CHANNEL_APR_EXCEEDED';
            $reason = 'The disclosed APR exceeds the limit configured for this product and channel.';
        }

        return ['available' => $code === null, 'code' => $code, 'reason' => $reason, 'channel' => $this->channel($channel), 'country' => $product->country, 'product_category' => $product->product_category, 'policy' => $policy];
    }

    public function partnerContext(object $product, object $partner): LoanProduct
    {
        $context = new LoanProduct([
            'name' => $product->name, 'status' => $product->status,
            'country' => $product->country, 'currency' => $product->currency,
            'institution_id' => $partner->institution_id ?? null,
            'product_category' => json_decode($product->disclosures ?? '{}', true)['distribution_category'] ?? 'personal_loan',
        ]);
        $context->distribution_partner_product_id = $product->id;
        $institution = isset($partner->institution_id) ? Institution::find($partner->institution_id) : null;
        $context->setRelation('institution', $partner->institution_id ? $institution : new Institution(['name' => $partner->name, 'status' => ucfirst($partner->status), 'lender_relationship' => 'independent']));

        return $context;
    }

    public function requireAvailable(LoanProduct $product, LoanProductTerm $term, string $channel, ?float $apr = null): array
    {
        $result = $this->assess($product, $term, $channel, $apr);
        if (! $result['available']) {
            throw new InvalidArgumentException($result['reason']);
        }

        return $result;
    }
}
