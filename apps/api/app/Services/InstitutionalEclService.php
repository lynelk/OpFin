<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InstitutionalEclService
{
    public function __construct(
        private readonly InstitutionalEclCalculator $calculator,
        private readonly LoanImpairmentService $impairment
    ) {}

    public function stagePolicy(int $institutionId, array $input, User $actor): int
    {
        $this->calculator->validateScenarios($input['scenarios']);
        if ($input['stage2_dpd'] >= $input['stage3_dpd']) {
            throw new InvalidArgumentException('Stage 3 DPD must exceed stage 2 DPD.');
        }
        return DB::table('institution_ecl_policies')->insertGetId([
            'institution_id' => $institutionId,
            'version' => $input['version'],
            'effective_from' => $input['effective_from'],
            'currency' => $input['currency'],
            'stage2_dpd' => $input['stage2_dpd'],
            'stage3_dpd' => $input['stage3_dpd'],
            'discount_bps' => $input['discount_bps'],
            'scenarios' => json_encode($input['scenarios'], JSON_THROW_ON_ERROR),
            'methodology_reference' => $input['methodology_reference'],
            'status' => 'draft',
            'created_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function approvePolicy(int $policyId, User $actor): void
    {
        DB::transaction(function () use ($policyId, $actor) {
            $policy = DB::table('institution_ecl_policies')->where('id', $policyId)->lockForUpdate()->first();
            if (! $policy || $policy->status !== 'draft') {
                throw new InvalidArgumentException('Policy must be a pending draft.');
            }
            if ((int) $policy->created_by === (int) $actor->id) {
                throw new InvalidArgumentException('Policy maker cannot approve their own ECL policy.');
            }
            DB::table('institution_ecl_policies')->where('id', $policyId)->update([
                'status' => 'approved', 'approved_by' => $actor->id,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function registerOwnership(int $institutionId, int $loanId, array $input, User $actor): int
    {
        $loan = Loan::withoutGlobalScopes()->findOrFail($loanId);
        if ((int) $loan->institution_id !== $institutionId) {
            throw new InvalidArgumentException('Loan is not owned by the selected lender record.');
        }
        $share = (int) $input['opfin_share_bps'];
        $kind = $input['owner_type'];
        if (($kind === 'opfin' && $share !== 10000)
            || ($kind === 'partner' && $share !== 0)
            || ($kind === 'shared' && ($share <= 0 || $share >= 10000))) {
            throw new InvalidArgumentException('Exposure ownership and platform risk share do not agree.');
        }
        return DB::table('loan_ecl_ownerships')->insertGetId([
            'loan_id' => $loanId, 'institution_id' => $institutionId,
            'owner_type' => $kind, 'opfin_share_bps' => $share,
            'agreement_reference' => $input['agreement_reference'],
            'status' => 'draft', 'created_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function approveOwnership(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $row = DB::table('loan_ecl_ownerships')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'draft' || (int) $row->created_by === (int) $actor->id) {
                throw new InvalidArgumentException('Ownership requires a different independent approver.');
            }
            DB::table('loan_ecl_ownerships')->where('id', $id)->update([
                'status' => 'approved', 'approved_by' => $actor->id,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }


    /**
     * Bulk portfolio evidence intake is not a disbursement, loan origination or GL event.
     * CSV batches have exact source control totals and mandatory independent review.
     */
    public function stageExternalCsv(
        int $institutionId, string $asOf, string $currency, string $sourceEvidence,
        string $csv, int $expectedCount, int $expectedPrincipal, User $actor
    ): int {
        if ($expectedCount < 1 || $expectedCount > 2000 || $expectedPrincipal < 0
            || strlen($csv) > 2 * 1024 * 1024 || $csv === '') {
            throw new InvalidArgumentException('CSV batch must contain 1–2,000 loans and be at most 2 MiB.');
        }
        $file = fopen('php://temp', 'w+');
        if (! $file) {
            throw new InvalidArgumentException('Unable to read the supplied CSV.');
        }
        fwrite($file, $csv);
        rewind($file);
        $header = fgetcsv($file, 0, ',', '"', '');
        if (is_array($header) && isset($header[0])) {
            $header[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', $header[0]);
        }
        if ($header !== ['external_loan_reference', 'principal_outstanding_minor', 'days_past_due', 'sicr', 'credit_impaired', 'contractual_maturity_date']) {
            fclose($file);
            throw new InvalidArgumentException('CSV header must match the published canonical loan observation template.');
        }
        $rows = [];
        $refs = [];
        $sum = 0;
        try {
            while (($fields = fgetcsv($file, 0, ',', '"', '')) !== false) {
                if ($fields === [null]) {
                    continue;
                }
                if (count($fields) !== 6 || count($rows) >= 2000) {
                    throw new InvalidArgumentException('CSV column count or 2,000-loan limit exceeded.');
                }
                [$reference, $principal, $dpd, $sicr, $impaired, $maturity] = array_map('trim', $fields);
                if ($reference === '' || strlen($reference) > 160
                    || preg_match('/[\\x00-\\x1F\\x7F]/', $reference)
                    || isset($refs[$reference])) {
                    throw new InvalidArgumentException('A loan reference is empty, duplicated or contains invalid control characters.');
                }
                foreach ([$principal, $dpd] as $numeric) {
                    if (! preg_match('/^(0|[1-9][0-9]*)$/D', $numeric) || strlen($numeric) > 13) {
                        throw new InvalidArgumentException('Principal and DPD must be non-negative integer source values.');
                    }
                }
                if ((int) $principal > 1000000000000 || (int) $dpd > 65535
                    || ! in_array($sicr, ['0', '1'], true) || ! in_array($impaired, ['0', '1'], true)
                    || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $maturity)
                    || ! checkdate((int) substr($maturity, 5, 2), (int) substr($maturity, 8, 2), (int) substr($maturity, 0, 4))) {
                    throw new InvalidArgumentException('CSV risk, maturity or monetary input is invalid.');
                }
                $refs[$reference] = true;
                $sum += (int) $principal;
                $rows[] = [
                    'external_loan_reference' => $reference,
                    'as_of_date' => $asOf, 'currency' => $currency,
                    'principal_outstanding_minor' => (int) $principal,
                    'days_past_due' => (int) $dpd, 'sicr' => $sicr === '1',
                    'credit_impaired' => $impaired === '1',
                    'contractual_maturity_date' => $maturity,
                    'source_evidence' => $sourceEvidence,
                ];
            }
        } finally {
            fclose($file);
        }
        if (count($rows) !== $expectedCount || $sum !== $expectedPrincipal) {
            throw new InvalidArgumentException('CSV rows and principal do not reconcile with source control totals.');
        }
        if ($asOf > now()->toDateString()) {
            throw new InvalidArgumentException('An external portfolio source cannot have a future reporting cut-off.');
        }
        return DB::transaction(function () use ($institutionId, $asOf, $currency, $sourceEvidence, $csv, $rows, $sum, $actor) {
            $batchId = DB::table('institution_ecl_import_batches')->insertGetId([
                'institution_id' => $institutionId, 'as_of_date' => $asOf,
                'currency' => $currency, 'source_evidence' => $sourceEvidence,
                'file_hash' => hash('sha256', $csv), 'loan_count' => count($rows),
                'principal_total_minor' => $sum, 'status' => 'staged',
                'submitted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($rows as $row) {
                $this->stageExternal($institutionId, $row, $actor, $batchId);
            }
            return $batchId;
        });
    }

    public function approveExternalBatch(int $batchId, User $actor): int
    {
        return DB::transaction(function () use ($batchId, $actor) {
            $batch = DB::table('institution_ecl_import_batches')->where('id', $batchId)->lockForUpdate()->first();
            if (! $batch || $batch->status !== 'staged' || (int) $batch->submitted_by === (int) $actor->id) {
                throw new InvalidArgumentException('An independent checker must approve a staged source batch.');
            }
            $rows = DB::table('institution_ecl_external_snapshots')
                ->where('import_batch_id', $batchId)->lockForUpdate()->get();
            if ($rows->count() !== (int) $batch->loan_count
                || (int) $rows->sum('principal_outstanding_minor') !== (int) $batch->principal_total_minor
                || $rows->contains(fn ($r) => $r->status !== 'staged')) {
                throw new InvalidArgumentException('Batch evidence differs from its source manifest.');
            }
            DB::table('institution_ecl_external_snapshots')->where('import_batch_id', $batchId)
                ->update(['status' => 'approved', 'approved_by' => $actor->id,
                    'approved_at' => now(), 'updated_at' => now()]);
            DB::table('institution_ecl_import_batches')->where('id', $batchId)
                ->update(['status' => 'approved', 'approved_by' => $actor->id,
                    'approved_at' => now(), 'updated_at' => now()]);
            return $rows->count();
        });
    }

    public function stageExternal(int $institutionId, array $data, User $actor, ?int $batchId = null): int
    {
        if ($data['as_of_date'] > now()->toDateString()) {
            throw new InvalidArgumentException('Future portfolio observations cannot be approved.');
        }
        $source = [
            'institution_id' => $institutionId,
            'import_batch_id' => $batchId,
            'external_loan_reference' => $data['external_loan_reference'],
            'as_of_date' => $data['as_of_date'],
            'currency' => $data['currency'],
            'principal_outstanding_minor' => $data['principal_outstanding_minor'],
            'days_past_due' => $data['days_past_due'],
            'sicr' => (bool) $data['sicr'],
            'credit_impaired' => (bool) $data['credit_impaired'],
            'contractual_maturity_date' => $data['contractual_maturity_date'],
            'source_evidence' => $data['source_evidence'],
        ];
        return DB::table('institution_ecl_external_snapshots')->insertGetId($source + [
            'source_hash' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)),
            'status' => 'staged', 'submitted_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function approveExternal(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $source = DB::table('institution_ecl_external_snapshots')->where('id', $id)->lockForUpdate()->first();
            if (! $source || $source->status !== 'staged' || (int) $source->submitted_by === (int) $actor->id) {
                throw new InvalidArgumentException('An independent reviewer must approve an unchanged external observation.');
            }
            DB::table('institution_ecl_external_snapshots')->where('id', $id)->update([
                'status' => 'approved', 'approved_by' => $actor->id,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function stageSourceCorrection(int $snapshotId, array $data, User $maker): int
    {
        return DB::transaction(function () use ($snapshotId, $data, $maker) {
            $source = DB::table('institution_ecl_external_snapshots')->where('id', $snapshotId)->lockForUpdate()->first();
            if (! $source || $source->status !== 'approved') {
                throw new InvalidArgumentException('Corrections require a previously approved immutable source observation.');
            }
            if (DB::table('institution_ecl_source_corrections')->where('external_snapshot_id', $snapshotId)
                ->where('status', 'draft')->exists()) {
                throw new InvalidArgumentException('Review the existing pending source correction first.');
            }
            $revision = 1 + (int) DB::table('institution_ecl_source_corrections')
                ->where('external_snapshot_id', $snapshotId)->max('revision');
            $change = [
                'external_snapshot_id' => $snapshotId, 'revision' => $revision,
                'principal_outstanding_minor' => (int) $data['principal_outstanding_minor'],
                'days_past_due' => (int) $data['days_past_due'],
                'sicr' => (bool) $data['sicr'],
                'credit_impaired' => (bool) $data['credit_impaired'],
                'contractual_maturity_date' => $data['contractual_maturity_date'],
                'source_evidence' => $data['source_evidence'],
                'reason' => $data['reason'],
            ];
            return DB::table('institution_ecl_source_corrections')->insertGetId($change + [
                'correction_hash' => hash('sha256', json_encode($change, JSON_THROW_ON_ERROR)),
                'status' => 'draft', 'created_by' => $maker->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function approveSourceCorrection(int $correctionId, User $checker): void
    {
        DB::transaction(function () use ($correctionId, $checker) {
            $correction = DB::table('institution_ecl_source_corrections')
                ->where('id', $correctionId)->lockForUpdate()->first();
            if (! $correction || $correction->status !== 'draft'
                || (int) $correction->created_by === (int) $checker->id) {
                throw new InvalidArgumentException('A different independent checker must approve the source correction.');
            }
            DB::table('institution_ecl_source_corrections')->where('id', $correctionId)->update([
                'status' => 'approved', 'approved_by' => $checker->id,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function build(int $institutionId, string $asOf, int $expectedExternalCount, int $expectedExternalPrincipal, string $coverageReference, User $actor, ?int $restatesRunId = null, ?string $restatementReason = null): int
    {
        $date = Carbon::parse($asOf);
        if ($expectedExternalCount < 0 || $expectedExternalPrincipal < 0 || trim($coverageReference) === '') {
            throw new InvalidArgumentException('Source completeness totals and evidence reference are required.');
        }
        if ($date->toDateString() > now()->toDateString()) {
            throw new InvalidArgumentException('Cannot run a future portfolio cut-off.');
        }
        $policy = DB::table('institution_ecl_policies')
            ->where('institution_id', $institutionId)->where('status', 'approved')
            ->where('effective_from', '<=', $asOf)->orderByDesc('effective_from')->orderByDesc('id')->first();
        if (! $policy) {
            throw new InvalidArgumentException('No independently approved effective ECL policy.');
        }
        $restatedRun = null;
        if ($restatesRunId !== null) {
            $restatedRun = DB::table('institution_ecl_runs')->find($restatesRunId);
            if (! $restatedRun || $restatedRun->status !== 'finalised'
                || (int) $restatedRun->institution_id !== $institutionId
                || $restatedRun->as_of_date !== $asOf
                || (int) $restatedRun->platform_ecl_minor !== 0
                || trim((string) $restatementReason) === '') {
                throw new InvalidArgumentException('Historical restatement is allowed only for a signed advisory-only run with a documented correction.');
            }
        }
        $scenarios = json_decode($policy->scenarios, true, 512, JSON_THROW_ON_ERROR);
        $this->calculator->validateScenarios($scenarios);
        $approvedOwnerships = DB::table('loan_ecl_ownerships')
            ->where('institution_id', $institutionId)->where('status', 'approved')->get();
        $allEligibleLoans = Loan::withoutGlobalScopes()->where('institution_id', $institutionId)
            ->whereDate('created_at', '<=', $asOf)->whereNotIn('status', ['Cancelled', 'Rejected', 'Reversed'])->pluck('id')->map(fn ($id) => (int) $id);
        $approvedIds = $approvedOwnerships->pluck('loan_id')->map(fn ($id) => (int) $id);
        if ($allEligibleLoans->diff($approvedIds)->isNotEmpty()) {
            throw new InvalidArgumentException('An institution loan has no independently approved ownership attestation.');
        }
        $external = DB::table('institution_ecl_external_snapshots')
            ->where('institution_id', $institutionId)->where('as_of_date', $asOf)
            ->where('status', 'approved')->orderBy('id')->get();
        $snapshotIds = $external->pluck('id')->all();
        $stagedCorrections = DB::table('institution_ecl_source_corrections')
            ->whereIn('external_snapshot_id', $snapshotIds)->where('status', 'draft')->exists();
        if ($stagedCorrections) {
            throw new InvalidArgumentException('An independent review of a pending source correction is required before calculation.');
        }
        $corrections = DB::table('institution_ecl_source_corrections')
            ->whereIn('external_snapshot_id', $snapshotIds)->where('status', 'approved')
            ->orderByDesc('revision')->get()->unique('external_snapshot_id')->keyBy('external_snapshot_id');
        foreach ($external as $record) {
            $correction = $corrections->get($record->id);
            if ($correction) {
                foreach (['principal_outstanding_minor', 'days_past_due', 'sicr', 'credit_impaired', 'contractual_maturity_date'] as $field) {
                    $record->{$field} = $correction->{$field};
                }
            }
        }
        if ($approvedOwnerships->isEmpty() && $external->isEmpty()) {
            throw new InvalidArgumentException('No reviewed exposures to provision.');
        }
        if ($approvedOwnerships->isNotEmpty() && $date->toDateString() !== now()->toDateString()) {
            throw new InvalidArgumentException('Historical managed-loan balances cannot be reconstructed from current schedules. Capture at cut-off date.');
        }
        $staged = DB::table('institution_ecl_external_snapshots')->where('institution_id', $institutionId)->where('as_of_date', $asOf)->where('status', 'staged')->exists();
        if ($staged || $external->count() !== $expectedExternalCount || (int) $external->sum('principal_outstanding_minor') !== $expectedExternalPrincipal) {
            throw new InvalidArgumentException('Unreviewed external source or submitted counts/principal do not match signed-off source controls.');
        }
        if ($external->contains(fn ($item) => $item->currency !== $policy->currency)) {
            throw new InvalidArgumentException('External snapshot currency differs from the approved policy.');
        }

        $rows = [];
        foreach ($approvedOwnerships as $ownership) {
            $loan = Loan::withoutGlobalScopes()->findOrFail($ownership->loan_id);
            if ($loan->created_at && $loan->created_at->toDateString() > $asOf) {
                continue;
            }
            $writtenOff = DB::table('credit_write_offs')->where('loan_id', $loan->id)->exists();
            $gross = $writtenOff ? 0 : $this->impairment->grossRecordedExposureMinor($loan);
            $loanCurrency = $loan->credit_offer_id
                ? DB::table('credit_offers')->where('id', $loan->credit_offer_id)->value('currency')
                : config('services.mobile_money.currency', 'UGX');
            if (strtoupper((string) $loanCurrency) !== $policy->currency) {
                throw new InvalidArgumentException('Managed exposure currency does not match the institution ECL policy.');
            }
            $dpd = $this->daysPastDue($loan, $date);
            $termEnd = $loan->disbursed_at
                ? Carbon::parse($loan->disbursed_at)->addDays((int) $loan->duration)
                : $date->copy()->addDays((int) $loan->duration);
            $months = max(0, (int) ceil($date->diffInDays($termEnd, false) / 30));
            $rows[] = $this->item(
                'opfin:'.$loan->id, (int) $loan->id, null, $ownership->owner_type,
                (int) $ownership->opfin_share_bps, $gross, $dpd, false, (bool) $loan->non_performing_at || $writtenOff,
                $months, $policy, $scenarios
            );
        }
        foreach ($external as $observation) {
            $months = max(0, (int) ceil($date->diffInDays(Carbon::parse($observation->contractual_maturity_date), false) / 30));
            $rows[] = $this->item(
                'external:'.$observation->external_loan_reference, null, (int) $observation->id,
                'partner', 0, (int) $observation->principal_outstanding_minor, (int) $observation->days_past_due,
                (bool) $observation->sicr, (bool) $observation->credit_impaired,
                $months, $policy, $scenarios
            );
        }
        if (count($rows) > 50000) {
            throw new InvalidArgumentException('ECL batch exceeds the reviewed 50,000 exposure limit. Partition by approved institution/subportfolio.');
        }
        $references = array_column($rows, 'loan_reference');
        if (count($references) !== count(array_unique($references))) {
            throw new InvalidArgumentException('Duplicate exposure references in monthly cut-off.');
        }
        $previous = DB::table('institution_ecl_runs')->where('institution_id', $institutionId)
            ->where('as_of_date', '<', $asOf)->whereIn('status', ['approved', 'posted', 'finalised'])
            ->orderByDesc('as_of_date')->orderByDesc('revision')->first();
        $previousAmounts = $previous ? DB::table('institution_ecl_run_items')
            ->where('run_id', $previous->id)->pluck('expected_credit_loss_minor', 'loan_reference') : collect();
        $missing = array_diff($previousAmounts->keys()->all(), $references);
        if ($missing !== []) {
            throw new InvalidArgumentException('Previous-period exposures are missing from the current extract; submit explicit zero-balance closures before sign-off.');
        }
        foreach ($rows as &$row) {
            $row['movement_minor'] = $row['expected_credit_loss_minor'] - (int) ($previousAmounts[$row['loan_reference']] ?? 0);
        }
        unset($row);
        $totalExposure = array_sum(array_column($rows, 'gross_exposure_minor'));
        $totalEcl = array_sum(array_column($rows, 'expected_credit_loss_minor'));
        $platformEcl = array_sum(array_column($rows, 'platform_ecl_minor'));
        $previousTotal = (int) ($previous?->total_ecl_minor ?? 0);
        $revision = ((int) DB::table('institution_ecl_runs')->where('institution_id', $institutionId)
            ->where('as_of_date', $asOf)->max('revision')) + 1;
        $hash = hash('sha256', json_encode([$institutionId, $asOf, $policy->id, $rows, $previous?->id, $restatesRunId], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($institutionId, $asOf, $policy, $actor, $rows, $totalExposure, $totalEcl, $platformEcl, $previousTotal, $hash, $revision, $expectedExternalCount, $expectedExternalPrincipal, $coverageReference, $restatesRunId, $restatementReason, $restatedRun) {
            if (! $restatedRun && DB::table('institution_ecl_runs')->where('institution_id', $institutionId)
                ->where('as_of_date', $asOf)->whereIn('status', ['approved', 'posted', 'finalised'])->exists()) {
                throw new InvalidArgumentException('A signed-off cut-off already exists. Use a controlled restatement process.');
            }
            $runId = DB::table('institution_ecl_runs')->insertGetId([
                'institution_id' => $institutionId, 'policy_id' => $policy->id,
                'as_of_date' => $asOf, 'revision' => $revision,
                'restates_run_id' => $restatesRunId,
                'restatement_reason' => $restatedRun ? $restatementReason : null,
                'restatement_delta_minor' => $restatedRun ? $totalEcl - (int) $restatedRun->total_ecl_minor : 0,
                'currency' => $policy->currency,
                'status' => 'draft', 'total_exposure_minor' => $totalExposure,
                'total_ecl_minor' => $totalEcl, 'platform_ecl_minor' => $platformEcl,
                'ecl_movement_minor' => $totalEcl - $previousTotal, 'content_hash' => $hash,
                'expected_external_count' => $expectedExternalCount,
                'expected_external_principal_minor' => $expectedExternalPrincipal,
                'coverage_reference' => $coverageReference,
                'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($rows as $row) {
                DB::table('institution_ecl_run_items')->insert($row + [
                    'run_id' => $runId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $runId;
        });
    }

    public function approveRun(int $runId, User $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = DB::table('institution_ecl_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'draft' || (int) $run->created_by === (int) $actor->id) {
                throw new InvalidArgumentException('Independent approval is required for a draft monthly ECL run.');
            }
            DB::table('institutions')->where('id', $run->institution_id)->lockForUpdate()->first();
            $alreadySigned = DB::table('institution_ecl_runs')
                ->where('institution_id', $run->institution_id)->where('as_of_date', $run->as_of_date)
                ->where('id', '!=', $runId)->whereIn('status', ['approved', 'posted', 'finalised'])->exists();
            if ($alreadySigned && (! $run->restates_run_id
                || DB::table('institution_ecl_runs')->where('id', $run->restates_run_id)
                    ->where('institution_id', $run->institution_id)->where('as_of_date', $run->as_of_date)
                    ->where('status', 'finalised')->where('platform_ecl_minor', 0)->doesntExist())) {
                throw new InvalidArgumentException('Another signed cut-off exists without an eligible advisory-only restatement.');
            }
            if ($run->restates_run_id) {
                DB::table('institution_ecl_runs')->where('id', $run->restates_run_id)
                    ->where('status', 'finalised')
                    ->update(['status' => 'superseded', 'updated_at' => now()]);
            }
            DB::table('institution_ecl_runs')->where('id', $runId)->update([
                'status' => 'approved', 'approved_by' => $actor->id,
                'approved_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function postRun(int $runId, User $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = DB::table('institution_ecl_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'approved') {
                throw new InvalidArgumentException('An independently approved ECL run is required.');
            }
            $policy = DB::table('institution_ecl_policies')->find($run->policy_id);
            $platformJournalCount = 0;
            foreach (DB::table('institution_ecl_run_items')->where('run_id', $runId)->orderBy('id')->get() as $item) {
                // Never book another lender's exposure, nor a shared claim whose receivable ownership
                // has not yet been reconciled in the canonical loan subledger.
                if ($item->owner_type !== 'opfin' || ! $item->loan_id) {
                    continue;
                }
                $ownership = DB::table('loan_ecl_ownerships')->where('loan_id', $item->loan_id)->first();
                if (! $ownership || $ownership->status !== 'approved'
                    || $ownership->owner_type !== 'opfin' || (int) $ownership->opfin_share_bps !== 10000) {
                    throw new InvalidArgumentException('Credit ownership changed since the ECL snapshot.');
                }
                $liveLoan = Loan::withoutGlobalScopes()->findOrFail($item->loan_id);
                $writtenOff = DB::table('credit_write_offs')->where('loan_id', $liveLoan->id)->exists();
                if ($writtenOff && (int) $item->gross_exposure_minor === 0) {
                    continue; // Its approved allowance was already consumed by the original write-off.
                }
                $platformJournalCount++;
                if ($this->impairment->grossRecordedExposureMinor($liveLoan) !== (int) $item->gross_exposure_minor) {
                    throw new InvalidArgumentException('Loan balance changed after ECL cut-off; rebuild from verified accounting evidence.');
                }
                $assessment = $this->impairment->assess(
                    $liveLoan,
                    Carbon::parse($run->as_of_date),
                    (int) $item->expected_credit_loss_minor,
                    $item->stage, $policy->version,
                    ['institutional_run' => $runId, 'calculation' => json_decode($item->calculation_evidence, true, 512, JSON_THROW_ON_ERROR)],
                    $actor
                );
                DB::table('institution_ecl_run_items')->where('id', $item->id)
                    ->update(['impairment_assessment_id' => $assessment->id, 'updated_at' => now()]);
            }
            DB::table('institution_ecl_runs')->where('id', $runId)->update([
                'status' => $platformJournalCount > 0 ? 'posted' : 'finalised',
                'posted_at' => $platformJournalCount > 0 ? now() : null, 'updated_at' => now(),
            ]);
        });
    }

    public function details(int $runId): array
    {
        $run = DB::table('institution_ecl_runs')->find($runId);
        if (! $run) {
            throw new InvalidArgumentException('ECL run not found.');
        }
        return ['run' => $run, 'items' => DB::table('institution_ecl_run_items')
            ->where('run_id', $runId)->orderBy('loan_reference')->get()];
    }

    private function item(
        string $reference, ?int $loanId, ?int $snapshotId, string $owner, int $shareBps,
        int $gross, int $dpd, bool $sicr, bool $impaired, int $months,
        object $policy, array $scenarios
    ): array {
        $result = $this->calculator->calculate($gross, $dpd, $sicr, $impaired, $months,
            (int) $policy->stage2_dpd, (int) $policy->stage3_dpd, (int) $policy->discount_bps, $scenarios);
        return [
            'loan_id' => $loanId, 'external_snapshot_id' => $snapshotId,
            'loan_reference' => $reference, 'owner_type' => $owner,
            'opfin_share_bps' => $shareBps, 'gross_exposure_minor' => $gross,
            'days_past_due' => $dpd, 'stage' => $result['stage'],
            'expected_credit_loss_minor' => $result['expected_credit_loss_minor'],
            'platform_ecl_minor' => (int) round($result['expected_credit_loss_minor'] * $shareBps / 10000),
            'calculation_evidence' => json_encode($result['evidence'], JSON_THROW_ON_ERROR),
        ];
    }

    private function daysPastDue(Loan $loan, Carbon $asOf): int
    {
        $query = $loan->credit_offer_id
            ? DB::table('credit_repayment_schedule_items')->where('total_outstanding_minor', '>', 0)
            : DB::table('loan_schedules')->where('total_outstanding', '>', 0);
        $due = $query->where('loan_id', $loan->id)->where('due_date', '<', $asOf->toDateString())
            ->orderBy('due_date')->value('due_date');
        return $due ? min(65535, (int) Carbon::parse($due)->diffInDays($asOf)) : 0;
    }
}
