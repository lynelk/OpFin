<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Models\FinancialSpace;
use App\Services\AuditLogger;
use App\Services\FinancialIntelligence\Layouts\LayoutRegistry;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LengthException;
use Throwable;

/**
 * Queue-side analysis of an uploaded statement PDF: screen, extract, check and label. Each queued
 * statement is claimed atomically, so a duplicate dispatch or a concurrent worker is a no-op.
 */
final class StatementDocumentAnalyser
{
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly PdfStatementInspector $inspector,
        private readonly PdfStatementText $text,
        private readonly LayoutRegistry $layouts,
        private readonly ExtractedStatementAnalysis $checks,
        private readonly AuditLogger $audit,
    ) {}

    /** @return string|null the resulting status, or null when the statement was not waiting for analysis */
    public function analyse(int $statementId): ?string
    {
        $claimed = DB::table('fi_statements')->where('id', $statementId)->where('status', 'queued_for_analysis')
            ->whereNull('revoked_at')->whereNull('original_purged_at')
            ->update(['status' => 'analysing', 'analysis_attempts' => DB::raw('analysis_attempts + 1'), 'updated_at' => now()]);
        if ($claimed !== 1) {
            return null;
        }
        $row = DB::table('fi_statements')->find($statementId);
        try {
            [$status, $analysis, $signals] = $this->run($row);
        } catch (Throwable $error) {
            report($error);
            [$status, $analysis, $signals] = [PdfStatementInspector::UNREADABLE, null, []];
        }
        $this->finish($statementId, (int) $row->financial_space_id, $status, $analysis, $signals);

        return $status;
    }

    /** Records an outcome for a statement this process owns (status `analysing`). */
    public function finish(int $statementId, int $spaceId, string $status, ?array $analysis, array $signals): void
    {
        DB::table('fi_statements')->where('id', $statementId)->where('status', 'analysing')->update([
            'status' => $status,
            'analysis_cipher' => $analysis !== null ? Crypt::encryptString(Values::canonical($analysis)) : null,
            'assurance' => json_encode(StatementAssurance::from($status, $analysis, $signals)),
            'analysed_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->record('intelligence.statement_analysed', null, FinancialSpace::query()->find($spaceId),
            ['statement_id' => $statementId, 'status' => $status, 'document_signals' => $signals]);
    }

    private function run(object $row): array
    {
        $bytes = Crypt::decryptString((string) Storage::disk(config('financial_intelligence.statement_disk'))->get($row->storage_path));
        $screen = $this->inspector->screen($bytes);
        if ($screen['verdict'] !== PdfStatementInspector::PARSE) {
            return [$screen['verdict'], null, []];
        }
        try {
            $pdf = $this->text->parse($bytes);
        } catch (Throwable) {
            return [PdfStatementInspector::UNREADABLE, null, $screen['signals']];
        }
        $document = $this->inspector->document($pdf);
        $signals = array_values(array_unique([...$screen['signals'], ...$document['signals']]));
        if ($document['verdict'] !== PdfStatementInspector::PARSE) {
            return [$document['verdict'], null, $signals];
        }
        try {
            $lines = $this->text->lines($pdf);
        } catch (LengthException) {
            return [PdfStatementInspector::LIMITS, null, $signals];
        }
        $input = $this->input($row);
        $issuer = (string) DB::table('fi_issuer_versions')->where('id', $row->issuer_version_id)->value('issuer_code');
        foreach ($this->layouts->for($issuer) as $adapter) {
            $extraction = $adapter->extract($lines, $input['minor_unit_exponent']);
            if ($extraction !== null) {
                $analysis = $this->checks->analyse($extraction, $adapter, $issuer, $input);
                $analysis['document'] = $document['metadata'];
                $analysis['document_signals'] = $signals;

                return ['analysed_unconfirmed', $analysis, $signals];
            }
        }

        return ['layout_not_supported', null, $signals];
    }

    private function input(object $row): array
    {
        $stored = $row->pipeline_cipher !== null
            ? json_decode(Crypt::decryptString($row->pipeline_cipher), true, 16, JSON_THROW_ON_ERROR)
            : [];
        $currency = $stored['currency'] ?? (string) DB::table('financial_spaces')->where('id', $row->financial_space_id)->value('currency');

        return ['currency' => $currency, 'minor_unit_exponent' => $stored['minor_unit_exponent'] ?? Values::exponent($currency),
            'period_start' => (string) $row->period_start, 'period_end' => (string) $row->period_end,
            'declared_opening_balance_minor' => $stored['opening_balance_minor'] ?? null,
            'declared_closing_balance_minor' => $stored['closing_balance_minor'] ?? null];
    }
}
