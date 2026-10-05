<?php

declare(strict_types=1);

namespace App\Services\ProductFactory;

use App\Models\ProductTemplate;

/**
 * Template and product validation for the Product Factory (PF-001). Templates hold the bounds a
 * partner product may not exceed. Numbers are integers in basis points or months; nothing here
 * computes a customer's price, which stays with the lender's approved pricing engine.
 */
final class ProductGuardrails
{
    /** @return list<string> plain-language problems with a template definition */
    public function templateProblems(string $family, string $rail, array $contractTypes, array $guardrails): array
    {
        $railConfig = config("product_factory.rails.{$rail}");
        if (! is_array($railConfig)) {
            return ['Choose a supported rail.'];
        }
        $problems = [];
        if (! in_array($family, config('product_factory.families'), true)) {
            $problems[] = 'Choose a supported product family.';
        }
        if ($contractTypes === [] || array_diff($contractTypes, $railConfig['contract_types']) !== []) {
            $problems[] = "Contract types must come from the {$rail} rail: ".implode(', ', $railConfig['contract_types']).'.';
        }
        if (! $this->range($guardrails['tenor_months'] ?? null, 1, 360)) {
            $problems[] = 'Set a tenor range between 1 and 360 months.';
        }
        if (! $this->whole($guardrails['price_max_bps'] ?? null, 0, 100000)) {
            $problems[] = 'Set the maximum '.($rail === 'ISLAMIC' ? 'profit rate' : 'APR').' in basis points.';
        }
        foreach (['deposit_min_bps', 'ltv_max_bps', 'fee_max_bps'] as $key) {
            if (($guardrails[$key] ?? null) !== null && ! $this->whole($guardrails[$key], 0, 10000)) {
                $problems[] = str_replace('_', ' ', $key).' must be between 0 and 10,000 basis points.';
            }
        }
        $fees = $guardrails['fee_types'] ?? [];
        if (! is_array($fees) || array_diff($fees, config('product_factory.fee_types')) !== []) {
            $problems[] = 'Fee types must come from the approved list: '.implode(', ', config('product_factory.fee_types')).'.';
        }
        $classes = $guardrails['asset_classes'] ?? [];
        if (! is_array($classes) || array_diff($classes, array_keys(config('asset_registry.classes'))) !== []) {
            $problems[] = 'Asset classes must come from the asset registry.';
        } elseif ($classes === [] && in_array($family, config('product_factory.asset_families'), true)) {
            $problems[] = 'An asset finance template must name the asset classes it finances.';
        }
        $disclosures = $guardrails['required_disclosures'] ?? [];
        if (! is_array($disclosures) || $disclosures === [] || array_diff($disclosures, config('product_factory.disclosure_keys')) !== []) {
            $problems[] = 'Name the required disclosures from the approved list: '.implode(', ', config('product_factory.disclosure_keys')).'.';
        }

        return $problems;
    }

    /** @return list<string> plain-language problems with a product against its template */
    public function productProblems(ProductTemplate $template, string $contractType, array $parameters, array $disclosure): array
    {
        $bounds = $template->guardrails;
        $problems = [];
        if (! in_array($contractType, $template->contract_types, true)) {
            $problems[] = 'This contract type is not allowed by the template.';
        }
        $tenor = $bounds['tenor_months'];
        if (! $this->range($parameters['tenor_months'] ?? null, (int) $tenor['min'], (int) $tenor['max'])) {
            $problems[] = "The tenor must sit within {$tenor['min']} to {$tenor['max']} months.";
        }
        if (! $this->whole($parameters['price_bps'] ?? null, 0, (int) $bounds['price_max_bps'])) {
            $problems[] = 'The '.($template->rail === 'ISLAMIC' ? 'profit rate' : 'APR')." must be at most {$bounds['price_max_bps']} basis points.";
        }
        if (($bounds['deposit_min_bps'] ?? null) !== null && ! $this->whole($parameters['deposit_bps'] ?? null, (int) $bounds['deposit_min_bps'], 10000)) {
            $problems[] = "A deposit of at least {$bounds['deposit_min_bps']} basis points is required.";
        }
        if (($bounds['ltv_max_bps'] ?? null) !== null && ! $this->whole($parameters['ltv_bps'] ?? null, 0, (int) $bounds['ltv_max_bps'])) {
            $problems[] = "Financing must be at most {$bounds['ltv_max_bps']} basis points of the asset value.";
        }
        $fees = $parameters['fees'] ?? [];
        foreach (is_array($fees) ? $fees : [null] as $fee) {
            if (! is_array($fee) || ! in_array($fee['type'] ?? null, $bounds['fee_types'] ?? [], true)) {
                $problems[] = 'A fee type is not allowed by the template.';
            } elseif (! $this->whole($fee['bps'] ?? null, 0, (int) ($bounds['fee_max_bps'] ?? 0))) {
                $problems[] = "The {$fee['type']} fee is above the template's cap.";
            }
        }
        $classes = $parameters['asset_classes'] ?? [];
        if (! is_array($classes) || array_diff($classes, $bounds['asset_classes'] ?? []) !== []) {
            $problems[] = 'An asset class is not allowed by the template.';
        } elseif ($classes === [] && ($bounds['asset_classes'] ?? []) !== []) {
            $problems[] = 'Choose the asset classes this product finances.';
        }
        foreach ($bounds['required_disclosures'] ?? [] as $key) {
            if (trim((string) ($disclosure[$key] ?? '')) === '') {
                $problems[] = 'Add the required disclosure: '.str_replace('_', ' ', $key).'.';
            }
        }

        return $problems;
    }

    private function range(mixed $range, int $min, int $max): bool
    {
        return is_array($range) && $this->whole($range['min'] ?? null, $min, $max)
            && $this->whole($range['max'] ?? null, $min, $max) && $range['min'] <= $range['max'];
    }

    private function whole(mixed $value, int $min, int $max): bool
    {
        return is_int($value) && $value >= $min && $value <= $max;
    }
}
