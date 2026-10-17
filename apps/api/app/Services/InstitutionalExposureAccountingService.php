<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Credit-risk ownership is contract evidence, not a funding-pool inference.
 * Partner cash is a fiduciary liability and may only be spent if verified
 * funding has ALREADY been posted to the appropriate subledger.
 */
final class InstitutionalExposureAccountingService
{
    public function stage(CreditOffer $offer, string $owner, int $shareBps, string $agreement, User $maker): int
    {
        $fresh = CreditOffer::query()->findOrFail($offer->id);
        if ($fresh->status !== CreditOffer::STATUS_OFFERED) {
            throw new InvalidArgumentException('Exposure owner must be attested before an offer is accepted.');
        }
        if (! in_array($owner, ['opfin', 'partner', 'shared'], true)
            || ($owner === 'opfin' && $shareBps !== 10000)
            || ($owner === 'partner' && $shareBps !== 0)
            || ($owner === 'shared' && ($shareBps <= 0 || $shareBps >= 10000))
            || trim($agreement) === '') {
            throw new InvalidArgumentException('Credit-risk owner, risk participation and executed agreement are inconsistent.');
        }
        if (DB::table('credit_offer_ecl_ownerships')->where('credit_offer_id', $fresh->id)->exists()) {
            throw new InvalidArgumentException('Offer ownership evidence already exists; changes require controlled cancellation and re-offer.');
        }
        return DB::table('credit_offer_ecl_ownerships')->insertGetId([
            'credit_offer_id' => $fresh->id, 'institution_id' => $fresh->institution_id,
            'owner_type' => $owner, 'opfin_share_bps' => $shareBps,
            'agreement_reference' => trim($agreement), 'status' => 'draft',
            'created_by' => $maker->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function approve(int $id, User $checker): void
    {
        DB::transaction(function () use ($id, $checker) {
            $row = DB::table('credit_offer_ecl_ownerships')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'draft' || (int) $row->created_by === (int) $checker->id) {
                throw new InvalidArgumentException('Offer ownership requires an independent finance checker.');
            }
            $offer = CreditOffer::query()->lockForUpdate()->findOrFail($row->credit_offer_id);
            if ($offer->status !== CreditOffer::STATUS_OFFERED) {
                throw new InvalidArgumentException('Offer ownership cannot be approved after acceptance.');
            }
            DB::table('credit_offer_ecl_ownerships')->where('id', $id)->update([
                'status' => 'approved', 'approved_by' => $checker->id, 'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function governed(CreditOffer $offer): bool
    {
        if (! Schema::hasTable('credit_offer_ecl_ownerships')) {
            return false;
        }
        return (bool) config('opfin.accounting.enforce_credit_owner_at_origination', false)
            || DB::table('credit_offer_ecl_ownerships')->where('credit_offer_id', $offer->id)->exists()
            || DB::table('institution_ecl_policies')->where('institution_id', $offer->institution_id)
                ->where('status', 'approved')->where('currency', strtoupper((string) $offer->currency))
                ->where('effective_from', '<=', now()->toDateString())->exists();
    }

    public function approved(CreditOffer $offer): object
    {
        if (! Schema::hasTable('credit_offer_ecl_ownerships')) {
            throw new InvalidArgumentException('Institutional ownership migration is required before production lending.');
        }
        $row = DB::table('credit_offer_ecl_ownerships')->where('credit_offer_id', $offer->id)->first();
        if (! $row || $row->status !== 'approved' || ! $row->approved_at
            || (int) $row->institution_id !== (int) $offer->institution_id) {
            throw new InvalidArgumentException('A signed, independently verified legal credit-risk owner is required before payout.');
        }
        if ($row->owner_type === 'shared') {
            throw new InvalidArgumentException('Shared exposure origination is blocked until asset, guarantee and funder-claim accounting is certified.');
        }
        return $row;
    }

    public function reserveBeforePayout(CreditOffer $offer): void
    {
        if (! $this->governed($offer)) {
            return;
        }
        $row = $this->approved($offer);
        if ($row->owner_type !== 'partner') {
            return;
        }
        DB::transaction(function () use ($offer) {
            $currency = strtoupper((string) $offer->currency);
            $account = $this->partnerFundsAccount((int) $offer->institution_id, $currency);
            // Serialise across offers sharing this institution/currency; PostgreSQL row lock
            // enforces the same held-cash constraint across concurrent customer acceptance.
            LedgerAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $reservation = DB::table('partner_ecl_cash_reservations')->where('credit_offer_id', $offer->id)->first();
            if ($reservation) {
                if ($reservation->status !== 'reserved') {
                    throw new InvalidArgumentException('This offer has a completed/released partner cash reservation.');
                }
                return;
            }
            $entries = DB::table('ledger_entries')->where('ledger_account_id', $account->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END),0) AS funds_minor")
                ->first();
            $available = (int) $entries->funds_minor
                - (int) DB::table('partner_ecl_cash_reservations')
                    ->where('institution_id', $offer->institution_id)->where('currency', $currency)
                    ->where('status', 'reserved')->sum('cash_minor');
            $cash = (int) $offer->net_disbursement_minor;
            if ($cash <= 0 || $available < $cash) {
                throw new InvalidArgumentException('Verified partner funds held are insufficient. Capital mandates alone are not bank cash receipts.');
            }
            DB::table('partner_ecl_cash_reservations')->insert([
                'credit_offer_id' => $offer->id, 'institution_id' => $offer->institution_id,
                'currency' => $currency, 'cash_minor' => $cash, 'status' => 'reserved',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function settleReservation(CreditOffer $offer): void
    {
        if (! $this->governed($offer)) {
            return;
        }
        $row = $this->approved($offer);
        if ($row->owner_type === 'partner') {
            $updated = DB::table('partner_ecl_cash_reservations')
                ->where('credit_offer_id', $offer->id)->where('status', 'reserved')
                ->where('cash_minor', $offer->net_disbursement_minor)
                ->update(['status' => 'consumed', 'updated_at' => now()]);
            if ($updated !== 1) {
                throw new InvalidArgumentException('Partner cash reservation was not recorded for the completed payout.');
            }
        }
    }

    public function releaseReservation(CreditOffer $offer): void
    {
        DB::table('partner_ecl_cash_reservations')
            ->where('credit_offer_id', $offer->id)->where('status', 'reserved')
            ->update(['status' => 'released', 'updated_at' => now()]);
    }

    public function attachLoan(CreditOffer $offer, Loan $loan): void
    {
        if (! $this->governed($offer)) {
            return;
        }
        $row = $this->approved($offer);
        $existing = DB::table('loan_ecl_ownerships')->where('loan_id', $loan->id)->first();
        if ($existing) {
            if ($existing->status !== 'approved' || $existing->owner_type !== $row->owner_type
                || (int) $existing->opfin_share_bps !== (int) $row->opfin_share_bps
                || (int) $existing->institution_id !== (int) $row->institution_id) {
                throw new InvalidArgumentException('Loan ownership conflicts with the immutable accepted offer.');
            }
            return;
        }
        DB::table('loan_ecl_ownerships')->insert([
            'loan_id' => $loan->id, 'institution_id' => $row->institution_id,
            'owner_type' => $row->owner_type, 'opfin_share_bps' => $row->opfin_share_bps,
            'agreement_reference' => $row->agreement_reference, 'status' => 'approved',
            'created_by' => $row->created_by, 'approved_by' => $row->approved_by,
            'approved_at' => $row->approved_at, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function ownerForLoan(Loan $loan): ?object
    {
        if (! Schema::hasTable('loan_ecl_ownerships')) {
            return null;
        }
        return DB::table('loan_ecl_ownerships')->where('loan_id', $loan->id)->first();
    }

    public function partnerFundsAccount(int $institutionId, string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => 'liability.partner_funds_held.institution_'.$institutionId, 'currency' => $currency],
            ['name' => 'Partner funds held institution '.$institutionId, 'type' => 'liability', 'is_active' => true]
        );
    }
}
