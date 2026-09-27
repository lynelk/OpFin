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
            ...$this->personalObligations($user->id),
            ...$this->essentials($user->id),
            ...$this->peerFinance($user->id),
            ...$this->savings($user->id),
            ...$this->protection($user->id),
            ...$this->investments($user->id),
            ...$this->payroll($user->id),
        ])->unique(fn (array $row) => ($row['code'] ?? '').'|'.($row['reference'] ?? ''))->values()->all();
    }

    private function loans(int $userId): array
    {
        if (! Schema::hasTable('loans')) return [];
        $q = DB::table('loans as l')->where('l.user_id', $userId)
            ->whereNotIn('l.status', ['Cleared', 'Cancelled', 'Rejected']);
        if (Schema::hasTable('institutions')) {
            $q->leftJoin('institutions as i', 'i.id', '=', 'l.institution_id')
                ->select('l.*', 'i.name as provider_name', 'i.phone as provider_phone',
                    'i.email as provider_email', 'i.address as provider_address');
        } else $q->select('l.*');

        return $q->get()->map(function ($row) {
            $outstanding = null;
            if (Schema::hasTable('loan_schedules') && Schema::hasColumn('loan_schedules', 'loan_id')) {
                foreach (['total_outstanding', 'total_outstanding_minor'] as $column) {
                    if (Schema::hasColumn('loan_schedules', $column)) {
                        $outstanding = (int) DB::table('loan_schedules')->where('loan_id', $row->id)->sum($column);
                        break;
                    }
                }
            }
            return $this->item('active_credit', 'Active credit', $row->reference ?? 'loan-'.$row->id,
                $row->status ?? 'active', $outstanding, $row->currency ?? 'UGX',
                $row->repayment_start_date ?? null,
                $this->contact($row->provider_name ?? 'Credit provider', $row->provider_phone ?? null,
                    $row->provider_email ?? null, $row->provider_address ?? null));
        })->all();
    }

    private function personalObligations(int $userId): array
    {
        if (! Schema::hasTable('financial_obligations') || ! Schema::hasTable('financial_spaces')
            || ! Schema::hasTable('financial_space_memberships')) return [];

        return DB::table('financial_obligations as o')
            ->join('financial_spaces as s', 's.id', '=', 'o.financial_space_id')
            ->join('financial_space_memberships as m', 'm.financial_space_id', '=', 's.id')
            ->where('m.user_id', $userId)->where('s.type', 'personal')
            ->where('o.direction', 'i_owe')->where('o.status', 'open')
            ->where('o.outstanding_amount_minor', '>', 0)->whereNull('o.deleted_at')
            ->select('o.*')->get()->map(function ($row) {
                $meta = $this->json($row->metadata ?? null);
                return $this->item('personal_obligation', 'Outstanding recorded obligation',
                    'personal-obligation-'.$row->id, $row->status, (int) $row->outstanding_amount_minor,
                    $row->currency, $row->due_date ?? null,
                    $this->contact($row->counterparty_name ?: 'Recorded counterparty',
                        $meta['phone'] ?? $meta['contact_phone'] ?? null,
                        $meta['email'] ?? $meta['contact_email'] ?? null, $meta['address'] ?? null));
            })->all();
    }

    private function essentials(int $userId): array
    {
        if (! Schema::hasTable('essentials_advances')) return [];
        $q = DB::table('essentials_advances as a')->where('a.user_id', $userId)
            ->where(function ($b) {
                $b->whereNotIn('a.status', ['settled', 'fulfilment_failed', 'lender_funding_failed'])
                    ->orWhere('a.outstanding_minor', '>', 0)->orWhere('a.principal_outstanding_minor', '>', 0);
            });
        if (Schema::hasTable('partners')) {
            $q->leftJoin('partners as p', 'p.id', '=', 'a.lender_partner_id');
            if (Schema::hasTable('institutions')) {
                $q->leftJoin('institutions as i', 'i.id', '=', 'p.institution_id');
            }
        }
        $select = ['a.*'];
        if (Schema::hasTable('partners')) { $select[]='p.name as partner_name'; $select[]='p.metadata as partner_metadata'; }
        if (Schema::hasTable('partners') && Schema::hasTable('institutions')) {
            array_push($select, 'i.name as provider_name', 'i.phone as provider_phone',
                'i.email as provider_email', 'i.address as provider_address');
        }
        return $q->select($select)->get()->map(function ($row) {
            $meta = $this->json($row->partner_metadata ?? null);
            return $this->item('essentials_financing', 'Essentials financing', $row->reference, $row->status,
                (int) ($row->outstanding_minor ?? $row->principal_outstanding_minor ?? 0),
                $row->currency ?? 'UGX', $row->next_due_date ?? null,
                $this->contact($row->provider_name ?? $row->partner_name ?? 'Financing partner',
                    $row->provider_phone ?? $meta['support_phone'] ?? $meta['phone'] ?? null,
                    $row->provider_email ?? $meta['support_email'] ?? $meta['email'] ?? null,
                    $row->provider_address ?? $meta['address'] ?? null));
        })->all();
    }

    private function peerFinance(int $userId): array
    {
        $out = [];
        if (Schema::hasTable('participatory_finance_listings')) {
            foreach (DB::table('participatory_finance_listings')->where('borrower_user_id', $userId)
                ->whereIn('status', ['awaiting_compliance_review','approved','funding','funded'])->get() as $row) {
                $out[] = $this->item('peer_borrowing', 'Peer-finance borrowing', $row->reference, $row->status,
                    max(0, (int) $row->target_amount_minor - (int) ($row->funded_amount_minor ?? 0)), 'UGX',
                    null, $this->namedInstitution($row->lender_of_record ?? null));
            }
        }
        if (Schema::hasTable('participatory_finance_commitments')) {
            $q = DB::table('participatory_finance_commitments as c')
                ->leftJoin('participatory_finance_listings as l', 'l.id', '=', 'c.listing_id')
                ->where('c.investor_user_id', $userId)
                ->whereIn('c.status', ['awaiting_step_up','provider_processing','settled'])
                ->select('c.*','l.lender_of_record');
            foreach ($q->get() as $row) {
                $out[] = $this->item('peer_lending', 'Peer-finance investment commitment', $row->reference,
                    $row->status, (int) $row->amount_minor, 'UGX', null,
                    $this->namedInstitution($row->lender_of_record ?? null));
            }
        }
        return $out;
    }

    private function savings(int $userId): array
    {
        if (! Schema::hasTable('savings_goals')) return [];
        $q = DB::table('savings_goals as g')->where('g.user_id', $userId);
        if (Schema::hasColumn('savings_goals','confirmed_balance_minor')) $q->where('g.confirmed_balance_minor','>',0);
        else $q->whereIn('g.status', ['active','withdrawal_pending']);
        if (Schema::hasTable('savings_products')) $q->leftJoin('savings_products as p','p.id','=','g.savings_product_id');
        if (Schema::hasTable('institutions') && Schema::hasColumn('savings_goals','institution_id'))
            $q->leftJoin('institutions as i','i.id','=','g.institution_id');

        $select=['g.*'];
        if (Schema::hasTable('savings_products')) $select[]='p.partner_name';
        if (Schema::hasTable('institutions') && Schema::hasColumn('savings_goals','institution_id'))
            array_push($select,'i.name as provider_name','i.phone as provider_phone','i.email as provider_email','i.address as provider_address');

        return $q->select($select)->get()->map(fn ($row) => $this->item('savings_position','Savings position',
            $row->goal_reference ?? 'savings-'.$row->id, $row->status ?? 'active',
            Schema::hasColumn('savings_goals','confirmed_balance_minor') ? (int) ($row->confirmed_balance_minor ?? 0) : null,
            'UGX', $row->target_date ?? null,
            $this->contact($row->provider_name ?? $row->partner_name ?? 'Savings partner',
                $row->provider_phone ?? null, $row->provider_email ?? null, $row->provider_address ?? null)))->all();
    }

    private function protection(int $userId): array
    {
        if (! Schema::hasTable('protection_policies')) return [];
        $q = DB::table('protection_policies as p')->where('p.user_id',$userId)
            ->whereIn('p.status',['premium_due','active','claim_pending']);
        if (Schema::hasTable('protection_products')) $q->leftJoin('protection_products as x','x.id','=','p.protection_product_id');
        if (Schema::hasTable('institutions')) $q->leftJoin('institutions as i','i.id','=','p.institution_id');
        $select=['p.*'];
        if (Schema::hasTable('protection_products')) array_push($select,'x.insurer_name','x.underwriter_name');
        if (Schema::hasTable('institutions')) array_push($select,'i.name as provider_name','i.phone as provider_phone','i.email as provider_email','i.address as provider_address');

        return $q->select($select)->get()->map(fn ($row) => $this->item('protection_policy','Active protection policy',
            $row->policy_reference, $row->status, (int) $row->premium_amount_minor, 'UGX',
            $row->next_premium_due_date ?? $row->cover_end_date ?? null,
            $this->contact($row->provider_name ?? $row->insurer_name ?? $row->underwriter_name ?? 'Insurance partner',
                $row->provider_phone ?? null, $row->provider_email ?? null, $row->provider_address ?? null)))->all();
    }

    private function investments(int $userId): array
    {
        if (! Schema::hasTable('investment_orders')) return [];
        $q = DB::table('investment_orders as o')->where('o.user_id',$userId)
            ->whereNotIn('o.status',['cancelled','rejected','failed','redeemed','closed']);
        if (Schema::hasTable('investment_products')) $q->leftJoin('investment_products as p','p.id','=','o.investment_product_id')
            ->select('o.*','p.provider_name');
        else $q->select('o.*');
        return $q->get()->map(fn ($row) => $this->item('investment_position','Investment order or position',
            $row->provider_reference ?? 'investment-order-'.$row->id, $row->status, (int) $row->amount_minor,
            $row->currency ?? 'UGX', null, $this->contact($row->provider_name ?? 'Investment provider')))->all();
    }

    private function payroll(int $userId): array
    {
        if (! Schema::hasTable('payroll_deduction_cases')) return [];
        return DB::table('payroll_deduction_cases')->where('user_id',$userId)
            ->whereIn('status',['reservation_pending','reserved','vote_approval_pending','deduction_approved',
                'payroll_submitted','reconciliation_pending','amendment_required','reconciliation_exception'])
            ->get()->map(fn ($row) => $this->item('payroll_deduction','Payroll deduction arrangement',
                $row->reference,$row->status,isset($row->requested_deduction_minor)?(int)$row->requested_deduction_minor:null,
                $row->currency ?? 'UGX',null,$this->contact($row->vote_name ?: 'Payroll deduction provider')))->all();
    }

    private function item(string $code,string $label,mixed $reference,mixed $status,?int $amount,string $currency,
        mixed $due,array $provider): array
    {
        return ['code'=>$code,'label'=>$label,'reference'=>(string)$reference,'status'=>(string)$status,
            'amount_minor'=>$amount,'currency'=>strtoupper($currency ?: 'UGX'),'due_date'=>$due ? (string)$due : null,
            'provider'=>$provider];
    }

    private function contact(?string $name,?string $phone=null,?string $email=null,?string $address=null): array
    {
        return ['name'=>$name ?: 'Provider','phone'=>$phone ?: null,'email'=>$email ?: null,'address'=>$address ?: null,
            'direct_contact_available'=>(bool)($phone || $email || $address)];
    }

    private function namedInstitution(?string $name): array
    {
        if ($name && Schema::hasTable('institutions')) {
            $i = DB::table('institutions')->where('name',$name)->first();
            if ($i) return $this->contact($i->name ?? $name,$i->phone ?? null,$i->email ?? null,$i->address ?? null);
        }
        return $this->contact($name ?: 'Financial provider');
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || trim($value)==='') return [];
        $decoded=json_decode($value,true);
        return is_array($decoded) ? $decoded : [];
    }
}
