<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\MobileMoneyTransaction;
use App\Models\RevenueEvent;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubscriptionBillingService
{
    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly TaxEngineService $taxes,
        private readonly RevenueAccountingService $revenue,
        private readonly AuditLogger $auditLogger,
        private readonly VerifiedWalletService $wallets,
    ) {}

    public function subscribe(
        FinancialSpace $space,
        object $plan,
        User $user,
        string $idempotencyKey,
        ?int $walletId = null,
    ): array {
        $existing = DB::table('subscription_contracts')
            ->where('financial_space_id', $space->id)
            ->where('opfin_plan_id', $plan->id)
            ->whereIn('status', ['pending_payment', 'active'])
            ->latest('id')
            ->first();

        if ($existing && $existing->status === 'active') {
            return ['contract' => $existing, 'invoice' => null, 'mobile_money' => null];
        }

        $contract = $existing;
        if (! $contract) {
            $contractId = DB::table('subscription_contracts')->insertGetId([
                'contract_reference' => 'OPF-SUB-'.Str::upper(Str::random(18)),
                'financial_space_id' => $space->id,
                'opfin_plan_id' => $plan->id,
                'subscribed_by_user_id' => $user->id,
                'status' => (int) $plan->price_minor > 0 ? 'pending_payment' : 'active',
                'price_minor' => (int) $plan->price_minor,
                'currency' => strtoupper((string) $plan->currency),
                'billing_period' => $plan->billing_period,
                'starts_at' => (int) $plan->price_minor > 0 ? null : now(),
                'next_billing_at' => (int) $plan->price_minor > 0 ? null : $this->nextBillingAt($plan->billing_period),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $contract = DB::table('subscription_contracts')->find($contractId);
        }

        if ((int) $plan->price_minor <= 0) {
            $this->activateEntitlements($space->id, $plan);
            return ['contract' => $contract, 'invoice' => null, 'mobile_money' => null];
        }

        $tax = $this->taxes->quoteRevenue('subscription', (int) $plan->price_minor, 0, (string) $plan->currency);
        $invoice = DB::table('billing_invoices')->where('subscription_contract_id', $contract->id)
            ->whereIn('status', ['payment_pending', 'paid'])->latest('id')->first();

        if (! $invoice) {
            $publicId = (string) Str::uuid();
            $invoiceId = DB::table('billing_invoices')->insertGetId([
                'public_id' => $publicId,
                'invoice_number' => 'OPF-INV-'.Str::upper(substr(str_replace('-', '', $publicId), 0, 18)),
                'subscription_contract_id' => $contract->id,
                'financial_space_id' => $space->id,
                'user_id' => $user->id,
                'net_amount_minor' => (int) $tax['opfin_amount_minor'],
                'tax_amount_minor' => (int) $tax['tax_amount_minor'],
                'total_amount_minor' => (int) $tax['invoice_total_minor'],
                'currency' => strtoupper((string) $plan->currency),
                'status' => 'payment_pending',
                'due_at' => now()->addDay(),
                'metadata' => json_encode([
                    'plan_code' => $plan->code,
                    'commercial_amount_minor' => (int) $plan->price_minor,
                    'tax_quote' => $tax,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $invoice = DB::table('billing_invoices')->find($invoiceId);
        }

        if ($invoice->status === 'paid') {
            return ['contract' => DB::table('subscription_contracts')->find($contract->id), 'invoice' => $invoice, 'mobile_money' => null];
        }

        $paymentTarget = $this->wallets->forRepayment($user, $walletId);
        $phone = $paymentTarget['phone'];

        $money = $this->mobileMoney->collect([
            'user_id' => $user->id,
            'institution_id' => $user->institution_id,
            'amount_minor' => (int) $invoice->total_amount_minor,
            'currency' => (string) $invoice->currency,
            'phone' => $phone,
            'idempotency_key' => trim($idempotencyKey),
            'internal_reference' => $invoice->invoice_number,
            'description' => 'OpFin subscription invoice',
            'purpose' => 'opfin_subscription',
            'billing_invoice_id' => (int) $invoice->id,
        ]);

        DB::table('billing_invoices')->where('id', $invoice->id)->update([
            'mobile_money_transaction_id' => $money->id,
            'updated_at' => now(),
        ]);

        $this->sync($money->fresh());

        return [
            'contract' => DB::table('subscription_contracts')->find($contract->id),
            'invoice' => DB::table('billing_invoices')->find($invoice->id),
            'mobile_money' => $money->fresh(),
        ];
    }

    public function sync(MobileMoneyTransaction $money): ?object
    {
        $invoiceId = (int) ($money->metadata['billing_invoice_id'] ?? 0);
        if ($invoiceId <= 0 || ($money->metadata['purpose'] ?? null) !== 'opfin_subscription') {
            return null;
        }

        $invoice = DB::table('billing_invoices')->where('id', $invoiceId)->first();
        if (! $invoice) return null;
        $contract = DB::table('subscription_contracts')->where('id', $invoice->subscription_contract_id)->first();
        if (! $contract) return null;
        $plan = DB::table('opfin_plans')->where('id', $contract->opfin_plan_id)->first();
        if (! $plan) return null;

        if ($money->status === MobileMoneyTransaction::STATUS_FAILED) {
            DB::table('billing_invoices')->where('id', $invoice->id)->update(['status' => 'payment_failed', 'updated_at' => now()]);
            $money->update(['accounting_status' => MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED]);
            return DB::table('billing_invoices')->find($invoice->id);
        }

        if ($money->status === MobileMoneyTransaction::STATUS_REVERSED) {
            if ($invoice->status === 'paid' && $invoice->revenue_event_id) {
                $event = RevenueEvent::query()->find($invoice->revenue_event_id);
                if ($event) {
                    $this->revenue->reverseSettled($event, $money);
                }
                DB::transaction(function () use ($invoice, $contract) {
                    DB::table('billing_invoices')->where('id', $invoice->id)->update(['status' => 'reversed', 'updated_at' => now()]);
                    DB::table('subscription_contracts')->where('id', $contract->id)->update(['status' => 'payment_reversed', 'updated_at' => now()]);
                    DB::table('financial_space_entitlements')->where('financial_space_id', $contract->financial_space_id)
                        ->update(['status' => 'suspended', 'ends_at' => now(), 'updated_at' => now()]);
                });
            }
            return DB::table('billing_invoices')->find($invoice->id);
        }

        if ($money->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL || $invoice->status === 'paid') {
            return $invoice;
        }

        return DB::transaction(function () use ($money, $invoice, $contract, $plan) {
            $lockedInvoice = DB::table('billing_invoices')->where('id', $invoice->id)->lockForUpdate()->first();
            if (! $lockedInvoice || $lockedInvoice->status === 'paid') return $lockedInvoice;
            if ((int) $money->amount_minor !== (int) $lockedInvoice->total_amount_minor) {
                throw new InvalidArgumentException('Subscription payment does not match the frozen invoice total.');
            }

            $event = $this->revenue->recogniseSettled([
                'event_type' => 'subscription',
                'gross_amount_minor' => (int) $plan->price_minor,
                'partner_amount_minor' => 0,
                'currency' => (string) $plan->currency,
                'financial_space_id' => $contract->financial_space_id,
                'user_id' => $contract->subscribed_by_user_id,
                'source_type' => 'billing_invoice',
                'source_reference' => $lockedInvoice->public_id,
                'occurrence_key' => $lockedInvoice->public_id,
                'metadata' => [
                    'subscription_contract_id' => $contract->id,
                    'opfin_plan_id' => $plan->id,
                    'billing_period' => $plan->billing_period,
                ],
            ], $money);

            DB::table('billing_invoices')->where('id', $lockedInvoice->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'revenue_event_id' => $event->id,
                'updated_at' => now(),
            ]);
            DB::table('subscription_contracts')->where('id', $contract->id)->update([
                'status' => 'active',
                'starts_at' => $contract->starts_at ?: now(),
                'next_billing_at' => $this->nextBillingAt($plan->billing_period),
                'updated_at' => now(),
            ]);
            $this->activateEntitlements((int) $contract->financial_space_id, $plan);

            $this->auditLogger->record('subscription.payment_settled', null, $event, [
                'subscription_contract_id' => $contract->id,
                'billing_invoice_id' => $lockedInvoice->id,
                'mobile_money_transaction_id' => $money->id,
            ]);

            return DB::table('billing_invoices')->find($lockedInvoice->id);
        });
    }

    private function activateEntitlements(int $spaceId, object $plan): void
    {
        DB::table('financial_space_entitlements')->where('financial_space_id', $spaceId)
            ->update(['status' => 'replaced', 'ends_at' => now(), 'updated_at' => now()]);
        $keys = (array) (json_decode((string) ($plan->metadata ?? '{}'), true)['entitlements'] ?? []);
        foreach ($keys as $key) {
            DB::table('financial_space_entitlements')->updateOrInsert(
                ['financial_space_id' => $spaceId, 'entitlement_key' => $key],
                [
                    'opfin_plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function nextBillingAt(?string $period): ?Carbon
    {
        return match (strtolower((string) $period)) {
            'monthly' => now()->addMonthNoOverflow(),
            'quarterly' => now()->addMonthsNoOverflow(3),
            'annual', 'annually', 'yearly' => now()->addYear(),
            default => null,
        };
    }
}
