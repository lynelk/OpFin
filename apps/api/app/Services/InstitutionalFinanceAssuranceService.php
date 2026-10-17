<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bank/provider custody balances and the third-party loan book are separate ledgers.
 * Never use an attested source reference as proof that an external statement is genuine.
 */
final class InstitutionalFinanceAssuranceService
{
    public const CAPACITIES = ['treasury', 'finance', 'risk', 'independent_model', 'legal'];

    public function calculate(int $institutionId, string $date, string $currency, array $providerStatements, int $eclRunId): array
    {
        if ($date > now()->toDateString() || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('A real calendar cut-off and three-letter currency are required.');
        }
        $run = DB::table('institution_ecl_runs')->where('id', $eclRunId)->first();
        if (! $run || (int) $run->institution_id !== $institutionId
            || $run->as_of_date !== $date || $run->currency !== $currency
            || ! in_array($run->status, ['finalised', 'posted'], true)) {
            throw new InvalidArgumentException('Reconciliation must reference the signed ECL and MIS cut-off for this institution and currency.');
        }
        if (count($providerStatements) < 1 || count($providerStatements) > 10) {
            throw new InvalidArgumentException('Provide all 1 to 10 active payout-provider bank statements.');
        }
        $seen = [];
        $input = [];
        foreach ($providerStatements as $statement) {
            if (! is_array($statement)) {
                throw new InvalidArgumentException('Every provider statement must be a structured record.');
            }
            $provider = strtolower((string) ($statement['provider'] ?? ''));
            $balance = $statement['balance_minor'] ?? null;
            $sha = strtolower((string) ($statement['statement_sha256'] ?? ''));
            $reference = trim((string) ($statement['statement_reference'] ?? ''));
            if (! preg_match('/^[a-z0-9_-]{2,50}$/D', $provider)
                || ! is_int($balance) || $balance < 0
                || ! preg_match('/^[a-f0-9]{64}$/D', $sha)
                || strlen($reference) < 12 || strlen($reference) > 255 || isset($seen[$provider])) {
                throw new InvalidArgumentException('Unique provider, nonnegative minor-unit balance, statement file digest and independent statement reference are required.');
            }
            $seen[$provider] = true;
            $input[$provider] = [
                'provider' => $provider, 'balance_minor' => $balance,
                'statement_sha256' => $sha, 'statement_reference' => $reference,
            ];
        }
        ksort($input);
        $prefix = 'cash.partner_custody.institution_'.$institutionId.'.provider_';
        $accounts = DB::table('ledger_accounts')->where('currency', $currency)
            ->where('type', 'asset')->get()
            ->filter(fn ($account) => str_starts_with((string) $account->code, $prefix));
        $providerBalances = [];
        foreach ($accounts as $account) {
            $provider = substr($account->code, strlen($prefix));
            $providerBalances[$provider] = $this->balance((int) $account->id, $date, 'debit');
        }
        foreach ($input as $provider => $statement) {
            if (! array_key_exists($provider, $providerBalances)) {
                $providerBalances[$provider] = 0;
            }
        }
        $allProviders = array_unique(array_merge(array_keys($input), array_keys($providerBalances)));
        sort($allProviders);
        $byProvider = [];
        foreach ($allProviders as $provider) {
            $external = $input[$provider]['balance_minor'] ?? null;
            $internal = (int) ($providerBalances[$provider] ?? 0);
            $byProvider[$provider] = [
                'external_minor' => $external,
                'ledger_minor' => $internal,
                'variance_minor' => $external === null ? null : $external - $internal,
                'matched' => $external !== null && $external === $internal,
            ];
        }
        $held = DB::table('ledger_accounts')->where('currency', $currency)
            ->where('code', 'liability.partner_funds_held.institution_'.$institutionId)->first();
        $liability = $held ? $this->balance((int) $held->id, $date, 'credit') : 0;
        $cashTotal = array_sum(array_column($input, 'balance_minor'));
        return [
            'run_id' => $eclRunId, 'run_hash' => $run->content_hash,
            'provider_control' => $byProvider,
            'provider_total_minor' => $cashTotal,
            'ledger_liability_minor' => $liability,
            'lender_book_minor' => (int) $run->expected_external_principal_minor,
            'lender_book_count' => (int) $run->expected_external_count,
            'all_provider_balances_matched' => array_reduce($byProvider, fn ($ok, $p) => $ok && $p['matched'], true),
        ];
    }

    private function balance(int $accountId, string $date, string $positive): int
    {
        $row = DB::table('ledger_entries as entry')
            ->join('ledger_transactions as tx', 'tx.id', '=', 'entry.ledger_transaction_id')
            ->where('entry.ledger_account_id', $accountId)
            ->where('tx.posted_at', '<=', $date.' 23:59:59')
            ->selectRaw('COALESCE(SUM(CASE WHEN entry.direction = ? THEN entry.amount_minor ELSE -entry.amount_minor END),0) AS amount', [$positive])
            ->first();
        return (int) ($row->amount ?? 0);
    }

