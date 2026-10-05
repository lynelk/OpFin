<?php

namespace App\Jobs;

use App\Services\FinancialIntelligence\StatementDocumentAnalyser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Analyses one uploaded statement PDF. The payload is only the statement id. Retries come from the
 * scheduled opfin:statements:analyse-pending sweep, which bounds them per statement.
 */
class AnalyseStatementDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 70;

    public function __construct(public readonly int $statementId) {}

    public function handle(StatementDocumentAnalyser $analyser): void
    {
        $analyser->analyse($this->statementId);
    }
}
