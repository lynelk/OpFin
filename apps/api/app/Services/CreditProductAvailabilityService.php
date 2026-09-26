<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Scopes\InstitutionScope;
use InvalidArgumentException;

class CreditProductAvailabilityService
{
    public function assertApplicationAvailable(LoanApplication $application): void
    {
        $product = LoanProduct::query()
            ->withoutGlobalScope(InstitutionScope::class)
            ->find($application->loan_product_id);
        $term = LoanProductTerm::query()->find($application->loan_product_term_id);

        if (! $product || ! $term) {
            throw new InvalidArgumentException('The selected credit product or term is no longer available.');
        }

        $this->assertAvailable($product, $term, (int) $application->institution_id);
    }

    public function assertAvailable(LoanProduct $product, LoanProductTerm $term, int $institutionId): void
    {
        if (strcasecmp((string) $product->status, 'Active') !== 0) {
            throw new InvalidArgumentException('The selected credit product is not active.');
        }

        if (strcasecmp((string) $term->status, 'Active') !== 0) {
            throw new InvalidArgumentException('The selected credit product term is not active.');
        }

        if ((int) $term->loan_product_id !== (int) $product->id) {
            throw new InvalidArgumentException('The selected product term does not belong to the selected credit product.');
        }

        if ($product->institution_id !== null && (int) $product->institution_id !== $institutionId) {
            throw new InvalidArgumentException('The selected institution is not eligible for this credit product.');
        }
    }
}
