<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Provisional, read-only ledger study for isolated development environments.
 * No bank source, sign-off, provider call, borrower mutation or GL write occurs.
 * A pro-forma entry MUST NOT be reused by ProductionLedgerService.
 */
final class InstitutionalSharedAccountingPreviewService
{
    public function preview(array $input): array
    {
        if (! app()->environment('local', 'testing', 'staging')
            || config('opfin.accounting.defer_institutional_evidence_for_nonproduction', false) !== true) {
            throw new InvalidArgumentException('Provisional shared accounting is restricted to explicitly deferred non-production environments.');
        }

        $form = $input['contract_form'] ?? null;
        $currency = $input['currency'] ?? null;
        $principal = $input['principal_amount_minor'] ?? null;
        $cash = $input['cash_disbursement_minor'] ?? null;
        $basisPoints = $input['opfin_share_bps'] ?? null;

        if (! in_array($form, ['direct_joint_creditor', 'funded_participation', 'financial_guarantee', 'first_loss'], true)
            || ! is_string($currency) || ! preg_match('/^[A-Z]{3}$/D', $currency)
            || ! is_int($principal) || $principal <= 0 || $principal > 1000000000000
            || ! is_int($cash) || $cash <= 0 || $cash > $principal
            || ! is_int($basisPoints) || $basisPoints <= 0 || $basisPoints >= 10000) {
            throw new InvalidArgumentException('A legal form, ISO currency, positive principal/net cash and a partial share in basis points are required.');
        }

        $result = [
            'mode' => 'synthetic_read_only',
            'evidence_gate' => 'external_evidence_deferred_for_nonproduction_only',
            'release_approved' => false,
            'shared_origination_enabled' => false,
            'contract_form' => $form, 'currency' => $currency,
            'principal_amount_minor' => $principal,
            'cash_disbursement_minor' => $cash,
            'opfin_share_bps' => $basisPoints,
            'partner_share_bps' => 10000 - $basisPoints,
            'ledger_posted' => false,
            'journals' => [],
            'outstanding_work' => [
                'contractual_recognition_and_derecognition',
                'provider_finality_and_custody_transfer',
                'withheld_and_financed_fee_allocation',
                'interest_and_collection_waterfall',
                'loan_level_ecl_and_write_off',
                'reversals_recoveries_rounding_and_validation',
            ],
        ];

        // A lower cash payout could include retained or financed charges. Do not
        // invent the fee recognition needed to balance that difference.
        if ($cash !== $principal) {
            $result['status'] = 'fee_policy_required';
            $result['reason'] = 'Principal and borrower cash differ; deferred fees and funding split cannot be guessed.';
            return $result;
        }

        if ($form !== 'direct_joint_creditor') {
            $result['status'] = 'contract_classification_required';
            $result['reason'] = match ($form) {
                'funded_participation' => 'A participation may require full asset recognition plus a financing liability, not pro-rata lending.',
                'financial_guarantee' => 'The issuer may recognise a financial guarantee obligation, not a share of the underlying receivable.',
                'first_loss' => 'Priority of losses, fair value and legal rights must determine liability or tranche accounting.',
            };
            return $result;
        }

        // Deterministic minor-unit rounding: the lender receives the remainder.
        // This is an illustrative TWO-FUNDER split with separate settlement legs,
        // NOT the journal for a single combined-provider borrower payout.
        $opfin = intdiv($principal * $basisPoints, 10000);
        $partner = $principal - $opfin;
        if ($opfin <= 0 || $partner <= 0) {
            $result['status'] = 'unrepresentable_minor_unit_split';
            $result['reason'] = 'The selected share is smaller than one currency minor unit.';
            return $result;
        }

        $result['status'] = 'provisional_direct_joint_creditor';
        $result['illustrative_opfin_principal_minor'] = $opfin;
        $result['illustrative_partner_principal_minor'] = $partner;
        $result['journals'] = [
            [
                'illustration' => 'platform_funded_credit_leg',
                'debit' => ['code' => 'asset.platform_owned_loan_receivable', 'amount_minor' => $opfin],
                'credit' => ['code' => 'cash.platform_clearing_provider', 'amount_minor' => $opfin],
            ],
            [
                'illustration' => 'partner_funded_custodial_leg',
                'debit' => ['code' => 'liability.partner_funds_held', 'amount_minor' => $partner],
                'credit' => ['code' => 'cash.partner_custody_provider', 'amount_minor' => $partner],
            ],
        ];
        $result['reason'] = 'Two segregated legal funding legs, no fees. Neither leg is executable without verified provider finality, split receivable book and signed ledger acceptance.';
        return $result;
    }
}
