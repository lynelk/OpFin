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

        return FinancialSpaceTreasuryAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'financial_space_id' => $space->id,
            'account_name' => trim($data['account_name']),
            'account_type' => $data['account_type'],
            'institution_name' => $data['institution_name'] ?? null,
            'account_reference_masked' => $this->maskedReference($data['account_reference'] ?? null),
            'currency' => strtoupper($data['currency'] ?? $space->currency),
            'opening_balance_minor' => (int) ($data['opening_balance_minor'] ?? 0),
            'current_balance_minor' => (int) ($data['opening_balance_minor'] ?? 0),
            'balance_as_of' => $data['balance_as_of'] ?? null,
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

        $sourceType = $data['source_type'] ?? 'manual';
        $sourceReference = $data['source_reference'] ?? null;
        if ($sourceReference === null || trim((string) $sourceReference) === '') {
            $sourceReference = 'manual:'.Str::uuid();
        }

        return DB::transaction(function () use ($space, $account, $actor, $data, $sourceType, $sourceReference) {
            $transaction = FinancialSpaceTransaction::query()->create([
                'public_id' => (string) Str::uuid(),
                'financial_space_id' => $space->id,
                'treasury_account_id' => $account->id,
                'created_by_user_id' => $actor->id,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? 'other',
                'direction' => $data['direction'],
                'amount_minor' => (int) $data['amount_minor'],
                'currency' => $account->currency,
                'description' => trim($data['description']),
                'counterparty_name' => $data['counterparty_name'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'value_date' => $data['value_date'] ?? null,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'reconciliation_status' => 'unreconciled',
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->refreshAccountBalance($account);

            $this->auditLogger->record('financial_space.treasury.transaction_recorded', $actor, $transaction, [
                'financial_space_id' => $space->id,
                'treasury_account_id' => $account->id,
                'direction' => $transaction->direction,
                'amount_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
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

        return DB::transaction(function () use (
            $space,
            $account,
            $actor,
            $file,
            $sourceHash,
            $mapping,
            $rows,
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
        $this->assertAdministrator($space, $actor);
        abort_unless($import->financial_space_id === $space->id, 404);

        return DB::transaction(function () use ($space, $import, $actor) {
            $matched = 0;
            $exceptions = 0;

            foreach ($import->rows()->where('reconciliation_status', '!=', 'matched')->orderBy('transaction_date')->orderBy('id')->get() as $row) {
                $candidates = $this->candidateTransactions($row);

                if ($candidates->count() === 1) {
                    $transaction = $candidates->first();
                    $method = $row->statement_reference
                        && $transaction->transaction_reference
                        && $this->sameReference($row->statement_reference, $transaction->transaction_reference)
                        ? 'exact_reference'
                        : 'unique_amount_date_direction';

                    $row->update([
                        'matched_transaction_id' => $transaction->id,
                        'reconciliation_status' => 'matched',
                        'exception_type' => null,
                        'match_method' => $method,
                        'match_confidence_percent' => $method === 'exact_reference' ? 100 : 90,
                        'notes' => 'Matched to one OpFin treasury transaction.',
                    ]);
                    $transaction->update([
                        'reconciliation_status' => 'matched',
                        'reconciled_at' => now(),
                    ]);
                    $matched++;
                    continue;
                }

                if ($candidates->count() > 1) {
                    $row->update([
                        'reconciliation_status' => 'exception',
                        'exception_type' => 'ambiguous_match',
                        'match_method' => null,
                        'match_confidence_percent' => null,
                        'notes' => 'More than one OpFin transaction could match this statement row.',
                    ]);
                    $exceptions++;
                    continue;
                }

                $row->update([
                    'reconciliation_status' => 'exception',
                    'exception_type' => 'missing_opfin_transaction',
                    'match_method' => null,
                    'match_confidence_percent' => null,
                    'notes' => 'No matching OpFin treasury transaction was found.',
                ]);
                $exceptions++;
            }

            $unmatchedBook = FinancialSpaceTransaction::query()
                ->where('treasury_account_id', $import->treasury_account_id)
                ->whereBetween('transaction_date', [$import->period_start, $import->period_end])
                ->where('reconciliation_status', 'unreconciled')
                ->count();

            $import->update([
                'matched_count' => $matched,
                'exception_count' => $exceptions + $unmatchedBook,
                'status' => ($exceptions + $unmatchedBook) === 0 ? 'reconciled' : 'exceptions',
                'summary' => [
                    ...($import->summary ?? []),
                    'statement_matched_count' => $matched,
                    'statement_exception_count' => $exceptions,
                    'unmatched_opfin_transaction_count' => $unmatchedBook,
                    'reconciled_at' => now()->toIso8601String(),
                ],
            ]);

            if ($import->closing_balance_minor !== null) {
                $account = $import->account;
                $bookClosing = $this->bookBalanceAsOf($account, CarbonImmutable::parse($import->period_end));
                $summary = $import->fresh()->summary ?? [];
                $summary['book_closing_balance_minor'] = $bookClosing;
                $summary['statement_closing_balance_minor'] = $import->closing_balance_minor;
                $summary['closing_balance_variance_minor'] = $bookClosing - $import->closing_balance_minor;
                $import->update(['summary' => $summary]);
            }

            $this->auditLogger->record('financial_space.statement_import.reconciled', $actor, $import, [
                'financial_space_id' => $space->id,
                'matched_count' => $matched,
                'exception_count' => $exceptions,
                'unmatched_opfin_transaction_count' => $unmatchedBook,
            ]);

            return $import->fresh(['rows', 'account']);
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
        $this->assertMember($space, $actor);
        $this->assertAccount($space, $account);

        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->startOfDay();
        if ($toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages(['to' => ['Statement end date must not be before the start date.']]);
        }

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
            .'-'.str_pad((string) ($account->id % 10000), 4, '0', STR_PAD_LEFT);

        $canonical = [
            'statement_number' => $statementNumber,
            'financial_space_id' => $space->id,
            'treasury_account_id' => $account->id,
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
        $contentHash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        $statement = FinancialSpaceGeneratedStatement::query()->firstOrNew([
            'statement_number' => $statementNumber,
        ]);
        if (! $statement->exists) {
            $statement->public_id = (string) Str::uuid();
        }
        $statement->fill([
                'financial_space_id' => $space->id,
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
                'generated_at' => now(),
                'summary' => [
                    'space_name' => $space->name,
                    'account_name' => $account->account_name,
                    'institution_name' => $account->institution_name,
                    'account_reference_masked' => $account->account_reference_masked,
                    'currency' => $account->currency,
                ],
        ]);
        $statement->save();

        return [
            'statement' => $statement->fresh(),
            'space' => $space,
            'account' => $account,
            'rows' => $rows,
        ];
    }

    public function renderBankStyleHtml(array $data): string
    {
        $statement = $data['statement'];
        $space = $data['space'];
        $account = $data['account'];
        $rows = $data['rows'];

        $money = fn (int $amount) => number_format($amount, 0, '.', ',');
        $esc = fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>'
                .'<td>'.$esc(CarbonImmutable::parse($row['date'])->format('d M Y')).'</td>'
                .'<td>'.($row['value_date'] ? $esc(CarbonImmutable::parse($row['value_date'])->format('d M Y')) : '').'</td>'
                .'<td><strong>'.$esc($row['description']).'</strong><br><span class="muted">'.$esc($row['reference']).'</span></td>'
                .'<td class="num">'.($row['debit_minor'] !== null ? $money($row['debit_minor']) : '').'</td>'
                .'<td class="num">'.($row['credit_minor'] !== null ? $money($row['credit_minor']) : '').'</td>'
                .'<td class="num">'.$money($row['balance_minor']).'</td>'
                .'</tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="6" class="empty">No transactions in this statement period.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><title>'.$esc($statement->statement_number).'</title>'
            .'<style>'
            .'@page{size:A4;margin:16mm}body{font-family:Arial,Helvetica,sans-serif;color:#172033;font-size:12px;margin:0}'
            .'.brand{font-size:26px;font-weight:800;letter-spacing:-1px}.sub{color:#5d6678}.top{display:flex;justify-content:space-between;border-bottom:3px solid #172033;padding-bottom:14px;margin-bottom:18px}'
            .'.right{text-align:right}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}.box{border:1px solid #d8dde7;border-radius:8px;padding:12px}'
            .'.label{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280}.value{font-size:15px;font-weight:700;margin-top:3px}'
            .'table{width:100%;border-collapse:collapse}th{background:#f3f5f8;text-align:left;padding:8px;border-bottom:1px solid #cfd5df;font-size:10px;text-transform:uppercase}'
            .'td{padding:8px;border-bottom:1px solid #e6e9ef;vertical-align:top}.num{text-align:right;white-space:nowrap}.muted{color:#7a8394;font-size:10px}'
            .'.summary{margin-top:18px;margin-left:auto;width:320px}.summary td{border:0;padding:4px 0}.summary td:last-child{text-align:right;font-weight:700}'
            .'.footer{margin-top:28px;padding-top:12px;border-top:1px solid #d8dde7;color:#6b7280;font-size:9px;line-height:1.5}.empty{text-align:center;color:#6b7280;padding:30px}'
            .'@media print{.no-print{display:none}}'
            .'</style></head><body>'
            .'<div class="top"><div><div class="brand">OpFin</div><div class="sub">Financial Space Statement</div></div>'
            .'<div class="right"><div class="label">Statement number</div><div class="value">'.$esc($statement->statement_number).'</div>'
            .'<div class="sub">Generated '.$esc($statement->generated_at->format('d M Y H:i')).'</div></div></div>'
            .'<div class="grid"><div class="box"><div class="label">Account holder</div><div class="value">'.$esc($space->name).'</div>'
            .'<div class="sub">'.$esc(ucwords(str_replace('_', ' ', $space->type))).'</div></div>'
            .'<div class="box"><div class="label">Account</div><div class="value">'.$esc($account->account_name).'</div>'
            .'<div class="sub">'.$esc($account->institution_name).' '.$esc($account->account_reference_masked).'</div></div>'
            .'<div class="box"><div class="label">Statement period</div><div class="value">'.$esc($statement->period_start->format('d M Y')).' – '.$esc($statement->period_end->format('d M Y')).'</div>'
            .'<div class="sub">'.$esc($account->currency).'</div></div>'
            .'<div class="box"><div class="label">Reconciliation</div><div class="value">'.$esc(ucwords(str_replace('_', ' ', $statement->reconciliation_status))).'</div>'
            .'<div class="sub">Content hash '.$esc(substr($statement->content_hash, 0, 16)).'…</div></div></div>'
            .'<table><thead><tr><th>Date</th><th>Value date</th><th>Transaction details</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>'
            .'<tbody>'.$body.'</tbody></table>'
            .'<table class="summary"><tr><td>Opening balance</td><td>'.$money($statement->opening_balance_minor).'</td></tr>'
            .'<tr><td>Total debits</td><td>'.$money($statement->total_debits_minor).'</td></tr>'
            .'<tr><td>Total credits</td><td>'.$money($statement->total_credits_minor).'</td></tr>'
            .'<tr><td>Closing balance</td><td>'.$money($statement->closing_balance_minor).'</td></tr></table>'
            .'<div class="footer">This is a system-generated OpFin Financial Space statement designed in bank-style format for record keeping, member reporting and reconciliation. '
            .'It is not a statement issued by '.$esc($account->institution_name ?: 'an underlying bank, custodian or payment provider').'. '
            .'Imported external statements remain separate source evidence. Amounts are shown in '.$esc($account->currency).'. '
            .'Statement integrity reference: '.$esc($statement->content_hash).'.</div>'
            .'</body></html>';
    }

    public function renderCsv(array $data): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Date', 'Value Date', 'Description', 'Reference', 'Debit', 'Credit', 'Balance', 'Reconciliation']);
        foreach ($data['rows'] as $row) {
            fputcsv($stream, [
                $row['date'],
                $row['value_date'],
                $row['description'],
                $row['reference'],
                $row['debit_minor'],
                $row['credit_minor'],
                $row['balance_minor'],
                $row['reconciliation_status'],
            ]);
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

    private function candidateTransactions(FinancialSpaceStatementRow $row): Collection
    {
        $query = FinancialSpaceTransaction::query()
            ->where('treasury_account_id', $row->treasury_account_id)
            ->where('currency', $row->currency)
            ->where('direction', $row->direction)
            ->where('amount_minor', $row->amount_minor)
            ->where('reconciliation_status', 'unreconciled');

        if ($row->statement_reference) {
            $byReference = (clone $query)
                ->whereNotNull('transaction_reference')
                ->get()
                ->filter(fn ($transaction) => $this->sameReference(
                    $row->statement_reference,
                    $transaction->transaction_reference
                ))
                ->values();
            if ($byReference->isNotEmpty()) {
                return $byReference;
            }
        }

        $date = CarbonImmutable::parse($row->transaction_date);

        return $query
            ->whereBetween('transaction_date', [
                $date->subDays(3)->toDateString(),
                $date->addDays(3)->toDateString(),
            ])
            ->get();
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
            'balance_as_of' => now()->toDateString(),
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

        if ($debitRaw !== null && $this->numericAmount($debitRaw) > 0) {
            $direction = 'debit';
            $amount = $this->toMinor($debitRaw, $minorUnitExponent);
        } elseif ($creditRaw !== null && $this->numericAmount($creditRaw) > 0) {
            $direction = 'credit';
            $amount = $this->toMinor($creditRaw, $minorUnitExponent);
        } elseif ($amountRaw !== null && in_array($directionRaw, ['debit', 'credit'], true)) {
            $direction = $directionRaw;
            $amount = $this->toMinor($amountRaw, $minorUnitExponent);
        } else {
            throw new InvalidArgumentException("Statement row {$lineNumber} requires debit/credit columns or amount plus direction.");
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

    private function numericAmount(string $value): float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function toMinor(string $value, int $exponent): int
    {
        $amount = abs($this->numericAmount($value));
        $factor = 10 ** max(0, min(4, $exponent));

        return (int) round($amount * $factor);
    }

    private function signedMinor(string $value, int $exponent): int
    {
        $amount = $this->numericAmount($value);
        $factor = 10 ** max(0, min(4, $exponent));

        return (int) round($amount * $factor);
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
