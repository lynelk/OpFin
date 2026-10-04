<?php

namespace App\Console\Commands;

use App\Jobs\AnalyseStatementDocument;
use App\Services\FinancialIntelligence\PdfStatementInspector;
use App\Services\FinancialIntelligence\StatementDocumentAnalyser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalysePendingStatements extends Command
{
    protected $signature = 'opfin:statements:analyse-pending {--limit=100}';

    protected $description = 'Queue statement PDFs awaiting analysis and recover analyses interrupted by a worker restart';

    public function handle(StatementDocumentAnalyser $analyser): int
    {
        $stale = now()->subMinutes(15);
        $interrupted = DB::table('fi_statements')->where('status', 'analysing')->where('updated_at', '<', $stale)->get(['id', 'financial_space_id', 'analysis_attempts']);
        foreach ($interrupted as $row) {
            if ((int) $row->analysis_attempts >= StatementDocumentAnalyser::MAX_ATTEMPTS) {
                $analyser->finish((int) $row->id, (int) $row->financial_space_id, PdfStatementInspector::UNREADABLE, null, []);
            } else {
                DB::table('fi_statements')->where('id', $row->id)->where('status', 'analysing')
                    ->update(['status' => 'queued_for_analysis', 'updated_at' => now()->subMinutes(10)]);
            }
        }

        $ids = DB::table('fi_statements')->where('status', 'queued_for_analysis')->where('updated_at', '<', now()->subMinutes(5))
            ->whereNull('revoked_at')->whereNull('original_purged_at')->orderBy('id')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        foreach ($ids as $id) {
            DB::table('fi_statements')->where('id', $id)->update(['updated_at' => now()]);
            AnalyseStatementDocument::dispatch((int) $id);
        }
        $this->info("Queued {$ids->count()} statement(s); recovered {$interrupted->count()} interrupted analysis run(s).");

        return self::SUCCESS;
    }
}
