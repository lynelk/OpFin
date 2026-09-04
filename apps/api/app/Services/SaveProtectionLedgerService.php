<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\MobileMoneyTransaction;
use App\Models\ProtectionPremiumPayment;
use App\Models\SavingsMovement;
use App\Models\User;

class SaveProtectionLedgerService
{
    public function __construct(private readonly ProductionLedgerService $ledger) {}

    public function postSavingsCollection(
        SavingsMovement $movement,
        MobileMoneyTransaction $mobileMoney,
    ): ?LedgerTransaction {
        $reference = 'savings.collection:'.$movement->movement_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $product = $movement->goal->product;

        return $this->ledger->post(
            $reference,
            'savings.contribution_collected',
            $movement,
            [
                [
                    'account_id' => $this->cashAccount($mobileMoney->provider, 'collection', $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Customer savings contribution collected pending partner settlement',
                ],
                [
                    'account_id' => $this->savingsPartnerPayableAccount($product->id, $product->partner_name, $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Amount payable to regulated savings partner',
                ],
            ],
            null,
            $movement->currency,
            [
                'savings_goal_id' => $movement->savings_goal_id,
                'savings_product_id' => $product->id,
                'mobile_money_transaction_id' => $mobileMoney->id,
                'partner_name' => $product->partner_name,
                'custody_model' => $product->custody_model,
            ],
        );
    }

    public function postSavingsPartnerSettlement(SavingsMovement $movement, ?User $actor = null): ?LedgerTransaction
    {
        $reference = 'savings.partner_settlement:'.$movement->movement_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $product = $movement->goal->product;
        $provider = $movement->mobileMoneyTransaction?->provider ?: 'partner';

        return $this->ledger->post(
            $reference,
            'savings.partner_settled',
            $movement,
            [
                [
                    'account_id' => $this->savingsPartnerPayableAccount($product->id, $product->partner_name, $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Savings partner payable cleared on partner confirmation',
                ],
                [
                    'account_id' => $this->cashAccount($provider, 'collection', $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Collected funds settled to regulated savings partner',
                ],
            ],
            $actor,
            $movement->currency,
            [
                'savings_goal_id' => $movement->savings_goal_id,
                'partner_reference' => $movement->partner_reference,
                'partner_evidence_hash' => $movement->partner_evidence_hash,
            ],
        );
    }

    public function postSavingsWithdrawalRelease(SavingsMovement $movement, ?User $actor = null): ?LedgerTransaction
    {
        $reference = 'savings.withdrawal_release:'.$movement->movement_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        return $this->ledger->post(
            $reference,
            'savings.withdrawal_partner_release',
            $movement,
            [
                [
                    'account_id' => $this->cashAccount('partner', 'collection', $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Funds released by savings partner for customer withdrawal',
                ],
                [
                    'account_id' => $this->withdrawalPayableAccount($movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Customer savings withdrawal payable created',
                ],
            ],
            $actor,
            $movement->currency,
            [
                'savings_goal_id' => $movement->savings_goal_id,
                'partner_reference' => $movement->partner_reference,
                'partner_evidence_hash' => $movement->partner_evidence_hash,
            ],
        );
    }

    public function postSavingsPayout(
        SavingsMovement $movement,
        MobileMoneyTransaction $mobileMoney,
    ): ?LedgerTransaction {
        $reference = 'savings.withdrawal_payout:'.$movement->movement_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        return $this->ledger->post(
            $reference,
            'savings.withdrawal_paid',
            $movement,
            [
                [
                    'account_id' => $this->withdrawalPayableAccount($movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Customer savings withdrawal payable settled',
                ],
                [
                    'account_id' => $this->cashAccount($mobileMoney->provider, 'disbursement', $movement->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $movement->amount_minor,
                    'memo' => 'Savings withdrawal paid through CPay',
                ],
            ],
            null,
            $movement->currency,
            [
                'savings_goal_id' => $movement->savings_goal_id,
                'mobile_money_transaction_id' => $mobileMoney->id,
                'provider_reference' => $mobileMoney->provider_reference,
            ],
        );
    }

    public function postProtectionPremiumCollection(
        ProtectionPremiumPayment $payment,
        MobileMoneyTransaction $mobileMoney,
    ): ?LedgerTransaction {
        $reference = 'protection.premium_collection:'.$payment->payment_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $product = $payment->policy->product;

        return $this->ledger->post(
            $reference,
            'protection.premium_collected',
            $payment,
            [
                [
                    'account_id' => $this->cashAccount($mobileMoney->provider, 'collection', $payment->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $payment->amount_minor,
                    'memo' => 'Protection premium collected pending insurer settlement',
                ],
                [
                    'account_id' => $this->insurerPayableAccount($product->id, $product->insurer_name, $payment->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $payment->amount_minor,
                    'memo' => 'Premium payable to insurer or underwriter',
                ],
            ],
            null,
            $payment->currency,
            [
                'protection_policy_id' => $payment->protection_policy_id,
                'protection_product_id' => $product->id,
                'mobile_money_transaction_id' => $mobileMoney->id,
                'insurer_name' => $product->insurer_name,
            ],
        );
    }

    public function postProtectionPremiumSettlement(
        ProtectionPremiumPayment $payment,
        ?User $actor = null,
    ): ?LedgerTransaction {
        $reference = 'protection.premium_settlement:'.$payment->payment_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $product = $payment->policy->product;
        $provider = $payment->mobileMoneyTransaction?->provider ?: 'partner';

        return $this->ledger->post(
            $reference,
            'protection.premium_partner_settled',
            $payment,
            [
                [
                    'account_id' => $this->insurerPayableAccount($product->id, $product->insurer_name, $payment->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $payment->amount_minor,
                    'memo' => 'Insurer premium payable cleared',
                ],
                [
                    'account_id' => $this->cashAccount($provider, 'collection', $payment->currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $payment->amount_minor,
                    'memo' => 'Premium settled to insurer or underwriter',
                ],
            ],
            $actor,
            $payment->currency,
            [
                'protection_policy_id' => $payment->protection_policy_id,
                'partner_reference' => $payment->partner_reference,
                'partner_evidence_hash' => $payment->partner_evidence_hash,
            ],
        );
    }

    private function cashAccount(string $provider, string $purpose, string $currency): LedgerAccount
    {
        $provider = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $provider) ?: 'unknown');

        return $this->account(
            "cash.{$provider}.{$purpose}",
            ucfirst($provider).' '.str_replace('_', ' ', $purpose).' cash',
            'asset',
            $currency,
        );
    }

    private function savingsPartnerPayableAccount(int $productId, string $partner, string $currency): LedgerAccount
    {
        return $this->account(
            "liability.savings_partner_payable.product_{$productId}",
            "Savings partner payable - {$partner}",
            'liability',
            $currency,
        );
    }

    private function insurerPayableAccount(int $productId, string $insurer, string $currency): LedgerAccount
    {
        return $this->account(
            "liability.insurer_premium_payable.product_{$productId}",
            "Insurer premium payable - {$insurer}",
            'liability',
            $currency,
        );
    }

    private function withdrawalPayableAccount(string $currency): LedgerAccount
    {
        return $this->account(
            'liability.customer_savings_withdrawal_payable',
            'Customer savings withdrawal payable',
            'liability',
            $currency,
        );
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'currency' => $currency,
                'is_active' => true,
            ],
        );
    }
}
