<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\MobileMoneyTransaction;
use App\Models\RevenueEvent;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RevenueAccountingService
{
    public function __construct(
        private readonly TaxEngineService $taxes,
        private readonly EfrisService $efris,
        private readonly ProductionLedgerService $ledger,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function accrue(array $data, User $actor): RevenueEvent
    {
        $eventType = (string) $data['event_type'];
        $currency = strtoupper((string) ($data['currency'] ?? 'UGX'));
        $commercialAmount = (int) $data['gross_amount_minor'];
        $partnerAmount = (int) ($data['partner_amount_minor'] ?? 0);
        $tax = $this->taxes->quoteRevenue($eventType, $commercialAmount, $partnerAmount, $currency);
        $occurrenceKey = (string) ($data['occurrence_key'] ?? Str::uuid());

        $existing = RevenueEvent::query()->where('occurrence_key', $occurrenceKey)->first();
        if ($existing) {
            return $existing;
        }

        $event = RevenueEvent::create([
            'public_id' => (string) Str::uuid(),
            'occurrence_key' => $occurrenceKey,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'user_id' => $data['user_id'] ?? $actor->id,
            'partner_id' => $data['partner_id'] ?? null,
            'partner_product_id' => $data['partner_product_id'] ?? null,
            'commercial_agreement_id' => $data['commercial_agreement_id'] ?? null,
            'event_type' => $eventType,
            'source_type' => $data['source_type'] ?? null,
            'source_reference' => $data['source_reference'] ?? null,
            'gross_amount_minor' => $tax['invoice_total_minor'],
            'opfin_amount_minor' => $tax['opfin_amount_minor'],
            'partner_amount_minor' => $tax['partner_amount_minor'],
            'tax_amount_minor' => $tax['tax_amount_minor'],
            'currency' => $currency,
            'status' => 'accrued',
            'accounting_status' => 'unposted',
            'statement_reconciliation_status' => 'unreconciled',
            'occurred_at' => $data['occurred_at'] ?? now(),
            'metadata' => array_merge((array) ($data['metadata'] ?? []), [
                'commercial_amount_minor' => $commercialAmount,
                'tax_quote' => $tax,
            ]),
        ]);

        $posted = $this->ledger->post(
            'revenue.accrual:'.$event->public_id,
            'revenue.accrual',
            $event,
            $this->economicEntries($event, null, false),
            $actor,
            $currency,
            ['revenue_event_id' => $event->id, 'occurrence_key' => $occurrenceKey],
        );
        $event->update([
            'ledger_transaction_id' => $posted->id,
            'accounting_status' => 'posted',
        ]);

        $this->taxes->record($event->id, $tax, $posted->id);
        $this->efris->createForRevenue($event->id, $tax);
        $this->auditLogger->record('revenue.accrued', $actor, $event, ['ledger_reference' => $posted->reference]);

        return $event->fresh();
    }

    public function recogniseSettled(array $data, MobileMoneyTransaction $money, ?User $actor = null): RevenueEvent
    {
        if ($money->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            throw new InvalidArgumentException('Settled revenue requires provider-confirmed successful money movement.');
        }

        $eventType = (string) $data['event_type'];
        $currency = strtoupper((string) ($data['currency'] ?? $money->currency));
        $commercialAmount = (int) $data['gross_amount_minor'];
        $partnerAmount = (int) ($data['partner_amount_minor'] ?? 0);
        $tax = $this->taxes->quoteRevenue($eventType, $commercialAmount, $partnerAmount, $currency);
        if ((int) $money->amount_minor !== (int) $tax['invoice_total_minor'] || strtoupper((string) $money->currency) !== $currency) {
            throw new InvalidArgumentException('Provider settlement does not match the governed revenue/tax economics.');
        }

        $occurrenceKey = (string) ($data['occurrence_key'] ?? Str::uuid());
        $existing = RevenueEvent::query()->where('occurrence_key', $occurrenceKey)->first();
        if ($existing) {
            return $existing;
        }

        $event = RevenueEvent::create([
            'public_id' => (string) Str::uuid(),
            'occurrence_key' => $occurrenceKey,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'user_id' => $data['user_id'] ?? $money->user_id,
            'partner_id' => $data['partner_id'] ?? null,
            'partner_product_id' => $data['partner_product_id'] ?? null,
            'commercial_agreement_id' => $data['commercial_agreement_id'] ?? null,
            'event_type' => $eventType,
            'source_type' => $data['source_type'] ?? null,
            'source_reference' => $data['source_reference'] ?? null,
            'gross_amount_minor' => $tax['invoice_total_minor'],
            'opfin_amount_minor' => $tax['opfin_amount_minor'],
            'partner_amount_minor' => $tax['partner_amount_minor'],
            'tax_amount_minor' => $tax['tax_amount_minor'],
            'currency' => $currency,
            'status' => 'settled',
            'accounting_status' => 'unposted',
            'statement_reconciliation_status' => $money->statement_reconciliation_status,
            'cpay_reference' => $money->provider_reference,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'settled_at' => now(),
            'metadata' => array_merge((array) ($data['metadata'] ?? []), [
                'commercial_amount_minor' => $commercialAmount,
                'tax_quote' => $tax,
                'mobile_money_transaction_id' => $money->id,
            ]),
        ]);

        $posted = $this->ledger->post(
            'revenue.cash:'.$event->public_id,
            'revenue.cash',
            $event,
            $this->economicEntries($event, $money, true),
            $actor,
            $currency,
            ['revenue_event_id' => $event->id, 'mobile_money_transaction_id' => $money->id],
        );
        $event->update([
            'ledger_transaction_id' => $posted->id,
            'accounting_status' => 'posted',
        ]);
        $money->update([
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
            'accounting_posted_at' => now(),
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        $this->taxes->record($event->id, $tax, $posted->id);
        $this->efris->createForRevenue($event->id, $tax);
        $this->auditLogger->record('revenue.settled', $actor, $event, ['ledger_reference' => $posted->reference]);

        return $event->fresh();
    }

    public function settleAccrued(RevenueEvent $event, MobileMoneyTransaction $money, User $actor): RevenueEvent
    {
        if ($event->status !== 'accrued') {
            throw new InvalidArgumentException('Only accrued revenue can be cash-settled.');
        }
        if ($money->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL
            || (int) $money->amount_minor !== (int) $event->gross_amount_minor
            || strtoupper((string) $money->currency) !== strtoupper((string) $event->currency)) {
            throw new InvalidArgumentException('Provider payment does not match the accrued revenue event.');
        }

        $posted = $this->ledger->post(
            'revenue.settlement:'.$event->public_id,
            'revenue.settlement',
            $event,
            [
                [
                    'account_id' => $this->cashAccount($money)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => (int) $event->gross_amount_minor,
                    'memo' => 'Cash received for accrued revenue',
                ],
                [
                    'account_id' => $this->receivableAccount($event)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => (int) $event->gross_amount_minor,
                    'memo' => 'Revenue receivable settled',
                ],
            ],
            $actor,
            $event->currency,
            ['revenue_event_id' => $event->id, 'mobile_money_transaction_id' => $money->id],
        );

        $event->update([
            'status' => 'settled',
            'cpay_reference' => $money->provider_reference,
            'settled_at' => now(),
            'statement_reconciliation_status' => $money->statement_reconciliation_status,
            'metadata' => array_merge($event->metadata ?? [], ['settlement_ledger_reference' => $posted->reference]),
        ]);
        $money->update([
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
            'accounting_posted_at' => now(),
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        return $event->fresh();
    }

    public function reverseSettled(RevenueEvent $event, MobileMoneyTransaction $money, ?User $actor = null): RevenueEvent
    {
        if ($event->status === 'reversed') {
            return $event;
        }
        if ($money->status !== MobileMoneyTransaction::STATUS_REVERSED) {
            throw new InvalidArgumentException('Revenue reversal requires provider-confirmed reversed money movement.');
        }

        $reference = 'revenue.reversal:'.$event->public_id;
        if (! \App\Models\LedgerTransaction::where('reference', $reference)->exists()) {
            $entries = [[
                'account_id' => $this->cashAccount($money)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => (int) $event->gross_amount_minor,
                'memo' => 'Provider reversed revenue collection',
            ]];

            $this->debit($entries, $this->incomeAccount($event), (int) $event->opfin_amount_minor, 'Revenue reversed');
            if ((int) $event->partner_amount_minor > 0) {
                $this->debit($entries, $this->partnerPayableAccount($event), (int) $event->partner_amount_minor, 'Partner share reversed');
            }
            if ((int) $event->tax_amount_minor > 0) {
                $this->debit($entries, $this->taxPayableAccount($event), (int) $event->tax_amount_minor, 'Tax liability reversed');
            }

            $this->ledger->post(
                $reference,
                'revenue.reversal',
                $event,
                $entries,
                $actor,
                $event->currency,
                ['revenue_event_id' => $event->id, 'mobile_money_transaction_id' => $money->id],
            );
        }

        $event->update([
            'status' => 'reversed',
            'statement_reconciliation_status' => $money->statement_reconciliation_status,
        ]);
        $money->update([
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_POSTED,
            'accounting_posted_at' => now(),
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);
        $this->auditLogger->record('revenue.reversed', $actor, $event, ['provider_reference' => $money->provider_reference]);

        return $event->fresh();
    }

    private function economicEntries(RevenueEvent $event, ?MobileMoneyTransaction $money, bool $cash): array
    {
        $entries = [[
            'account_id' => ($cash ? $this->cashAccount($money) : $this->receivableAccount($event))->id,
            'direction' => LedgerEntry::DIRECTION_DEBIT,
            'amount_minor' => (int) $event->gross_amount_minor,
            'memo' => $cash ? 'Cash received for revenue' : 'Revenue receivable accrued',
        ]];

        $this->credit($entries, $this->incomeAccount($event), (int) $event->opfin_amount_minor, 'OpFin revenue recognised');
        if ((int) $event->partner_amount_minor > 0) {
            $this->credit($entries, $this->partnerPayableAccount($event), (int) $event->partner_amount_minor, 'Partner share payable');
        }
        if ((int) $event->tax_amount_minor > 0) {
            $this->credit($entries, $this->taxPayableAccount($event), (int) $event->tax_amount_minor, 'Tax payable');
        }

        return $entries;
    }

    private function cashAccount(?MobileMoneyTransaction $money): LedgerAccount
    {
        if (! $money) {
            throw new InvalidArgumentException('Cash revenue posting requires a money movement.');
        }
        return $this->account(
            'cash.'.strtolower((string) $money->provider).'.collection',
            ucfirst((string) $money->provider).' collection cash',
            'asset',
            (string) $money->currency,
        );
    }

    private function receivableAccount(RevenueEvent $event): LedgerAccount
    {
        return $this->account(
            'asset.revenue_receivable.'.strtolower($event->event_type),
            ucfirst(str_replace('_', ' ', $event->event_type)).' revenue receivable',
            'asset',
            $event->currency,
        );
    }

    private function incomeAccount(RevenueEvent $event): LedgerAccount
    {
        return $this->account(
            'income.platform.'.strtolower($event->event_type),
            ucfirst(str_replace('_', ' ', $event->event_type)).' revenue',
            'income',
            $event->currency,
        );
    }

    private function partnerPayableAccount(RevenueEvent $event): LedgerAccount
    {
        $suffix = $event->partner_id ? 'partner_'.$event->partner_id : 'unallocated';
        return $this->account(
            'liability.partner_payable.'.$suffix,
            'Partner payable '.$suffix,
            'liability',
            $event->currency,
        );
    }

    private function taxPayableAccount(RevenueEvent $event): LedgerAccount
    {
        $taxType = (string) (($event->metadata['tax_quote']['tax_type'] ?? 'tax'));
        return $this->account(
            'liability.tax_payable.'.strtolower($taxType),
            strtoupper($taxType).' payable',
            'liability',
            $event->currency,
        );
    }

    private function credit(array &$entries, LedgerAccount $account, int $amount, string $memo): void
    {
        if ($amount <= 0) return;
        $entries[] = ['account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_CREDIT, 'amount_minor' => $amount, 'memo' => $memo];
    }

    private function debit(array &$entries, LedgerAccount $account, int $amount, string $memo): void
    {
        if ($amount <= 0) return;
        $entries[] = ['account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_DEBIT, 'amount_minor' => $amount, 'memo' => $memo];
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);
        $account = LedgerAccount::query()->where('code', $code)->first();
        if ($account) {
            if (strtoupper((string) $account->currency) !== $currency || ! $account->is_active) {
                throw new InvalidArgumentException("Ledger account {$code} is unavailable for {$currency} postings.");
            }
            return $account;
        }

        return LedgerAccount::create([
            'code' => $code, 'name' => $name, 'type' => $type, 'currency' => $currency, 'is_active' => true,
        ]);
    }
}
