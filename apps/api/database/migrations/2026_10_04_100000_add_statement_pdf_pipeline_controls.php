<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fi_statements', function (Blueprint $t): void {
            // Analysis inputs (currency, exponent, declared balances) kept for asynchronous PDF analysis.
            $t->longText('pipeline_cipher')->nullable();
            // Non-sensitive assurance codes only, so listings never decrypt evidence.
            $t->json('assurance')->nullable();
            $t->timestamp('analysed_at')->nullable();
            // Bounded retries: an analysis interrupted twice (for example by a worker timeout) is closed as unreadable.
            $t->unsignedTinyInteger('analysis_attempts')->default(0);
            $t->date('legal_hold_until')->nullable();
            $t->timestamp('original_purged_at')->nullable();
            $t->index(['status', 'updated_at']);
        });

        // PDFs stored before the pipeline existed are analysed by the scheduled pending-analysis sweep.
        DB::table('fi_statements')->where('status', 'quarantined_parser_required')
            ->update(['status' => 'queued_for_analysis', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('fi_statements')->whereIn('status', ['queued_for_analysis', 'analysing'])
            ->update(['status' => 'quarantined_parser_required', 'updated_at' => now()]);

        Schema::table('fi_statements', function (Blueprint $t): void {
            $t->dropIndex(['status', 'updated_at']);
            $t->dropColumn(['pipeline_cipher', 'assurance', 'analysed_at', 'analysis_attempts', 'legal_hold_until', 'original_purged_at']);
        });
    }
};
