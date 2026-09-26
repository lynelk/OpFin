<?php

namespace App\Services;

/** Eligibility is not permission to read a customer's accounts or repayment history. */
class EssentialsPartnerEligibilityView
{
    public function forSpace(array $result, int $spaceId): array
    {
        $lines = [];
        foreach ($result['lines'] ?? [] as $line) {
            if ($line instanceof \Illuminate\Contracts\Support\Arrayable) {
                $line = $line->toArray();
            }
            if (! is_array($line) || (int) ($line['financial_space_id'] ?? 0) !== $spaceId) {
                continue;
            }
            $lines[] = array_intersect_key($line, array_flip([
                'id', 'reference', 'financial_space_id', 'lender_partner_id', 'partner_product_id',
                'approved_limit_minor', 'available_limit_minor', 'currency', 'status', 'expires_at',
            ]));
        }
        $currencies = array_values(array_unique(array_filter(array_column($lines, 'currency'), 'is_string')));
        $available = 0;
        if (count($currencies) === 1) {
            foreach ($lines as $line) {
                $available = max($available, $this->amount($line['available_limit_minor'] ?? 0));
            }
            // Keep the existing overall headroom ceiling without returning its
            // underlying cross-Space accounts, history or raw credit evidence.
            $available = min($available, $this->amount($result['overall']['overall_available_limit_minor'] ?? 0));
        }

        return [
            'financial_space_id' => $spaceId,
            'lines' => $lines,
            'overall' => [
                'financial_space_id' => $spaceId,
                'overall_available_limit_minor' => $available,
                'currency' => count($currencies) === 1 ? $currencies[0] : null,
                'limits_are_not_additive' => true,
            ],
        ];
    }

    private function amount(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_string($value) && ctype_digit($value) && (string) (int) $value === $value) {
            return (int) $value;
        }

        return 0;
    }
}