    public function stage(array $input, User $maker): int
    {
        $institution = (int) $input['institution_id'];
        $date = $input['as_of_date'];
        $currency = $input['currency'];
        $statements = $input['provider_statements'];
        $control = $this->calculate($institution, $date, $currency, $statements, (int) $input['ecl_run_id']);
        $payable = (int) $input['external_payable_minor'];
        $principal = (int) $input['external_principal_minor'];
        $count = (int) $input['external_loan_count'];
        if ($payable < 0 || $principal < 0 || $count < 0
            || ! preg_match('/^[a-fA-F0-9]{64}$/D', $input['lender_ledger_sha256'])
            || strlen(trim($input['lender_ledger_reference'])) < 12) {
            throw new InvalidArgumentException('Signed lender-book and bank source evidence is required, not a typed control total alone.');
        }
        $control['external_payable_minor'] = $payable;
        $control['external_principal_minor'] = $principal;
        $control['external_loan_count'] = $count;
        $control['bank_vs_payable_variance_minor'] = $control['provider_total_minor'] - $payable;
        $control['lender_vs_mis_variance_minor'] = $principal - $control['lender_book_minor'];
        $control['lender_count_variance'] = $count - $control['lender_book_count'];
        $control['payable_vs_gl_variance_minor'] = $payable - $control['ledger_liability_minor'];
        $control['balanced'] = $control['all_provider_balances_matched']
            && $control['bank_vs_payable_variance_minor'] === 0
            && $control['payable_vs_gl_variance_minor'] === 0
            && $control['lender_vs_mis_variance_minor'] === 0
            && $control['lender_count_variance'] === 0;
        $statements = array_values(collect($statements)->sortBy('provider')->all());
        $source = [
            'institution_id' => $institution, 'ecl_run_id' => $input['ecl_run_id'],
            'as_of_date' => $date, 'currency' => $currency,
            'provider_statements' => json_encode($statements, JSON_THROW_ON_ERROR),
            'external_payable_minor' => $payable,
            'external_principal_minor' => $principal,
            'external_loan_count' => $count,
            'lender_ledger_reference' => trim($input['lender_ledger_reference']),
            'lender_ledger_sha256' => strtolower($input['lender_ledger_sha256']),
            'control_results' => json_encode($control, JSON_THROW_ON_ERROR),
            'created_by' => $maker->id,
        ];
        return DB::table('institution_finance_reconciliations')->insertGetId($source + [
            'content_hash' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)),
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertCutStillMatched(object $row): void
    {
        $prior = json_decode($row->control_results, true, 512, JSON_THROW_ON_ERROR);
        $now = $this->calculate((int) $row->institution_id, $row->as_of_date, $row->currency,
            json_decode($row->provider_statements, true, 512, JSON_THROW_ON_ERROR), (int) $row->ecl_run_id);
        if (! $prior['balanced'] || $now['run_hash'] !== $prior['run_hash']
            || $now['provider_control'] !== $prior['provider_control']
            || $now['ledger_liability_minor'] !== $prior['ledger_liability_minor']) {
            throw new InvalidArgumentException('Bank, lender and GL variances or cut-off changes prevent independent assurance.');
        }
    }

    public function approve(int $id, User $checker): void
    {
        DB::transaction(function () use ($id, $checker) {
            $row = DB::table('institution_finance_reconciliations')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'draft' || (int) $row->created_by === (int) $checker->id) {
                throw new InvalidArgumentException('An independent checker must review the original external source reconciliation.');
            }
            $this->assertCutStillMatched($row);
            DB::table('institution_finance_reconciliations')->where('id', $id)->update([
                'status' => 'reconciled_pending_signoff',
                'approved_by' => $checker->id, 'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function sign(int $reconciliationId, string $capacity, string $reportReference,
        string $reportSha256, string $statement, User $signer): void
    {
        DB::transaction(function () use ($reconciliationId, $capacity, $reportReference, $reportSha256, $statement, $signer) {
            $row = DB::table('institution_finance_reconciliations')->where('id', $reconciliationId)->lockForUpdate()->first();
            if (! $row || $row->status !== 'reconciled_pending_signoff'
                || ! in_array($capacity, self::CAPACITIES, true)
                || (int) $row->created_by === (int) $signer->id
                || (int) $row->approved_by === (int) $signer->id
                || ! preg_match('/^[a-fA-F0-9]{64}$/D', $reportSha256)
                || strlen(trim($reportReference)) < 12 || strlen(trim($statement)) < 30
                || DB::table('institution_finance_reviews')->where('reconciliation_id', $reconciliationId)
                    ->where('reviewed_by', $signer->id)->exists()) {
                throw new InvalidArgumentException('A distinct qualified person must individually review the source dossier and sign their own evidence statement.');
            }
            $this->assertCutStillMatched($row);
            DB::table('institution_finance_reviews')->insert([
                'reconciliation_id' => $reconciliationId, 'capacity' => $capacity,
                'reviewed_by' => $signer->id, 'report_sha256' => strtolower($reportSha256),
                'report_reference' => trim($reportReference), 'signed_statement' => trim($statement),
                'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function reviewSharedClassification(int $classificationId, string $capacity, string $reference,
        string $hash, string $conclusion, User $reviewer): void
    {
        DB::transaction(function () use ($classificationId, $capacity, $reference, $hash, $conclusion, $reviewer) {
            $classification = DB::table('institution_shared_classifications')
                ->where('id', $classificationId)->lockForUpdate()->first();
            if (! $classification || ! in_array($classification->status,
                ['proposed_pending_legal_review', 'classified_pending_ledger_certification'], true)
                || ! in_array($capacity, self::CAPACITIES, true)
                || (int) $classification->created_by === (int) $reviewer->id
                || strlen(trim($reference)) < 12 || strlen(trim($conclusion)) < 50
                || ! preg_match('/^[a-fA-F0-9]{64}$/D', $hash)) {
                throw new InvalidArgumentException('Separate legal/financial reviewers must attest the specific contract and its classification.');
            }
            $cut = DB::table('institution_finance_reconciliations')->find($classification->reconciliation_id);
            if (! $cut || $cut->status !== 'reconciled_pending_signoff') {
                throw new InvalidArgumentException('Contract classification cannot outlive a rejected lender reconciliation.');
            }
            $this->assertCutStillMatched($cut);
            $cutReviews = DB::table('institution_finance_reviews')
                ->where('reconciliation_id', $cut->id)->pluck('capacity')->all();
            if (count(array_intersect(self::CAPACITIES, $cutReviews)) !== count(self::CAPACITIES)) {
                throw new InvalidArgumentException('Every qualified assurance capacity must first sign the reconciled institution evidence.');
            }
            DB::table('institution_shared_classification_reviews')->insert([
                'classification_id' => $classificationId, 'capacity' => $capacity,
                'reviewed_by' => $reviewer->id, 'review_reference' => trim($reference),
                'review_document_sha256' => strtolower($hash),
                'technical_conclusion' => trim($conclusion),
                'reviewed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $count = DB::table('institution_shared_classification_reviews')
                ->where('classification_id', $classificationId)->distinct()->count('capacity');
            if ($count === count(self::CAPACITIES)) {
                DB::table('institution_shared_classifications')->where('id', $classificationId)
                    ->update(['status' => 'classified_pending_ledger_certification', 'updated_at' => now()]);
            }
            // Never activate origination from a documentary sign-off. Direct joint creditors
            // need correct economic splitting in principal, accrued interest, ECL, fees,
            // cash custody, reverse postings and write-offs before the first live loan.
        });
    }

    public function proposeSharedClassification(CreditOffer $offer, int $reconciliationId, string $form,
        int $share, string $contractRef, string $contractHash, string $policyRef, string $memoHash, User $maker): int
    {
        if (! in_array($form, ['direct_joint_creditor', 'funded_participation', 'financial_guarantee', 'first_loss'], true)
            || $share <= 0 || $share >= 10000 || trim($contractRef) === '' || trim($policyRef) === ''
            || ! preg_match('/^[a-f0-9]{64}$/i', $contractHash)
            || ! preg_match('/^[a-f0-9]{64}$/i', $memoHash)) {
            throw new InvalidArgumentException('Shared arrangements need explicit legal form, allocation and independent accounting memo evidence.');
        }
        $reconciliation = DB::table('institution_finance_reconciliations')->find($reconciliationId);
        $ownership = DB::table('credit_offer_ecl_ownerships')->where('credit_offer_id', $offer->id)->first();
        if (! $reconciliation || $reconciliation->status !== 'reconciled_pending_signoff'
            || (int) $reconciliation->institution_id !== (int) $offer->institution_id
            || $reconciliation->currency !== $offer->currency
            || ! $ownership || $ownership->status !== 'approved'
            || $ownership->owner_type !== 'shared' || (int) $ownership->opfin_share_bps !== $share) {
            throw new InvalidArgumentException('The offered risk share and signed lender reconciliation must match.');
        }
        if ($offer->status !== CreditOffer::STATUS_OFFERED) {
            throw new InvalidArgumentException('Shared-contract classification must precede customer acceptance, not reclassify a live loan.');
        }
        // A financial guarantee or first-loss contract is NOT a percentage-owned
        // loan receivable. No variant automatically activates shared origination.
        return DB::table('institution_shared_classifications')->insertGetId([
            'credit_offer_id' => $offer->id, 'institution_id' => $offer->institution_id,
            'reconciliation_id' => $reconciliationId, 'contract_form' => $form,
            'opfin_share_bps' => $share, 'contract_reference' => trim($contractRef),
            'signed_contract_sha256' => strtolower($contractHash),
            'accounting_policy_reference' => trim($policyRef),
            'technical_memo_sha256' => strtolower($memoHash),
            'status' => 'proposed_pending_legal_review', 'created_by' => $maker->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
