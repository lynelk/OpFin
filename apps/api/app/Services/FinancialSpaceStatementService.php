<?php

namespace App\Services;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceGeneratedStatement;
use App\Models\FinancialSpaceStatementImport;
use App\Models\FinancialSpaceStatementRow;
use App\Models\FinancialSpaceTransaction;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FinancialSpaceStatementService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function createAccount(FinancialSpace $space, User $actor, array $data): FinancialSpaceTreasuryAccount
    {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);

        $openingBalanceMinor = (int) ($data['opening_balance_minor'] ?? 0);
        $openingBalanceAsOf = $data['balance_as_of'] ?? null;
        if ($openingBalanceMinor !== 0 && ! $openingBalanceAsOf) {
            throw ValidationException::withMessages([
                'balance_as_of' => ['A non-zero opening balance requires the date that balance was valid.'],
            ]);
        }

        return FinancialSpaceTreasuryAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'financial_space_id' => $space->id,
            'account_name' => trim($data['account_name']),
            'account_type' => $data['account_type'],
            'institution_name' => $data['institution_name'] ?? null,
            'account_reference_masked' => $this->maskedReference($data['account_reference'] ?? null),
            'currency' => strtoupper($data['currency'] ?? $space->currency),
            'opening_balance_minor' => $openingBalanceMinor,
            'current_balance_minor' => $openingBalanceMinor,
            'balance_as_of' => $openingBalanceAsOf,
            'current_balance_as_of' => $openingBalanceAsOf,
            'status' => 'active',
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function recordTransaction(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        User $actor,
        array $data
    ): FinancialSpaceTransaction {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        $this->assertAccount($space, $account);

        if (strtoupper($data['currency'] ?? $account->currency) !== $account->currency) {
            throw ValidationException::withMessages([
                'currency' => ['Transaction currency must match the treasury account currency.'],
            ]);
        }
        if ($account->balance_as_of
            && CarbonImmutable::parse($data['transaction_date'])->lessThan(CarbonImmutable::parse($account->balance_as_of))) {
            throw ValidationException::withMessages([
                'transaction_date' => ['Transaction date cannot be before this account opening-balance baseline.'],
            ]);
        }

        $sourceType = $data['source_type'] ?? 'manual';
        $sourceReference = $data['source_reference'] ?? null;
        if ($sourceReference === null || trim((string) $sourceReference) === '') {
            $sourceReference = 'manual:'.Str::uuid();
        }

        return DB::transaction(function () use ($space, $account, $actor, $data, $sourceType, $sourceReference) {
            $lockedAccount = FinancialSpaceTreasuryAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertAccount($space, $lockedAccount);

            $existing = FinancialSpaceTransaction::query()
                ->where('financial_space_id', $space->id)
                ->where('source_type', $sourceType)
                ->where('source_reference', $sourceReference)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $sameInstruction = (int) $existing->treasury_account_id === (int) $lockedAccount->id
                    && $existing->direction === $data['direction']
                    && (int) $existing->amount_minor === (int) $data['amount_minor']
                    && strtoupper((string) $existing->currency) === strtoupper((string) $lockedAccount->currency)
                    && $existing->transaction_date?->toDateString() === CarbonImmutable::parse($data['transaction_date'])->toDateString();

                if (! $sameInstruction) {
                    throw new InvalidArgumentException(
                        'Treasury source reference was already used for a different canonical cashbook instruction.'
                    );
                }

                return $existing->fresh();
            }

            $transaction = FinancialSpaceTransaction::query()->create([
                'public_id' => (string) Str::uuid(),
                'financial_space_id' => $space->id,
                'treasury_account_id' => $lockedAccount->id,
                'created_by_user_id' => $actor->id,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? 'other',
                'direction' => $data['direction'],
                'amount_minor' => (int) $data['amount_minor'],
                'currency' => $lockedAccount->currency,
                'description' => trim($data['description']),
                'counterparty_name' => $data['counterparty_name'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'value_date' => $data['value_date'] ?? null,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'reconciliation_status' => 'unreconciled',
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->refreshAccountBalance($lockedAccount);

            $this->auditLogger->record('financial_space.treasury.transaction_recorded', $actor, $transaction, [
                'financial_space_id' => $space->id,
                'treasury_account_id' => $lockedAccount->id,
                'direction' => $transaction->direction,
                'amount_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
            ]);

            return $transaction->fresh();
        });
    }

    public function importCsv(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        User $actor,
        UploadedFile $file,
        array $mapping,
        int $minorUnitExponent = 0,
        ?int $openingBalanceMinor = null,
        ?int $closingBalanceMinor = null,
    ): FinancialSpaceStatementImport {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        $this->assertAccount($space, $account);
        $this->assertMapping($mapping);

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            throw new InvalidArgumentException('The uploaded statement file is empty.');
        }

        $sourceHash = hash('sha256', $contents);
        $existing = FinancialSpaceStatementImport::query()
            ->where('financial_space_id', $space->id)
            ->where('treasury_account_id', $account->id)
            ->where('source_hash', $sourceHash)
            ->first();
        if ($existing) {
            return $existing->load('rows');
        }

        $parsed = $this->parseCsv($contents);
        if (count($parsed) < 2) {
            throw new InvalidArgumentException('The statement CSV must contain a header row and at least one transaction row.');
        }

        $headers = array_map(fn ($header) => $this->normaliseHeader($header), array_shift($parsed));
        $indexed = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $indexed[$header] = $index;
            }
        }

        $rows = [];
        foreach ($parsed as $lineNumber => $values) {
            if ($this->blankRow($values)) {
                continue;
            }

            $record = $this->normaliseCsvRecord(
                $values,
                $indexed,
                $mapping,
                $account->currency,
                $minorUnitExponent,
                $lineNumber + 2,
            );
            $rows[] = $record;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The statement did not contain any importable transactions.');
        }

        $importDates = collect($rows)
            ->pluck('transaction_date')
            ->map(fn ($date) => CarbonImmutable::parse($date));
        if ($account->balance_as_of
            && $importDates->min()?->lessThan(CarbonImmutable::parse($account->balance_as_of))) {
            throw ValidationException::withMessages([
                'statement_file' => ['This statement begins before the treasury account opening-balance baseline and cannot be reconciled safely.'],
            ]);
        }

        return DB::transaction(function () use (
            $space,
            $account,
            $actor,
            $file,
            $sourceHash,
            $mapping,
            $rows,
            $minorUnitExponent,
            $openingBalanceMinor,
            $closingBalanceMinor,
        ) {
            $dates = collect($rows)->pluck('transaction_date')->map(fn ($date) => CarbonImmutable::parse($date));

            $import = FinancialSpaceStatementImport::query()->create([
                'public_id' => (string) Str::uuid(),
                'financial_space_id' => $space->id,
                'treasury_account_id' => $account->id,
                'imported_by_user_id' => $actor->id,
                'source_format' => 'csv',
                'original_filename' => $file->getClientOriginalName(),
                'source_hash' => $sourceHash,
                'period_start' => $dates->min()?->toDateString(),
                'period_end' => $dates->max()?->toDateString(),
                'opening_balance_minor' => $openingBalanceMinor,
                'closing_balance_minor' => $closingBalanceMinor,
                'row_count' => count($rows),
                'matched_count' => 0,
                'exception_count' => 0,
                'status' => 'parsed',
                'column_mapping' => $mapping,
                'summary' => [
                    'currency' => $account->currency,
                    'minor_unit_exponent' => $minorUnitExponent,
                    'source' => 'uploaded_statement',
                ],
            ]);

            foreach ($rows as $record) {
                FinancialSpaceStatementRow::query()->create([
                    'statement_import_id' => $import->id,
                    'financial_space_id' => $space->id,
                    'treasury_account_id' => $account->id,
                    'row_hash' => hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)),
                    'statement_reference' => $record['statement_reference'],
                    'transaction_date' => $record['transaction_date'],
                    'value_date' => $record['value_date'],
                    'description' => $record['description'],
                    'direction' => $record['direction'],
                    'amount_minor' => $record['amount_minor'],
                    'currency' => $record['currency'],
                    'running_balance_minor' => $record['running_balance_minor'],
                    'reconciliation_status' => 'unmatched',
                    'raw_payload' => $record['raw_payload'],
                ]);
            }

            $this->auditLogger->record('financial_space.statement_import.created', $actor, $import, [
                'financial_space_id' => $space->id,
                'treasury_account_id' => $account->id,
                'row_count' => count($rows),
                'source_hash' => $sourceHash,
            ]);

            return $import->fresh('rows');
        });
    }

    public function reconcile(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        User $actor,
    ): FinancialSpaceStatementImport {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($import->financial_space_id === $space->id, 404);
        abort_if(in_array($import->confirmation_status, ['confirmed', 'confirmed_with_exceptions'], true), 409, 'A confirmed reconciliation cannot be re-run.');

        return DB::transaction(function () use ($space, $import, $actor) {
            $lockedImport = FinancialSpaceStatementImport::query()
                ->whereKey($import->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if(
                in_array($lockedImport->confirmation_status, ['confirmed', 'confirmed_with_exceptions'], true),
                409,
                'A confirmed reconciliation cannot be re-run.'
            );

            foreach (
                $lockedImport->rows()
                    ->whereNotIn('reconciliation_status', ['matched', 'resolved'])
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->get() as $candidateRow
            ) {
                $row = FinancialSpaceStatementRow::query()
                    ->whereKey($candidateRow->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $ranked = $this->rankedCandidateTransactions($row);
                $top = $ranked->first();
                $second = $ranked->skip(1)->first();
                $topScore = (int) ($top['score'] ?? 0);
                $secondScore = (int) ($second['score'] ?? 0);
                $margin = $topScore - $secondScore;

                if ($top && $topScore >= 95 && ($ranked->count() === 1 || $margin >= 10)) {
                    $transaction = FinancialSpaceTransaction::query()
                        ->whereKey($top['transaction']->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $alreadyUsed = FinancialSpaceStatementRow::query()
                        ->where('matched_transaction_id', $transaction->id)
                        ->where('reconciliation_status', 'matched')
                        ->whereKeyNot($row->id)
                        ->exists();

                    if ($transaction->reconciliation_status === 'unreconciled' && ! $alreadyUsed) {
                        $row->update([
                            'matched_transaction_id' => $transaction->id,
                            'reconciliation_status' => 'matched',
                            'exception_type' => null,
                            'match_method' => $top['method'],
                            'match_confidence_percent' => $topScore,
                            'suggested_matches' => null,
                            'user_resolution' => null,
                            'resolved_by_user_id' => null,
                            'resolved_at' => null,
                            'notes' => 'Automatically matched by OpFin using high-confidence reconciliation evidence.',
                        ]);
                        $transaction->update([
                            'reconciliation_status' => 'matched',
                            'reconciled_at' => now(),
                        ]);

                        continue;
                    }

                    $ranked = $this->rankedCandidateTransactions($row);
                    $top = $ranked->first();
                    $topScore = (int) ($top['score'] ?? 0);
                }

                $suggestions = $ranked
                    ->take(3)
                    ->map(fn (array $candidate) => $this->candidatePresentation($candidate))
                    ->values()
                    ->all();

                if ($top && $topScore >= 70) {
                    $row->update([
                        'matched_transaction_id' => null,
                        'reconciliation_status' => 'review_required',
                        'exception_type' => 'suggested_match',
                        'match_method' => null,
                        'match_confidence_percent' => $topScore,
                        'suggested_matches' => $suggestions,
                        'notes' => 'OpFin found a plausible match but requires user confirmation.',
                    ]);

                    continue;
                }

                $row->update([
                    'matched_transaction_id' => null,
                    'reconciliation_status' => 'review_required',
                    'exception_type' => $ranked->count() > 1 ? 'ambiguous_match' : 'missing_opfin_transaction',
                    'match_method' => null,
                    'match_confidence_percent' => null,
                    'suggested_matches' => $suggestions ?: null,
                    'notes' => $ranked->count() > 1
                        ? 'Several low-confidence OpFin transactions may relate to this statement row.'
                        : 'No sufficiently plausible OpFin cashbook transaction was found.',
                ]);
            }

            $this->refreshImportState($lockedImport);

            if ($lockedImport->closing_balance_minor !== null) {
                $account = $lockedImport->account;
                $bookClosing = $this->bookBalanceAsOf($account, CarbonImmutable::parse($lockedImport->period_end));
                $summary = $lockedImport->fresh()->summary ?? [];
                $summary['book_closing_balance_minor'] = $bookClosing;
                $summary['statement_closing_balance_minor'] = $lockedImport->closing_balance_minor;
                $summary['closing_balance_variance_minor'] = $bookClosing - $lockedImport->closing_balance_minor;
                $lockedImport->update(['summary' => $summary]);
            }

            $this->refreshImportState($lockedImport);

            $this->auditLogger->record('financial_space.statement_import.reconciled', $actor, $lockedImport, [
                'financial_space_id' => $space->id,
                'matched_count' => $lockedImport->fresh()->matched_count,
                'exception_count' => $lockedImport->fresh()->exception_count,
                'review_todo_count' => count($lockedImport->fresh()->review_todos ?? []),
            ]);

            return $lockedImport->fresh(['rows.matchedTransaction', 'account']);
        });
    }

    public function resolveStatementRow(
        FinancialSpace $space,
        FinancialSpaceStatementRow $row,
        User $actor,
        string $action,
        ?int $transactionId = null,
        ?string $reason = null,
    ): FinancialSpaceStatementRow {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($row->financial_space_id === $space->id, 404);

        if ($action === 'match_transaction') {
            if (! $transactionId) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Choose the OpFin transaction to match.'],
                ]);
            }

            $transaction = FinancialSpaceTransaction::query()->findOrFail($transactionId);

            return $this->matchStatementRow($space, $row, $transaction, $actor);
        }

        if ($row->reconciliation_status === 'matched') {
            throw ValidationException::withMessages([
                'action' => ['This row is already reconciled.'],
            ]);
        }

        if ($action === 'create_book_entry') {
            return DB::transaction(function () use ($space, $row, $actor, $reason) {
                $account = FinancialSpaceTreasuryAccount::query()->findOrFail($row->treasury_account_id);
                $transaction = $this->recordTransaction($space, $account, $actor, [
                    'transaction_reference' => $row->statement_reference,
                    'transaction_type' => 'statement_import_adjustment',
                    'direction' => $row->direction,
                    'amount_minor' => (int) $row->amount_minor,
                    'currency' => $row->currency,
                    'description' => $row->description,
                    'counterparty_name' => null,
                    'transaction_date' => $row->transaction_date->toDateString(),
                    'value_date' => $row->value_date?->toDateString(),
                    'source_type' => 'statement_user_request',
                    'source_reference' => 'statement-row:'.$row->id,
                    'metadata' => [
                        'statement_import_id' => $row->statement_import_id,
                        'requested_reason' => $reason,
                    ],
                ]);

                return $this->matchStatementRow($space, $row, $transaction, $actor);
            });
        }

        if (! in_array($action, ['mark_external_only', 'mark_duplicate'], true)) {
            throw ValidationException::withMessages([
                'action' => ['Unsupported reconciliation action.'],
            ]);
        }
        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Explain why this exception is being accepted.'],
            ]);
        }

        $row->update([
            'matched_transaction_id' => null,
            'reconciliation_status' => 'resolved',
            'exception_type' => $action === 'mark_duplicate' ? 'accepted_duplicate' : 'accepted_external_only',
            'match_method' => 'user_resolution',
            'match_confidence_percent' => null,
            'user_resolution' => $action,
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now(),
            'notes' => trim((string) $reason),
        ]);

        $import = FinancialSpaceStatementImport::query()->findOrFail($row->statement_import_id);
        $this->refreshImportState($import);

        $this->auditLogger->record('financial_space.statement_row.resolved', $actor, $row, [
            'financial_space_id' => $space->id,
            'statement_import_id' => $import->id,
            'action' => $action,
            'reason' => trim((string) $reason),
        ]);

        return $row->fresh('matchedTransaction');
    }

    public function resolveBookTransaction(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        FinancialSpaceTransaction $transaction,
        User $actor,
        string $reason,
    ): FinancialSpaceTransaction {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($import->financial_space_id === $space->id, 404);
        abort_unless($transaction->financial_space_id === $space->id, 404);
        abort_unless($transaction->treasury_account_id === $import->treasury_account_id, 422);
        abort_unless(
            $transaction->transaction_date->between(
                CarbonImmutable::parse($import->period_start),
                CarbonImmutable::parse($import->period_end)
            ),
            422
        );
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Explain why this book transaction is being accepted without an external statement match.'],
            ]);
        }

        $metadata = $transaction->metadata ?? [];
        $metadata['reconciliation_exception'] = [
            'statement_import_id' => $import->id,
            'resolution' => 'accepted_book_only',
            'reason' => trim($reason),
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now()->toIso8601String(),
        ];
        $transaction->update([
            'reconciliation_status' => 'accepted_exception',
            'reconciled_at' => now(),
            'metadata' => $metadata,
        ]);

        $this->refreshImportState($import);

        $this->auditLogger->record('financial_space.book_transaction.accepted_exception', $actor, $transaction, [
            'financial_space_id' => $space->id,
            'statement_import_id' => $import->id,
            'reason' => trim($reason),
        ]);

        return $transaction->fresh();
    }

    public function resolveBalanceVariance(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        User $actor,
        string $reason,
    ): FinancialSpaceStatementImport {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($import->financial_space_id === $space->id, 404);
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Explain why the closing-balance variance is being accepted.'],
            ]);
        }

        $summary = $import->summary ?? [];
        $variance = (int) ($summary['closing_balance_variance_minor'] ?? 0);
        if ($variance === 0) {
            return $import->fresh();
        }
        $summary['accepted_closing_balance_variance'] = [
            'amount_minor' => $variance,
            'reason' => trim($reason),
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => now()->toIso8601String(),
        ];
        $import->update(['summary' => $summary]);
        $this->refreshImportState($import);

        return $import->fresh(['rows', 'account']);
    }

    public function confirmReconciliation(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        User $actor,
        ?string $note = null,
    ): FinancialSpaceStatementImport {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($import->financial_space_id === $space->id, 404);
        abort_if($import->confirmed_at !== null, 409, 'This reconciliation has already been confirmed.');

        $this->refreshImportState($import);
        $fresh = $import->fresh();
        $todos = $fresh->review_todos ?? [];
        if ($todos !== []) {
            throw ValidationException::withMessages([
                'reconciliation' => ['Complete or explicitly resolve the remaining reconciliation to-dos before confirmation.'],
            ]);
        }

        $acceptedExceptions = FinancialSpaceStatementRow::query()
            ->where('statement_import_id', $import->id)
            ->where('reconciliation_status', 'resolved')
            ->count()
            + FinancialSpaceTransaction::query()
                ->where('treasury_account_id', $import->treasury_account_id)
                ->whereBetween('transaction_date', [$import->period_start, $import->period_end])
                ->where('reconciliation_status', 'accepted_exception')
                ->count();

        $summary = $fresh->summary ?? [];
        if (! empty($summary['accepted_closing_balance_variance'])) {
            $acceptedExceptions++;
        }
        $summary['confirmation_note'] = $note;

        $import->update([
            'status' => 'reconciled',
            'confirmation_status' => $acceptedExceptions > 0 ? 'confirmed_with_exceptions' : 'confirmed',
            'confirmed_by_user_id' => $actor->id,
            'confirmed_at' => now(),
            'summary' => $summary,
        ]);

        $this->auditLogger->record('financial_space.statement_import.confirmed', $actor, $import, [
            'financial_space_id' => $space->id,
            'accepted_exception_count' => $acceptedExceptions,
            'confirmation_status' => $import->fresh()->confirmation_status,
        ]);

        return $import->fresh(['rows.matchedTransaction', 'account']);
    }

    public function matchStatementRow(
        FinancialSpace $space,
        FinancialSpaceStatementRow $row,
        FinancialSpaceTransaction $transaction,
        User $actor,
    ): FinancialSpaceStatementRow {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        abort_unless($row->financial_space_id === $space->id, 404);
        abort_unless($transaction->financial_space_id === $space->id, 404);
        abort_unless($row->treasury_account_id === $transaction->treasury_account_id, 422);

        if ($row->reconciliation_status === 'matched') {
            if ((int) $row->matched_transaction_id === (int) $transaction->id) {
                return $row->fresh('matchedTransaction');
            }

            throw ValidationException::withMessages([
                'transaction_id' => ['This statement row is already reconciled to a different transaction.'],
            ]);
        }

        if ($row->currency !== $transaction->currency
            || $row->direction !== $transaction->direction
            || (int) $row->amount_minor !== (int) $transaction->amount_minor) {
            throw ValidationException::withMessages([
                'transaction_id' => ['Manual reconciliation requires the same account, currency, direction and amount.'],
            ]);
        }

        $alreadyUsed = FinancialSpaceStatementRow::query()
            ->where('matched_transaction_id', $transaction->id)
            ->where('reconciliation_status', 'matched')
            ->whereKeyNot($row->id)
            ->exists();
        if ($alreadyUsed) {
            throw ValidationException::withMessages([
                'transaction_id' => ['That OpFin transaction is already matched to another statement row.'],
            ]);
        }

        return DB::transaction(function () use ($space, $row, $transaction, $actor) {
            $row->update([
                'matched_transaction_id' => $transaction->id,
                'reconciliation_status' => 'matched',
                'exception_type' => null,
                'match_method' => 'manual',
                'match_confidence_percent' => 100,
                'notes' => 'Manually matched by an authorised finance role.',
            ]);
            $transaction->update([
                'reconciliation_status' => 'matched',
                'reconciled_at' => now(),
            ]);

            $import = FinancialSpaceStatementImport::query()->findOrFail($row->statement_import_id);
            $this->refreshImportState($import);

            $this->auditLogger->record('financial_space.statement_row.manually_matched', $actor, $row, [
                'financial_space_id' => $space->id,
                'statement_import_id' => $import->id,
                'transaction_id' => $transaction->id,
            ]);

            return $row->fresh('matchedTransaction');
        });
    }

    public function generateStatement(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        User $actor,
        string $from,
        string $to,
    ): array {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);
        $this->assertAccount($space, $account);

        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->startOfDay();
        if ($toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages(['to' => ['Statement end date must not be before the start date.']]);
        }
        $this->assertStatementBaseline($account, $fromDate);

        $transactions = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $account->id)
            ->whereBetween('transaction_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderBy('transaction_date')
            ->orderBy('value_date')
            ->orderBy('id')
            ->get();

        $opening = $this->bookBalanceBefore($account, $fromDate);
        $balance = $opening;
        $totalDebits = 0;
        $totalCredits = 0;
        $rows = [];

        foreach ($transactions as $transaction) {
            if ($transaction->direction === 'credit') {
                $balance += $transaction->amount_minor;
                $totalCredits += $transaction->amount_minor;
            } else {
                $balance -= $transaction->amount_minor;
                $totalDebits += $transaction->amount_minor;
            }

            $rows[] = [
                'date' => $transaction->transaction_date->toDateString(),
                'value_date' => $transaction->value_date?->toDateString(),
                'description' => $transaction->description,
                'reference' => $transaction->transaction_reference,
                'debit_minor' => $transaction->direction === 'debit' ? $transaction->amount_minor : null,
                'credit_minor' => $transaction->direction === 'credit' ? $transaction->amount_minor : null,
                'balance_minor' => $balance,
                'reconciliation_status' => $transaction->reconciliation_status,
            ];
        }

        $reconciliationStatus = $transactions->isEmpty()
            ? 'no_activity'
            : ($transactions->every(fn ($transaction) => $transaction->reconciliation_status === 'matched')
                ? 'reconciled'
                : 'partially_reconciled');

        $statementNumber = 'OFS-'.strtoupper(substr(str_replace('-', '', $space->public_id), 0, 8))
            .'-'.$fromDate->format('Ymd').'-'.$toDate->format('Ymd')
            .'-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6));

        $statementPayload = [
            'statement_number' => $statementNumber,
            'space' => [
                'id' => $space->id,
                'public_id' => $space->public_id,
                'name' => $space->name,
                'type' => $space->type,
            ],
            'account' => [
                'id' => $account->id,
                'public_id' => $account->public_id,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'institution_name' => $account->institution_name,
                'account_reference_masked' => $account->account_reference_masked,
                'currency' => $account->currency,
            ],
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'opening_balance_minor' => $opening,
            'closing_balance_minor' => $balance,
            'total_debits_minor' => $totalDebits,
            'total_credits_minor' => $totalCredits,
            'transaction_count' => count($rows),
            'reconciliation_status' => $reconciliationStatus,
            'rows' => $rows,
        ];
        $contentHash = hash(
            'sha256',
            json_encode($statementPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $statement = FinancialSpaceGeneratedStatement::query()->create([
            'public_id' => (string) Str::uuid(),
            'statement_number' => $statementNumber,
            'financial_space_id' => $space->id,
            'treasury_account_id' => $account->id,
            'generated_by_user_id' => $actor->id,
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'opening_balance_minor' => $opening,
            'closing_balance_minor' => $balance,
            'total_debits_minor' => $totalDebits,
            'total_credits_minor' => $totalCredits,
            'transaction_count' => count($rows),
            'reconciliation_status' => $reconciliationStatus,
            'content_hash' => $contentHash,
            'statement_payload' => $statementPayload,
            'generated_at' => now(),
            'summary' => [
                'space_name' => $space->name,
                'account_name' => $account->account_name,
                'institution_name' => $account->institution_name,
                'account_reference_masked' => $account->account_reference_masked,
                'currency' => $account->currency,
            ],
        ]);

        $this->auditLogger->record('financial_space.statement.generated', $actor, $statement, [
            'financial_space_id' => $space->id,
            'treasury_account_id' => $account->id,
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'content_hash' => $contentHash,
        ]);

        return [
            'statement' => $statement->fresh(),
            'space' => $space,
            'account' => $account,
            'rows' => $rows,
        ];
    }

    public function generateConsolidatedStatement(
        FinancialSpace $space,
        User $actor,
        string $from,
        string $to,
    ): array {
        $this->assertTreasurySpace($space);
        $this->assertAdministrator($space, $actor);

        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->startOfDay();
        if ($toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages([
                'to' => ['Statement end date must not be before the start date.'],
            ]);
        }

        $accounts = FinancialSpaceTreasuryAccount::query()
            ->where('financial_space_id', $space->id)
            ->where('status', 'active')
            ->orderBy('currency')
            ->orderBy('account_name')
            ->get();

        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages([
                'treasury' => ['Create at least one treasury account before issuing a consolidated statement.'],
            ]);
        }
        foreach ($accounts as $account) {
            $this->assertStatementBaseline($account, $fromDate);
        }

        $sections = [];
        $totalsByCurrency = [];
        $transactionCount = 0;
        $allReconciled = true;

        foreach ($accounts as $account) {
            $transactions = FinancialSpaceTransaction::query()
                ->where('treasury_account_id', $account->id)
                ->whereBetween('transaction_date', [
                    $fromDate->toDateString(),
                    $toDate->toDateString(),
                ])
                ->orderBy('transaction_date')
                ->orderBy('value_date')
                ->orderBy('id')
                ->get();

            $opening = $this->bookBalanceBefore($account, $fromDate);
            $balance = $opening;
            $debits = 0;
            $credits = 0;
            $rows = [];

            foreach ($transactions as $transaction) {
                if ($transaction->direction === 'credit') {
                    $balance += $transaction->amount_minor;
                    $credits += $transaction->amount_minor;
                } else {
                    $balance -= $transaction->amount_minor;
                    $debits += $transaction->amount_minor;
                }

                if (! in_array($transaction->reconciliation_status, ['matched', 'accepted_exception'], true)) {
                    $allReconciled = false;
                }

                $rows[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->account_name,
                    'currency' => $account->currency,
                    'date' => $transaction->transaction_date->toDateString(),
                    'value_date' => $transaction->value_date?->toDateString(),
                    'description' => $transaction->description,
                    'reference' => $transaction->transaction_reference,
                    'debit_minor' => $transaction->direction === 'debit' ? $transaction->amount_minor : null,
                    'credit_minor' => $transaction->direction === 'credit' ? $transaction->amount_minor : null,
                    'balance_minor' => $balance,
                    'reconciliation_status' => $transaction->reconciliation_status,
                ];
            }

            $transactionCount += count($rows);
            $currency = $account->currency;
            $totalsByCurrency[$currency] ??= [
                'opening_balance_minor' => 0,
                'closing_balance_minor' => 0,
                'total_debits_minor' => 0,
                'total_credits_minor' => 0,
                'transaction_count' => 0,
            ];
            $totalsByCurrency[$currency]['opening_balance_minor'] += $opening;
            $totalsByCurrency[$currency]['closing_balance_minor'] += $balance;
            $totalsByCurrency[$currency]['total_debits_minor'] += $debits;
            $totalsByCurrency[$currency]['total_credits_minor'] += $credits;
            $totalsByCurrency[$currency]['transaction_count'] += count($rows);

            $sections[] = [
                'account' => [
                    'id' => $account->id,
                    'public_id' => $account->public_id,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'institution_name' => $account->institution_name,
                    'account_reference_masked' => $account->account_reference_masked,
                    'currency' => $currency,
                ],
                'opening_balance_minor' => $opening,
                'closing_balance_minor' => $balance,
                'total_debits_minor' => $debits,
                'total_credits_minor' => $credits,
                'transaction_count' => count($rows),
                'rows' => $rows,
            ];
        }

        $positionByCurrency = [];
        $assets = DB::table('financial_assets')
            ->where('financial_space_id', $space->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get();
        foreach ($assets as $asset) {
            $currency = strtoupper((string) $asset->currency);
            $positionByCurrency[$currency] ??= [
                'recorded_assets_minor' => 0,
                'amount_owed_minor' => 0,
                'amount_receivable_minor' => 0,
            ];
            $positionByCurrency[$currency]['recorded_assets_minor'] += (int) $asset->value_minor;
        }

        $obligations = DB::table('financial_obligations')
            ->where('financial_space_id', $space->id)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->get();
        foreach ($obligations as $obligation) {
            $currency = strtoupper((string) $obligation->currency);
            $positionByCurrency[$currency] ??= [
                'recorded_assets_minor' => 0,
                'amount_owed_minor' => 0,
                'amount_receivable_minor' => 0,
            ];
            if ($obligation->direction === 'i_owe') {
                $positionByCurrency[$currency]['amount_owed_minor'] += (int) $obligation->outstanding_amount_minor;
            } else {
                $positionByCurrency[$currency]['amount_receivable_minor'] += (int) $obligation->outstanding_amount_minor;
            }
        }

        $currencies = array_values(array_unique([
            ...array_keys($totalsByCurrency),
            ...array_keys($positionByCurrency),
        ]));
        sort($currencies);

        $singleCurrency = count($currencies) === 1 ? $currencies[0] : null;
        $headline = $singleCurrency ? ($totalsByCurrency[$singleCurrency] ?? null) : null;
        $statementNumber = 'OFC-'.strtoupper(substr(str_replace('-', '', $space->public_id), 0, 8))
            .'-'.$fromDate->format('Ymd').'-'.$toDate->format('Ymd')
            .'-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 6));

        $payload = [
            'statement_number' => $statementNumber,
            'statement_scope' => 'consolidated',
            'space' => [
                'id' => $space->id,
                'public_id' => $space->public_id,
                'name' => $space->name,
                'type' => $space->type,
                'country' => $space->country,
                'currency' => $space->currency,
            ],
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'currencies' => $currencies,
            'totals_by_currency' => $totalsByCurrency,
            'position_by_currency' => $positionByCurrency,
            'position_snapshot_as_of' => now()->toIso8601String(),
            'sections' => $sections,
            'transaction_count' => $transactionCount,
            'reconciliation_status' => $transactionCount === 0
                ? 'no_activity'
                : ($allReconciled ? 'reconciled' : 'partially_reconciled'),
        ];
        $contentHash = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $statement = FinancialSpaceGeneratedStatement::query()->create([
            'public_id' => (string) Str::uuid(),
            'statement_number' => $statementNumber,
            'financial_space_id' => $space->id,
            'treasury_account_id' => null,
            'generated_by_user_id' => $actor->id,
            'statement_scope' => 'consolidated',
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'opening_balance_minor' => $headline['opening_balance_minor'] ?? null,
            'closing_balance_minor' => $headline['closing_balance_minor'] ?? null,
            'total_debits_minor' => $headline['total_debits_minor'] ?? 0,
            'total_credits_minor' => $headline['total_credits_minor'] ?? 0,
            'transaction_count' => $transactionCount,
            'reconciliation_status' => $payload['reconciliation_status'],
            'content_hash' => $contentHash,
            'statement_payload' => $payload,
            'generated_at' => now(),
            'summary' => [
                'space_name' => $space->name,
                'statement_scope' => 'consolidated',
                'currencies' => $currencies,
                'multi_currency' => count($currencies) > 1,
                'totals_by_currency' => $totalsByCurrency,
                'position_by_currency' => $positionByCurrency,
            ],
        ]);

        $this->auditLogger->record('financial_space.consolidated_statement.generated', $actor, $statement, [
            'financial_space_id' => $space->id,
            'period_start' => $fromDate->toDateString(),
            'period_end' => $toDate->toDateString(),
            'account_count' => $accounts->count(),
            'transaction_count' => $transactionCount,
            'currencies' => $currencies,
        ]);

        return [
            'statement' => $statement->fresh(),
            'space' => $space,
            'account' => null,
            'rows' => collect($sections)->flatMap(fn (array $section) => $section['rows'])->values()->all(),
            'sections' => $sections,
            'totals_by_currency' => $totalsByCurrency,
            'position_by_currency' => $positionByCurrency,
        ];
    }

    public function statementData(
        FinancialSpace $space,
        FinancialSpaceGeneratedStatement $statement,
        User $actor,
    ): array {
        $this->assertTreasurySpace($space);
        $this->assertMember($space, $actor);
        abort_unless($statement->financial_space_id === $space->id, 404);

        $payload = $statement->statement_payload ?? [];
        $spaceSnapshot = is_array($payload['space'] ?? null)
            ? $payload['space']
            : [
                'id' => $space->id,
                'public_id' => $space->public_id,
                'name' => $space->name,
                'type' => $space->type,
            ];

        if ($statement->statement_scope === 'consolidated') {
            return [
                'statement' => $statement,
                'space' => (object) $spaceSnapshot,
                'account' => null,
                'rows' => collect($payload['sections'] ?? [])
                    ->flatMap(fn ($section) => is_array($section['rows'] ?? null) ? $section['rows'] : [])
                    ->values()
                    ->all(),
                'sections' => is_array($payload['sections'] ?? null) ? $payload['sections'] : [],
                'totals_by_currency' => is_array($payload['totals_by_currency'] ?? null) ? $payload['totals_by_currency'] : [],
                'position_by_currency' => is_array($payload['position_by_currency'] ?? null) ? $payload['position_by_currency'] : [],
            ];
        }

        $accountCurrent = FinancialSpaceTreasuryAccount::query()->withTrashed()->findOrFail($statement->treasury_account_id);
        $accountSnapshot = is_array($payload['account'] ?? null)
            ? $payload['account']
            : [
                'id' => $accountCurrent->id,
                'public_id' => $accountCurrent->public_id,
                'account_name' => $accountCurrent->account_name,
                'account_type' => $accountCurrent->account_type,
                'institution_name' => $accountCurrent->institution_name,
                'account_reference_masked' => $accountCurrent->account_reference_masked,
                'currency' => $accountCurrent->currency,
            ];

        return [
            'statement' => $statement,
            'space' => (object) $spaceSnapshot,
            'account' => (object) $accountSnapshot,
            'rows' => is_array($payload['rows'] ?? null) ? $payload['rows'] : [],
            'sections' => [],
            'totals_by_currency' => [],
            'position_by_currency' => [],
        ];
    }

    public function generatedStatements(
        FinancialSpace $space,
        User $actor,
        ?int $accountId = null,
    ): Collection {
        $this->assertTreasurySpace($space);
        $this->assertMember($space, $actor);

        return FinancialSpaceGeneratedStatement::query()
            ->where('financial_space_id', $space->id)
            ->when($accountId, fn ($query) => $query->where('treasury_account_id', $accountId))
            ->orderByDesc('generated_at')
            ->limit(24)
            ->get();
    }

    public function renderBankStyleHtml(array $data): string
    {
        $statement = $data['statement'];
        $space = $data['space'];
        $account = $data['account'];
        $rows = $data['rows'];
        $sections = $data['sections'] ?? [];
        $totalsByCurrency = $data['totals_by_currency'] ?? [];
        $positionByCurrency = $data['position_by_currency'] ?? [];

        $money = fn (int $amount) => number_format($amount, 0, '.', ',');
        $esc = fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        $styles = '<style>'
            .'@page{size:A4;margin:15mm}body{font-family:Arial,Helvetica,sans-serif;color:#172033;font-size:11px;margin:0}'
            .'.brand{font-size:28px;font-weight:800;letter-spacing:-1px}.sub{color:#5d6678}.top{display:flex;justify-content:space-between;border-bottom:3px solid #172033;padding-bottom:14px;margin-bottom:18px}'
            .'.right{text-align:right}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}.box{border:1px solid #d8dde7;border-radius:8px;padding:11px}'
            .'.label{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280}.value{font-size:14px;font-weight:700;margin-top:3px}'
            .'h2{font-size:15px;margin:24px 0 8px}h3{font-size:13px;margin:18px 0 6px}'
            .'table{width:100%;border-collapse:collapse;margin-bottom:12px}th{background:#f3f5f8;text-align:left;padding:7px;border-bottom:1px solid #cfd5df;font-size:9px;text-transform:uppercase}'
            .'td{padding:7px;border-bottom:1px solid #e6e9ef;vertical-align:top}.num{text-align:right;white-space:nowrap}.muted{color:#7a8394;font-size:9px}'
            .'.summary{margin:10px 0 18px auto;width:330px}.summary td{border:0;padding:3px 0}.summary td:last-child{text-align:right;font-weight:700}'
            .'.currency{font-weight:700}.footer{margin-top:24px;padding-top:10px;border-top:1px solid #d8dde7;color:#6b7280;font-size:8.5px;line-height:1.5}'
            .'.empty{text-align:center;color:#6b7280;padding:24px}.page-break{page-break-before:always}'
            .'@media print{.no-print{display:none}}'
            .'</style>';

        $header = '<div class="top"><div><div class="brand">OpFin</div><div class="sub">'
            .($statement->statement_scope === 'consolidated' ? 'Consolidated Financial Space Statement' : 'Financial Space Statement')
            .'</div></div><div class="right"><div class="label">Statement number</div><div class="value">'.$esc($statement->statement_number).'</div>'
            .'<div class="sub">Generated '.$esc($statement->generated_at->format('d M Y H:i')).'</div></div></div>';

        $identity = '<div class="grid"><div class="box"><div class="label">Financial Space</div><div class="value">'.$esc($space->name).'</div>'
            .'<div class="sub">'.$esc(ucwords(str_replace('_', ' ', $space->type))).'</div></div>'
            .'<div class="box"><div class="label">Statement period</div><div class="value">'.$esc($statement->period_start->format('d M Y')).' – '.$esc($statement->period_end->format('d M Y')).'</div>'
            .'<div class="sub">'.($statement->statement_scope === 'consolidated' ? 'All treasury accounts and recorded position activity' : $esc($account->currency)).'</div></div>'
            .'<div class="box"><div class="label">Reconciliation</div><div class="value">'.$esc(ucwords(str_replace('_', ' ', $statement->reconciliation_status))).'</div>'
            .'<div class="sub">Transactions '.$esc($statement->transaction_count).'</div></div>'
            .'<div class="box"><div class="label">Integrity</div><div class="value">'.$esc(substr($statement->content_hash, 0, 16)).'…</div>'
            .'<div class="sub">Immutable issued snapshot</div></div></div>';

        if ($statement->statement_scope === 'consolidated') {
            $currencySummary = '<h2>Summary by currency</h2><table><thead><tr><th>Currency</th><th class="num">Opening</th><th class="num">Debits</th><th class="num">Credits</th><th class="num">Closing</th><th class="num">Transactions</th></tr></thead><tbody>';
            foreach ($totalsByCurrency as $currency => $totals) {
                $currencySummary .= '<tr><td class="currency">'.$esc($currency).'</td>'
                    .'<td class="num">'.$money((int) ($totals['opening_balance_minor'] ?? 0)).'</td>'
                    .'<td class="num">'.$money((int) ($totals['total_debits_minor'] ?? 0)).'</td>'
                    .'<td class="num">'.$money((int) ($totals['total_credits_minor'] ?? 0)).'</td>'
                    .'<td class="num">'.$money((int) ($totals['closing_balance_minor'] ?? 0)).'</td>'
                    .'<td class="num">'.$esc($totals['transaction_count'] ?? 0).'</td></tr>';
            }
            $currencySummary .= '</tbody></table>';

            $position = '<h2>Recorded financial position</h2><table><thead><tr><th>Currency</th><th class="num">Recorded assets</th><th class="num">Amount owed</th><th class="num">Amount receivable</th></tr></thead><tbody>';
            if ($positionByCurrency === []) {
                $position .= '<tr><td colspan="4" class="empty">No additional asset or obligation position is recorded.</td></tr>';
            } else {
                foreach ($positionByCurrency as $currency => $values) {
                    $position .= '<tr><td class="currency">'.$esc($currency).'</td>'
                        .'<td class="num">'.$money((int) ($values['recorded_assets_minor'] ?? 0)).'</td>'
                        .'<td class="num">'.$money((int) ($values['amount_owed_minor'] ?? 0)).'</td>'
                        .'<td class="num">'.$money((int) ($values['amount_receivable_minor'] ?? 0)).'</td></tr>';
                }
            }
            $position .= '</tbody></table>';

            $accountSections = '';
            foreach ($sections as $index => $section) {
                $sectionAccount = $section['account'] ?? [];
                $sectionRows = $section['rows'] ?? [];
                $body = '';
                foreach ($sectionRows as $row) {
                    $body .= '<tr>'
                        .'<td>'.$esc(CarbonImmutable::parse($row['date'])->format('d M Y')).'</td>'
                        .'<td>'.($row['value_date'] ? $esc(CarbonImmutable::parse($row['value_date'])->format('d M Y')) : '').'</td>'
                        .'<td><strong>'.$esc($row['description']).'</strong><br><span class="muted">'.$esc($row['reference']).'</span></td>'
                        .'<td class="num">'.($row['debit_minor'] !== null ? $money((int) $row['debit_minor']) : '').'</td>'
                        .'<td class="num">'.($row['credit_minor'] !== null ? $money((int) $row['credit_minor']) : '').'</td>'
                        .'<td class="num">'.$money((int) $row['balance_minor']).'</td></tr>';
                }
                if ($body === '') {
                    $body = '<tr><td colspan="6" class="empty">No transactions in this account for the statement period.</td></tr>';
                }
                $accountSections .= '<h2>'.($index > 0 ? '' : '').$esc($sectionAccount['account_name'] ?? 'Treasury account').' · '.$esc($sectionAccount['currency'] ?? '').'</h2>'
                    .'<div class="sub">'.$esc($sectionAccount['institution_name'] ?? '').' '.$esc($sectionAccount['account_reference_masked'] ?? '').'</div>'
                    .'<table><thead><tr><th>Date</th><th>Value date</th><th>Transaction details</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead><tbody>'.$body.'</tbody></table>'
                    .'<table class="summary"><tr><td>Opening balance</td><td>'.$money((int) ($section['opening_balance_minor'] ?? 0)).'</td></tr>'
                    .'<tr><td>Total debits</td><td>'.$money((int) ($section['total_debits_minor'] ?? 0)).'</td></tr>'
                    .'<tr><td>Total credits</td><td>'.$money((int) ($section['total_credits_minor'] ?? 0)).'</td></tr>'
                    .'<tr><td>Closing balance</td><td>'.$money((int) ($section['closing_balance_minor'] ?? 0)).'</td></tr></table>';
            }

            return '<!doctype html><html><head><meta charset="utf-8"><title>'.$esc($statement->statement_number).'</title>'.$styles.'</head><body>'
                .$header.$identity.$currencySummary.$position.$accountSections
                .'<div class="footer">This is a system-generated OpFin consolidated Financial Space statement. It combines all recorded OpFin treasury-account activity for the selected period and a snapshot of recorded Financial Space assets/obligations. '
                .'Currencies are reported separately; OpFin does not invent FX conversions. It is not a bank, broker or custodian-issued statement. Imported external statements remain separate reconciliation evidence. '
                .'Statement integrity reference: '.$esc($statement->content_hash).'.</div></body></html>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'
                .'<td>'.$esc(CarbonImmutable::parse($row['date'])->format('d M Y')).'</td>'
                .'<td>'.($row['value_date'] ? $esc(CarbonImmutable::parse($row['value_date'])->format('d M Y')) : '').'</td>'
                .'<td><strong>'.$esc($row['description']).'</strong><br><span class="muted">'.$esc($row['reference']).'</span></td>'
                .'<td class="num">'.($row['debit_minor'] !== null ? $money((int) $row['debit_minor']) : '').'</td>'
                .'<td class="num">'.($row['credit_minor'] !== null ? $money((int) $row['credit_minor']) : '').'</td>'
                .'<td class="num">'.$money((int) $row['balance_minor']).'</td></tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="6" class="empty">No transactions in this statement period.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.$esc($statement->statement_number).'</title>'.$styles.'</head><body>'
            .$header.$identity
            .'<div class="box"><div class="label">Account</div><div class="value">'.$esc($account->account_name).'</div>'
            .'<div class="sub">'.$esc($account->institution_name).' '.$esc($account->account_reference_masked).'</div></div>'
            .'<h2>Account activity</h2><table><thead><tr><th>Date</th><th>Value date</th><th>Transaction details</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>'
            .'<tbody>'.$body.'</tbody></table>'
            .'<table class="summary"><tr><td>Opening balance</td><td>'.$money((int) $statement->opening_balance_minor).'</td></tr>'
            .'<tr><td>Total debits</td><td>'.$money((int) $statement->total_debits_minor).'</td></tr>'
            .'<tr><td>Total credits</td><td>'.$money((int) $statement->total_credits_minor).'</td></tr>'
            .'<tr><td>Closing balance</td><td>'.$money((int) $statement->closing_balance_minor).'</td></tr></table>'
            .'<div class="footer">This is a system-generated OpFin Financial Space statement designed in bank-style format for record keeping, member reporting and reconciliation. '
            .'It is not a statement issued by '.$esc($account->institution_name ?: 'an underlying bank, custodian or payment provider').'. '
            .'Imported external statements remain separate source evidence. Amounts are shown in '.$esc($account->currency).'. '
            .'Statement integrity reference: '.$esc($statement->content_hash).'.</div></body></html>';
    }

    public function renderCsv(array $data): string
    {
        $stream = fopen('php://temp', 'r+');
        $consolidated = $data['statement']->statement_scope === 'consolidated';

        fputcsv($stream, $consolidated
            ? ['Account', 'Currency', 'Date', 'Value Date', 'Description', 'Reference', 'Debit', 'Credit', 'Balance', 'Reconciliation']
            : ['Date', 'Value Date', 'Description', 'Reference', 'Debit', 'Credit', 'Balance', 'Reconciliation']);

        foreach ($data['rows'] as $row) {
            $base = [
                $this->csvSafe($row['date']),
                $this->csvSafe($row['value_date']),
                $this->csvSafe($row['description']),
                $this->csvSafe($row['reference']),
                $row['debit_minor'],
                $row['credit_minor'],
                $row['balance_minor'],
                $this->csvSafe($row['reconciliation_status']),
            ];

            if ($consolidated) {
                fputcsv($stream, [
                    $this->csvSafe($row['account_name'] ?? ''),
                    $this->csvSafe($row['currency'] ?? ''),
                    ...$base,
                ]);
            } else {
                fputcsv($stream, $base);
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }

    public function accounts(FinancialSpace $space, User $actor): Collection
    {
        $this->assertMember($space, $actor);

        return FinancialSpaceTreasuryAccount::query()
            ->where('financial_space_id', $space->id)
            ->where('status', 'active')
            ->orderBy('account_name')
            ->get();
    }

    public function imports(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        User $actor,
    ): Collection {
        $this->assertTreasurySpace($space);
        $this->assertMember($space, $actor);
        $this->assertAccount($space, $account);

        return FinancialSpaceStatementImport::query()
            ->where('treasury_account_id', $account->id)
            ->orderByDesc('created_at')
            ->limit(24)
            ->get();
    }

    public function transactions(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        User $actor,
        ?string $from = null,
        ?string $to = null,
    ): Collection {
        $this->assertMember($space, $actor);
        $this->assertAccount($space, $account);

        return FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $account->id)
            ->when($from, fn ($query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('transaction_date', '<=', $to))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    private function refreshImportState(FinancialSpaceStatementImport $import): void
    {
        $matchedTotal = FinancialSpaceStatementRow::query()
            ->where('statement_import_id', $import->id)
            ->where('reconciliation_status', 'matched')
            ->count();
        $resolvedRows = FinancialSpaceStatementRow::query()
            ->where('statement_import_id', $import->id)
            ->where('reconciliation_status', 'resolved')
            ->count();
        $unresolvedRows = FinancialSpaceStatementRow::query()
            ->where('statement_import_id', $import->id)
            ->whereNotIn('reconciliation_status', ['matched', 'resolved'])
            ->get();

        foreach ($unresolvedRows as $row) {
            $ranked = $this->rankedCandidateTransactions($row);
            $top = $ranked->first();
            $suggestions = $ranked
                ->take(3)
                ->map(fn (array $candidate) => $this->candidatePresentation($candidate))
                ->values()
                ->all();
            $topScore = (int) ($top['score'] ?? 0);

            $row->update([
                'exception_type' => $top && $topScore >= 70
                    ? 'suggested_match'
                    : ($ranked->count() > 1 ? 'ambiguous_match' : 'missing_opfin_transaction'),
                'match_confidence_percent' => $top && $topScore >= 70 ? $topScore : null,
                'suggested_matches' => $suggestions ?: null,
            ]);
        }
        $unresolvedRows = FinancialSpaceStatementRow::query()
            ->where('statement_import_id', $import->id)
            ->whereNotIn('reconciliation_status', ['matched', 'resolved'])
            ->get();

        $suggestedTransactionIds = $unresolvedRows
            ->flatMap(fn (FinancialSpaceStatementRow $row) => collect($row->suggested_matches ?? [])
                ->pluck('transaction_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $unmatchedBookTransactions = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $import->treasury_account_id)
            ->whereBetween('transaction_date', [$import->period_start, $import->period_end])
            ->where('reconciliation_status', 'unreconciled')
            ->when($suggestedTransactionIds !== [], fn ($query) => $query->whereNotIn('id', $suggestedTransactionIds))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $todos = [];
        foreach ($unresolvedRows as $row) {
            $todos[] = [
                'type' => 'statement_row',
                'row_id' => $row->id,
                'exception_type' => $row->exception_type,
                'date' => $row->transaction_date?->toDateString(),
                'description' => $row->description,
                'amount_minor' => $row->amount_minor,
                'direction' => $row->direction,
                'suggested_matches' => $row->suggested_matches ?? [],
                'allowed_actions' => [
                    'match_transaction',
                    'create_book_entry',
                    'mark_external_only',
                    'mark_duplicate',
                ],
            ];
        }

        foreach ($unmatchedBookTransactions as $transaction) {
            $todos[] = [
                'type' => 'book_transaction',
                'transaction_id' => $transaction->id,
                'date' => $transaction->transaction_date?->toDateString(),
                'description' => $transaction->description,
                'amount_minor' => $transaction->amount_minor,
                'direction' => $transaction->direction,
                'allowed_actions' => ['accept_book_only'],
            ];
        }

        $summary = $import->summary ?? [];
        $variance = (int) ($summary['closing_balance_variance_minor'] ?? 0);
        if ($variance !== 0 && empty($summary['accepted_closing_balance_variance'])) {
            $todos[] = [
                'type' => 'balance_variance',
                'amount_minor' => $variance,
                'allowed_actions' => ['accept_balance_variance'],
            ];
        }

        $summary['statement_matched_count'] = $matchedTotal;
        $summary['accepted_statement_exception_count'] = $resolvedRows;
        $summary['unresolved_statement_row_count'] = $unresolvedRows->count();
        $summary['unmatched_opfin_transaction_count'] = $unmatchedBookTransactions->count();
        $summary['reconciled_at'] = now()->toIso8601String();

        $import->update([
            'matched_count' => $matchedTotal,
            'exception_count' => $unresolvedRows->count() + $unmatchedBookTransactions->count(),
            'status' => $todos === [] ? 'ready_for_confirmation' : 'review_required',
            'confirmation_status' => $todos === [] ? 'ready' : 'not_ready',
            'summary' => $summary,
            'review_todos' => $todos,
        ]);
    }

    private function rankedCandidateTransactions(FinancialSpaceStatementRow $row): Collection
    {
        $date = CarbonImmutable::parse($row->transaction_date);
        $candidates = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $row->treasury_account_id)
            ->where('currency', $row->currency)
            ->where('direction', $row->direction)
            ->where('amount_minor', $row->amount_minor)
            ->where('reconciliation_status', 'unreconciled')
            ->whereBetween('transaction_date', [
                $date->subDays(7)->toDateString(),
                $date->addDays(7)->toDateString(),
            ])
            ->get();

        return $candidates
            ->map(function (FinancialSpaceTransaction $transaction) use ($row, $date) {
                $score = 60;
                $method = 'amount_direction_window';

                if ($row->statement_reference
                    && $transaction->transaction_reference
                    && $this->sameReference($row->statement_reference, $transaction->transaction_reference)) {
                    $score += 35;
                    $method = 'exact_reference';
                }

                $days = (int) abs($date->diffInDays(CarbonImmutable::parse($transaction->transaction_date)));
                $score += match (true) {
                    $days === 0 => 20,
                    $days <= 1 => 15,
                    $days <= 3 => 10,
                    default => 5,
                };

                $descriptionSimilarity = $this->descriptionSimilarity(
                    $row->description,
                    $transaction->description
                );
                $score += (int) round($descriptionSimilarity * 15);
                $score = min(100, $score);

                if ($method !== 'exact_reference' && $score >= 90) {
                    $method = 'amount_date_description';
                }

                return [
                    'transaction' => $transaction,
                    'score' => $score,
                    'method' => $method,
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    private function candidatePresentation(array $candidate): array
    {
        /** @var FinancialSpaceTransaction $transaction */
        $transaction = $candidate['transaction'];

        return [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->transaction_reference,
            'date' => $transaction->transaction_date?->toDateString(),
            'description' => $transaction->description,
            'amount_minor' => $transaction->amount_minor,
            'direction' => $transaction->direction,
            'confidence_percent' => (int) $candidate['score'],
            'match_method' => $candidate['method'],
        ];
    }

    private function descriptionSimilarity(string $left, string $right): float
    {
        $tokens = function (string $value): array {
            $normalised = strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $value) ?? '');
            return array_values(array_unique(array_filter(
                preg_split('/\s+/', $normalised) ?: [],
                fn ($token) => strlen($token) >= 3
            )));
        };

        $leftTokens = $tokens($left);
        $rightTokens = $tokens($right);
        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique([...$leftTokens, ...$rightTokens]));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function candidateTransactions(FinancialSpaceStatementRow $row): Collection
    {
        return $this->rankedCandidateTransactions($row)
            ->map(fn (array $candidate) => $candidate['transaction'])
            ->values();
    }

    private function sameReference(string $left, string $right): bool
    {
        $normalise = fn ($value) => strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($value)) ?? '');

        return $normalise($left) !== '' && $normalise($left) === $normalise($right);
    }

    private function bookBalanceBefore(FinancialSpaceTreasuryAccount $account, CarbonImmutable $from): int
    {
        $balance = (int) $account->opening_balance_minor;
        $transactions = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $account->id)
            ->whereDate('transaction_date', '<', $from->toDateString())
            ->get();

        foreach ($transactions as $transaction) {
            $balance += $transaction->direction === 'credit'
                ? $transaction->amount_minor
                : -$transaction->amount_minor;
        }

        return $balance;
    }

    private function bookBalanceAsOf(FinancialSpaceTreasuryAccount $account, CarbonImmutable $date): int
    {
        $balance = (int) $account->opening_balance_minor;
        $transactions = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $account->id)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->get();

        foreach ($transactions as $transaction) {
            $balance += $transaction->direction === 'credit'
                ? $transaction->amount_minor
                : -$transaction->amount_minor;
        }

        return $balance;
    }

    private function refreshAccountBalance(FinancialSpaceTreasuryAccount $account): void
    {
        $account->update([
            'current_balance_minor' => $this->bookBalanceAsOf($account, CarbonImmutable::today()->addYears(100)),
            'current_balance_as_of' => now()->toDateString(),
        ]);
    }

    private function parseCsv(string $contents): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function normaliseCsvRecord(
        array $values,
        array $indexed,
        array $mapping,
        string $currency,
        int $minorUnitExponent,
        int $lineNumber,
    ): array {
        $get = function (string $key) use ($mapping, $indexed, $values) {
            $header = $this->normaliseHeader((string) ($mapping[$key] ?? ''));
            if ($header === '' || ! array_key_exists($header, $indexed)) {
                return null;
            }

            return isset($values[$indexed[$header]]) ? trim((string) $values[$indexed[$header]]) : null;
        };

        $dateRaw = $get('date');
        $description = $get('description');
        if (! $dateRaw || ! $description) {
            throw new InvalidArgumentException("Statement row {$lineNumber} requires date and description values.");
        }

        $debitRaw = $get('debit');
        $creditRaw = $get('credit');
        $amountRaw = $get('amount');
        $directionRaw = strtolower((string) $get('direction'));

        $debitMinor = $debitRaw !== null && trim($debitRaw) !== ''
            ? $this->toMinor($debitRaw, $minorUnitExponent)
            : 0;
        $creditMinor = $creditRaw !== null && trim($creditRaw) !== ''
            ? $this->toMinor($creditRaw, $minorUnitExponent)
            : 0;

        if ($debitMinor > 0 && $creditMinor > 0) {
            throw new InvalidArgumentException(
                "Statement row {$lineNumber} cannot contain both a non-zero debit and credit amount."
            );
        }

        if ($debitMinor > 0) {
            $direction = 'debit';
            $amount = $debitMinor;
        } elseif ($creditMinor > 0) {
            $direction = 'credit';
            $amount = $creditMinor;
        } elseif ($amountRaw !== null && trim($amountRaw) !== '' && in_array($directionRaw, ['debit', 'credit'], true)) {
            $direction = $directionRaw;
            $amount = $this->toMinor($amountRaw, $minorUnitExponent);
        } else {
            throw new InvalidArgumentException("Statement row {$lineNumber} requires debit/credit columns or amount plus direction.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Statement row {$lineNumber} requires a positive transaction amount.");
        }

        return [
            'statement_reference' => $get('reference'),
            'transaction_date' => CarbonImmutable::parse($dateRaw)->toDateString(),
            'value_date' => $get('value_date') ? CarbonImmutable::parse($get('value_date'))->toDateString() : null,
            'description' => $description,
            'direction' => $direction,
            'amount_minor' => $amount,
            'currency' => strtoupper($currency),
            'running_balance_minor' => $get('balance') !== null && $get('balance') !== ''
                ? $this->signedMinor($get('balance'), $minorUnitExponent)
                : null,
            'raw_payload' => array_combine(
                array_values(array_keys($indexed)),
                array_map(fn ($header) => $values[$indexed[$header]] ?? null, array_keys($indexed))
            ) ?: [],
        ];
    }

    private function assertMapping(array $mapping): void
    {
        foreach (['date', 'description'] as $required) {
            if (empty($mapping[$required])) {
                throw ValidationException::withMessages([
                    'mapping.'.$required => ["A {$required} column mapping is required."],
                ]);
            }
        }

        $hasSplit = ! empty($mapping['debit']) || ! empty($mapping['credit']);
        $hasAmountDirection = ! empty($mapping['amount']) && ! empty($mapping['direction']);
        if (! $hasSplit && ! $hasAmountDirection) {
            throw ValidationException::withMessages([
                'mapping' => ['Map debit/credit columns, or map both amount and direction.'],
            ]);
        }
    }

    private function normaliseHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $header) ?? '', '_'));
    }

    private function blankRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function toMinor(string $value, int $exponent): int
    {
        return abs($this->parseMinorUnits($value, $exponent));
    }

    private function signedMinor(string $value, int $exponent): int
    {
        return $this->parseMinorUnits($value, $exponent);
    }

    private function parseMinorUnits(string $value, int $exponent): int
    {
        $exponent = max(0, min(4, $exponent));
        $raw = trim(str_replace([',', ' '], '', $value));
        if ($raw === '') {
            return 0;
        }

        $parenthesisedNegative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        if ($parenthesisedNegative) {
            $raw = substr($raw, 1, -1);
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $clean)) {
            throw new InvalidArgumentException("Invalid monetary amount: {$value}");
        }

        $negative = $parenthesisedNegative || str_starts_with($clean, '-');
        $unsigned = ltrim($clean, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen($fraction) > $exponent) {
            $discarded = substr($fraction, $exponent);
            if (trim($discarded, '0') !== '') {
                throw new InvalidArgumentException(
                    "Monetary amount {$value} has more precision than the configured minor-unit exponent {$exponent}."
                );
            }
            $fraction = substr($fraction, 0, $exponent);
        }

        $fraction = str_pad($fraction, $exponent, '0');
        $digits = ltrim($whole.$fraction, '0');
        $digits = $digits === '' ? '0' : $digits;

        if (strlen($digits) > 16) {
            throw new InvalidArgumentException('Monetary amount exceeds the supported treasury range.');
        }

        $minor = (int) $digits;
        if ($minor > 9000000000000000) {
            throw new InvalidArgumentException('Monetary amount exceeds the supported treasury range.');
        }

        return $negative ? -$minor : $minor;
    }

    private function assertStatementBaseline(
        FinancialSpaceTreasuryAccount $account,
        CarbonImmutable $fromDate,
    ): void {
        if ($account->balance_as_of
            && $fromDate->lessThan(CarbonImmutable::parse($account->balance_as_of))) {
            throw ValidationException::withMessages([
                'from' => [
                    'Statement start date cannot be before the opening-balance baseline for '.$account->account_name.'.',
                ],
            ]);
        }
    }

    private function csvSafe(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && preg_match('/^[=+\-@]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }

    private function maskedReference(?string $reference): ?string
    {
        if ($reference === null || trim($reference) === '') {
            return null;
        }

        $reference = preg_replace('/\s+/', '', trim($reference)) ?? trim($reference);
        if (strlen($reference) <= 4) {
            return str_repeat('•', max(0, strlen($reference) - 1)).substr($reference, -1);
        }

        return str_repeat('•', max(4, strlen($reference) - 4)).substr($reference, -4);
    }

    private function assertTreasurySpace(FinancialSpace $space): void
    {
        abort_unless(
            in_array($space->type, ['savings_group', 'investment_club', 'sacco', 'business', 'investment_fund'], true),
            422,
            'Treasury statements are available for group and organisation Financial Spaces.'
        );
    }

    private function assertAccount(FinancialSpace $space, FinancialSpaceTreasuryAccount $account): void
    {
        abort_unless($account->financial_space_id === $space->id && $account->status === 'active', 404);
    }

    private function assertMember(FinancialSpace $space, User $actor): void
    {
        abort_unless(
            DB::table('financial_space_memberships')
                ->where('financial_space_id', $space->id)
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists(),
            403
        );
    }

    private function assertAdministrator(FinancialSpace $space, User $actor): void
    {
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');

        abort_unless(
            in_array($role, ['owner','administrator','admin','chairperson','treasurer','secretary','director','manager'], true),
            403
        );
    }
}
