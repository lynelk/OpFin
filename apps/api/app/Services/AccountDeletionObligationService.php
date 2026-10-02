<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountDeletionObligationService
{
    public function forUser(User $user): array
    {
        return collect([
            ...$this->loans($user->id),
            ...$this->financingArrangements($user->id),
            ...$this->collectionInstructions($user->id),
            ...$this->personalObligations($user->id),
            ...$this->essentials($user->id),
            ...$this->peerFinance($user->id),
            ...$this->savings($user->id),
            ...$this->protection($user->id),
            ...$this->investments($user->id),
            ...$this->payroll($user->id),
            ...$this->pendingFinancialActions($user->id),
            ...$this->pendingMoneyMovements($user->id),
        ])
            ->unique(fn (array $row) => ($row['code'] ?? '').'|'.($row['reference'] ?? ''))
            ->values()
            ->all();
    }

    private function loans(int $userId): array
    {
        if (! Schema::hasTable('loans')) {
            return [];
        }

        $query = DB::table('loans as loans')
            ->where('loans.user_id', $userId)
            ->whereNotIn('loans.status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed']);

        if (Schema::hasTable('institutions')) {
            $query
                ->leftJoin('institutions as institutions', 'institutions.id', '=', 'loans.institution_id')
                ->select(
                    'loans.*',
                    'institutions.name as provider_name',
                    'institutions.phone as provider_phone',
                    'institutions.email as provider_email',
                    'institutions.address as provider_address',
                );
        } else {
            $query->select('loans.*');
        }

        return $query->get()
            ->map(function ($loan) {
                $outstanding = null;

                if (Schema::hasTable('loan_schedules') && Schema::hasColumn('loan_schedules', 'loan_id')) {
                    foreach (['total_outstanding', 'total_outstanding_minor'] as $column) {
                        if (Schema::hasColumn('loan_schedules', $column)) {
                            $outstanding = (int) DB::table('loan_schedules')
                                ->where('loan_id', $loan->id)
                                ->sum($column);
                            break;
                        }
                    }
                }

                return $this->item(
                    'active_credit',
                    'Active credit',
                    $loan->reference ?? 'loan-'.$loan->id,
                    $loan->status ?? 'active',
                    $outstanding,
                    $loan->currency ?? 'UGX',
                    $loan->repayment_start_date ?? null,
                    $this->contact(
                        $loan->provider_name ?? 'Credit provider',
                        $loan->provider_phone ?? null,
                        $loan->provider_email ?? null,
                        $loan->provider_address ?? null,
                    ),
                );
            })
            ->all();
    }

    private function financingArrangements(int $userId): array
    {
        if (! Schema::hasTable('financing_arrangements')) {
            return [];
        }

        $rows = DB::table('financing_arrangements as arrangements')
            ->leftJoin('financial_products as products', 'products.id', '=', 'arrangements.financial_product_id')
            ->leftJoin('partners as partners', 'partners.id', '=', 'products.partner_id')
            ->leftJoin('institutions as institutions', 'institutions.id', '=', 'partners.institution_id')
            ->where('arrangements.user_id', $userId)
            ->whereNotIn('arrangements.status', ['settled', 'closed', 'cancelled', 'rejected'])
            ->select('arrangements.*', 'partners.name as partner_name', 'institutions.name as provider_name',
                'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $legacyType = strtolower((string) ($row->legacy_type ?? ''));
            if (in_array($legacyType, ['loan', 'legacy_loan', 'app\\models\\loan'], true)
                && $row->legacy_id && DB::table('loans')->where('id', $row->legacy_id)->where('user_id', $userId)
                    ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])->exists()) {
                continue;
            }
            $item = $this->item('financing_arrangement', 'Financing arrangement still open', $row->reference,
                $row->status, isset($row->total_obligation_minor) ? (int) $row->total_obligation_minor : null,
                $row->currency, null, $this->contact($row->provider_name ?? $row->partner_name ?? 'Financing provider',
                    $row->provider_phone, $row->provider_email, $row->provider_address));
            $item['amount_basis'] = 'contract_total_not_current_balance';
            $items[] = $item;
        }

        return $items;
    }

    private function collectionInstructions(int $userId): array
    {
        if (! Schema::hasTable('essentials_collection_instructions')) {
            return [];
        }

        return DB::table('essentials_collection_instructions as instructions')
            ->join('essentials_repayments as repayments', 'repayments.id', '=', 'instructions.repayment_id')
            ->join('essentials_advances as advances', 'advances.id', '=', 'instructions.advance_id')
            ->leftJoin('partners as partners', 'partners.id', '=', 'advances.lender_partner_id')
            ->leftJoin('institutions as institutions', 'institutions.id', '=', 'partners.institution_id')
            ->where('instructions.user_id', $userId)
            ->where(function ($query) {
                $query->whereNotIn('instructions.status', ['applied', 'failed', 'reversed'])
                    ->orWhere(function ($reversed) {
                        $reversed->where('instructions.status', 'reversed')->where('repayments.status', '<>', 'reversed');
                    });
            })
            ->select('instructions.id', 'instructions.status', 'instructions.amount_minor', 'instructions.currency',
                'repayments.reference', 'partners.name as partner_name', 'institutions.name as provider_name',
                'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
            ->get()->map(fn ($row) => $this->item('essentials_collection_reconciliation',
                'Collection or reversal still requires reconciliation', $row->reference ?: 'collection-'.$row->id,
                $row->status, (int) $row->amount_minor, $row->currency, null,
                $this->contact($row->provider_name ?? $row->partner_name ?? 'Collection provider',
                    $row->provider_phone, $row->provider_email, $row->provider_address)))
            ->all();
    }

    private function personalObligations(int $userId): array
    {
        if (! Schema::hasTable('financial_obligations')
            || ! Schema::hasTable('financial_spaces')
            || ! Schema::hasTable('financial_space_memberships')) {
            return [];
        }

        return DB::table('financial_obligations as obligations')
            ->join('financial_spaces as spaces', 'spaces.id', '=', 'obligations.financial_space_id')
            ->join(
                'financial_space_memberships as memberships',
                'memberships.financial_space_id',
                '=',
                'spaces.id',
            )
            ->where('memberships.user_id', $userId)
            ->where('memberships.role', 'owner')
            ->where('memberships.status', 'active')
            ->whereNull('memberships.deleted_at')
            ->whereNull('spaces.deleted_at')
            ->where('spaces.type', 'personal')
            ->where('obligations.direction', 'i_owe')
            ->where('obligations.status', 'open')
            ->where('obligations.outstanding_amount_minor', '>', 0)
            ->whereNull('obligations.deleted_at')
            ->select('obligations.*')
            ->get()
            ->map(function ($obligation) {
                $metadata = $this->json($obligation->metadata ?? null);

                return $this->item(
                    'personal_obligation',
                    'Outstanding recorded obligation',
                    'personal-obligation-'.$obligation->id,
                    $obligation->status,
                    (int) $obligation->outstanding_amount_minor,
                    $obligation->currency,
                    $obligation->due_date ?? null,
                    $this->contact(
                        $obligation->counterparty_name ?: 'Recorded counterparty',
                        $metadata['phone'] ?? $metadata['contact_phone'] ?? null,
                        $metadata['email'] ?? $metadata['contact_email'] ?? null,
                        $metadata['address'] ?? null,
                    ),
                );
            })
            ->all();
    }

    private function essentials(int $userId): array
    {
        if (! Schema::hasTable('essentials_advances')) {
            return [];
        }

        $query = DB::table('essentials_advances as advances')
            ->where('advances.user_id', $userId)
            ->where(function ($builder) {
                $builder
                    ->whereNotIn('advances.status', ['settled', 'fulfilment_failed', 'lender_funding_failed'])
                    ->orWhere('advances.outstanding_minor', '>', 0)
                    ->orWhere('advances.principal_outstanding_minor', '>', 0);
            });

        if (Schema::hasTable('partners')) {
            $query->leftJoin('partners as partners', 'partners.id', '=', 'advances.lender_partner_id');

            if (Schema::hasTable('institutions')) {
                $query->leftJoin('institutions as institutions', 'institutions.id', '=', 'partners.institution_id');
            }
        }

        $select = ['advances.*'];

        if (Schema::hasTable('partners')) {
            $select[] = 'partners.name as partner_name';
            $select[] = 'partners.metadata as partner_metadata';
        }

        if (Schema::hasTable('partners') && Schema::hasTable('institutions')) {
            $select[] = 'institutions.name as provider_name';
            $select[] = 'institutions.phone as provider_phone';
            $select[] = 'institutions.email as provider_email';
            $select[] = 'institutions.address as provider_address';
        }

        $items = $query->select($select)
            ->get()
            ->map(function ($advance) {
                $metadata = $this->json($advance->partner_metadata ?? null);

                return $this->item(
                    'essentials_financing',
                    'Essentials financing',
                    $advance->reference,
                    $advance->status,
                    (int) ($advance->outstanding_minor ?? $advance->principal_outstanding_minor ?? 0),
                    $advance->currency ?? 'UGX',
                    $advance->next_due_date ?? null,
                    $this->contact(
                        $advance->provider_name ?? $advance->partner_name ?? 'Financing partner',
                        $advance->provider_phone ?? $metadata['support_phone'] ?? $metadata['phone'] ?? null,
                        $advance->provider_email ?? $metadata['support_email'] ?? $metadata['email'] ?? null,
                        $advance->provider_address ?? $metadata['address'] ?? null,
                    ),
                );
            })
            ->all();

        if (Schema::hasTable('essentials_repayments')) {
            foreach (DB::table('essentials_repayments')
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'pending_provider_confirmation'])
                ->get() as $repayment) {
                $items[] = $this->item(
                    'essentials_repayment_pending',
                    'Repayment still processing',
                    $repayment->reference,
                    $repayment->status,
                    (int) $repayment->amount_minor,
                    $repayment->currency ?? 'UGX',
                    null,
                    $this->contact('OpFin payment processing'),
                );
            }
        }

        return $items;
    }

    private function peerFinance(int $userId): array
    {
        $items = [];

        if (Schema::hasTable('participatory_finance_listings')) {
            foreach (DB::table('participatory_finance_listings')
                ->where('borrower_user_id', $userId)
                ->whereIn('status', ['awaiting_compliance_review', 'approved', 'funding', 'funded'])
                ->get() as $listing) {
                $items[] = $this->item(
                    'peer_borrowing',
                    'Peer-finance borrowing',
                    $listing->reference,
                    $listing->status,
                    $listing->status === 'funded'
                        ? max(0, (int) ($listing->funded_amount_minor ?? $listing->target_amount_minor))
                        : max(
                            0,
                            (int) $listing->target_amount_minor - (int) ($listing->funded_amount_minor ?? 0),
                        ),
                    'UGX',
                    null,
                    $this->namedInstitution($listing->lender_of_record ?? null),
                );
            }
        }

        if (Schema::hasTable('participatory_finance_commitments')) {
            $query = DB::table('participatory_finance_commitments as commitments')
                ->leftJoin(
                    'participatory_finance_listings as listings',
                    'listings.id',
                    '=',
                    'commitments.listing_id',
                )
                ->where('commitments.investor_user_id', $userId)
                ->whereIn('commitments.status', ['awaiting_step_up', 'provider_processing', 'settled'])
                ->select('commitments.*', 'listings.lender_of_record');

            foreach ($query->get() as $commitment) {
                $items[] = $this->item(
                    'peer_lending',
                    'Peer-finance investment commitment',
                    $commitment->reference,
                    $commitment->status,
                    (int) $commitment->amount_minor,
                    'UGX',
                    null,
                    $this->namedInstitution($commitment->lender_of_record ?? null),
                );
            }
        }

        return $items;
    }

    private function savings(int $userId): array
    {
        if (! Schema::hasTable('savings_goals')) {
            return [];
        }

        $query = DB::table('savings_goals as goals')
            ->where('goals.user_id', $userId);

        if (Schema::hasColumn('savings_goals', 'confirmed_balance_minor')) {
            $query->where('goals.confirmed_balance_minor', '>', 0);
        } else {
            $query->whereIn('goals.status', ['active', 'withdrawal_pending']);
        }

        if (Schema::hasTable('savings_products')) {
            $query->leftJoin('savings_products as products', 'products.id', '=', 'goals.savings_product_id');
        }

        if (Schema::hasTable('institutions') && Schema::hasColumn('savings_goals', 'institution_id')) {
            $query->leftJoin('institutions as institutions', 'institutions.id', '=', 'goals.institution_id');
        }

        $select = ['goals.*'];

        if (Schema::hasTable('savings_products')) {
            $select[] = 'products.partner_name';
        }

        if (Schema::hasTable('institutions') && Schema::hasColumn('savings_goals', 'institution_id')) {
            $select[] = 'institutions.name as provider_name';
            $select[] = 'institutions.phone as provider_phone';
            $select[] = 'institutions.email as provider_email';
            $select[] = 'institutions.address as provider_address';
        }

        $items = $query->select($select)
            ->get()
            ->map(function ($goal) {
                return $this->item(
                    'savings_position',
                    'Savings position',
                    $goal->goal_reference ?? 'savings-'.$goal->id,
                    $goal->status ?? 'active',
                    Schema::hasColumn('savings_goals', 'confirmed_balance_minor')
                        ? (int) ($goal->confirmed_balance_minor ?? 0)
                        : null,
                    'UGX',
                    $goal->target_date ?? null,
                    $this->contact(
                        $goal->provider_name ?? $goal->partner_name ?? 'Savings partner',
                        $goal->provider_phone ?? null,
                        $goal->provider_email ?? null,
                        $goal->provider_address ?? null,
                    ),
                );
            })
            ->all();

        if (Schema::hasTable('savings_movements')) {
            foreach (DB::table('savings_movements')
                ->where('user_id', $userId)
                ->whereNotIn('status', ['completed', 'failed', 'rejected', 'cancelled', 'reversed'])
                ->get() as $movement) {
                $items[] = $this->item(
                    'savings_movement_pending',
                    'Savings transaction still processing',
                    $movement->movement_reference,
                    $movement->status,
                    (int) $movement->amount_minor,
                    $movement->currency ?? 'UGX',
                    null,
                    $this->contact('Savings partner'),
                );
            }
        }

        return $items;
    }

    private function protection(int $userId): array
    {
        $items = [];

        if (Schema::hasTable('protection_policies')) {
            $query = DB::table('protection_policies as policies')
                ->where('policies.user_id', $userId)
                ->whereIn('policies.status', ['premium_due', 'premium_pending', 'pending_issuance', 'active', 'claim_pending', 'lapsed']);

            if (Schema::hasTable('protection_products')) {
                $query->leftJoin(
                    'protection_products as products',
                    'products.id',
                    '=',
                    'policies.protection_product_id',
                );
            }

            if (Schema::hasTable('institutions')) {
                $query->leftJoin(
                    'institutions as institutions',
                    'institutions.id',
                    '=',
                    'policies.institution_id',
                );
            }

            $select = ['policies.*'];

            if (Schema::hasTable('protection_products')) {
                $select[] = 'products.insurer_name';
                $select[] = 'products.underwriter_name';
            }

            if (Schema::hasTable('institutions')) {
                $select[] = 'institutions.name as provider_name';
                $select[] = 'institutions.phone as provider_phone';
                $select[] = 'institutions.email as provider_email';
                $select[] = 'institutions.address as provider_address';
            }

            foreach ($query->select($select)->get() as $policy) {
                $items[] = $this->item(
                    'protection_policy',
                    'Active protection policy',
                    $policy->policy_reference,
                    $policy->status,
                    (int) $policy->premium_amount_minor,
                    'UGX',
                    $policy->next_premium_due_date ?? $policy->cover_end_date ?? null,
                    $this->contact(
                        $policy->provider_name ?? $policy->insurer_name ?? $policy->underwriter_name ?? 'Insurance partner',
                        $policy->provider_phone ?? null,
                        $policy->provider_email ?? null,
                        $policy->provider_address ?? null,
                    ),
                );
            }
        }

        if (Schema::hasTable('protection_premium_payments')) {
            foreach (DB::table('protection_premium_payments as payments')
                ->join('protection_policies as policies', 'policies.id', '=', 'payments.protection_policy_id')
                ->join('protection_products as products', 'products.id', '=', 'policies.protection_product_id')
                ->leftJoin('institutions as institutions', 'institutions.id', '=', 'payments.institution_id')
                ->where('payments.user_id', $userId)
                ->whereNotIn('payments.status', ['confirmed', 'failed', 'reversed', 'cancelled'])
                ->select('payments.*', 'products.insurer_name', 'institutions.name as provider_name',
                    'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
                ->get() as $payment) {
                $items[] = $this->item('protection_premium_pending', 'Protection premium or reversal still requires finality',
                    $payment->payment_reference, $payment->status, (int) $payment->amount_minor,
                    $payment->currency, $payment->coverage_period_end,
                    $this->contact($payment->provider_name ?? $payment->insurer_name ?? 'Insurance partner',
                        $payment->provider_phone, $payment->provider_email, $payment->provider_address));
            }
        }

        if (Schema::hasTable('protection_claims')) {
            foreach (DB::table('protection_claims')
                ->where('user_id', $userId)
                ->whereNotIn('status', ['paid', 'declined', 'rejected', 'closed', 'withdrawn', 'cancelled'])
                ->get() as $claim) {
                $items[] = $this->item(
                    'protection_claim_pending',
                    'Protection claim still open',
                    $claim->claim_reference,
                    $claim->status,
                    isset($claim->claimed_amount_minor) ? (int) $claim->claimed_amount_minor : null,
                    'UGX',
                    null,
                    $this->contact('Insurance partner'),
                );
            }
        }

        return $items;
    }

    private function investments(int $userId): array
    {
        $items = [];

        if (Schema::hasTable('investment_orders')) {
            $query = DB::table('investment_orders as orders')
                ->where('orders.user_id', $userId)
                ->whereNotIn('orders.status', ['cancelled', 'rejected', 'failed', 'redeemed', 'closed']);

            if (Schema::hasTable('investment_products')) {
                $query
                    ->leftJoin(
                        'investment_products as products',
                        'products.id',
                        '=',
                        'orders.investment_product_id',
                    )
                    ->select('orders.*', 'products.provider_name');
            } else {
                $query->select('orders.*');
            }

            foreach ($query->get() as $order) {
                $items[] = $this->item(
                    'investment_position',
                    'Investment order or position',
                    $order->provider_reference ?? 'investment-order-'.$order->id,
                    $order->status,
                    (int) $order->amount_minor,
                    $order->currency ?? 'UGX',
                    null,
                    $this->contact($order->provider_name ?? 'Investment provider'),
                );
            }
        }

        if (Schema::hasTable('capital_mandates')) {
            foreach (DB::table('capital_mandates')
                ->where('owner_user_id', $userId)
                ->whereNotIn('status', ['draft', 'cancelled', 'closed', 'rejected'])
                ->where(function ($query) {
                    $query
                        ->where('committed_capital_minor', '>', 0)
                        ->orWhere('deployed_capital_minor', '>', 0);
                })
                ->get() as $mandate) {
                $items[] = $this->item(
                    'capital_mandate',
                    'Active capital mandate',
                    $mandate->reference,
                    $mandate->status,
                    max(
                        (int) $mandate->committed_capital_minor,
                        (int) $mandate->deployed_capital_minor,
                    ),
                    'UGX',
                    null,
                    $this->contact($mandate->name),
                );
            }
        }

        return $items;
    }

    private function payroll(int $userId): array
    {
        if (! Schema::hasTable('payroll_deduction_cases')) {
            return [];
        }

        return DB::table('payroll_deduction_cases')
            ->where('user_id', $userId)
            ->whereIn('status', [
                'reservation_pending',
                'reserved',
                'vote_approval_pending',
                'deduction_approved',
                'payroll_submitted',
                'reconciliation_pending',
                'amendment_required',
                'reconciliation_exception',
                'cancellation_pending',
            ])
            ->get()
            ->map(fn ($case) => $this->item(
                'payroll_deduction',
                'Payroll deduction arrangement',
                $case->reference,
                $case->status,
                isset($case->requested_deduction_minor)
                    ? (int) $case->requested_deduction_minor
                    : null,
                $case->currency ?? 'UGX',
                null,
                $this->contact($case->vote_name ?: 'Payroll deduction provider'),
            ))
            ->all();
    }

    private function pendingFinancialActions(int $userId): array
    {
        if (! Schema::hasTable('financial_action_intents')) {
            return [];
        }

        return DB::table('financial_action_intents')
            ->where('user_id', $userId)
            ->whereNotIn('status', [
                'settled',
                'completed',
                'failed',
                'rejected',
                'cancelled',
                'expired',
                'reversed',
            ])
            ->get()
            ->map(fn ($intent) => $this->item(
                'financial_action_pending',
                'Financial action still processing',
                $intent->reference,
                $intent->status,
                isset($intent->amount_minor) ? (int) $intent->amount_minor : null,
                $intent->currency ?? 'UGX',
                null,
                $this->contact('OpFin processing route'),
            ))
            ->all();
    }

    private function pendingMoneyMovements(int $userId): array
    {
        if (! Schema::hasTable('mobile_money_transactions')) {
            return [];
        }

        return DB::table('mobile_money_transactions')
            ->where('user_id', $userId)
            ->whereNotIn('status', [
                'successful',
                'SUCCESSFUL',
                'failed',
                'FAILED',
                'reversed',
                'REVERSED',
                'cancelled',
                'CANCELLED',
            ])
            ->get()
            ->map(fn ($movement) => $this->item(
                'money_movement_pending',
                'Payment or disbursement still processing',
                $movement->internal_reference
                    ?? $movement->idempotency_key
                    ?? 'money-movement-'.$movement->id,
                $movement->status,
                isset($movement->amount_minor) ? (int) $movement->amount_minor : null,
                $movement->currency ?? 'UGX',
                null,
                $this->contact(ucfirst((string) ($movement->provider ?? 'payment provider'))),
            ))
            ->all();
    }

    private function item(
        string $code,
        string $label,
        mixed $reference,
        mixed $status,
        ?int $amount,
        string $currency,
        mixed $dueDate,
        array $provider,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'reference' => (string) $reference,
            'status' => (string) $status,
            'amount_minor' => $amount,
            'currency' => strtoupper($currency ?: 'UGX'),
            'due_date' => $dueDate ? (string) $dueDate : null,
            'provider' => $provider,
            'next_step' => 'Resolve this obligation with the recorded provider or counterparty before retrying account deletion.',
        ];
    }

    private function contact(
        ?string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
    ): array {
        return [
            'name' => $name ?: 'Provider',
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'address' => $address ?: null,
            'direct_contact_available' => (bool) ($phone || $email || $address),
        ];
    }

    private function namedInstitution(?string $name): array
    {
        if ($name && Schema::hasTable('institutions')) {
            $institution = DB::table('institutions')
                ->where('name', $name)
                ->first();

            if ($institution) {
                return $this->contact(
                    $institution->name ?? $name,
                    $institution->phone ?? null,
                    $institution->email ?? null,
                    $institution->address ?? null,
                );
            }
        }

        return $this->contact($name ?: 'Financial provider');
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
