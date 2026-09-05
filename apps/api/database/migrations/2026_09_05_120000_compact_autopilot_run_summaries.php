<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('autopilot_runs')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Use the function form so PDO cannot treat the JSONB operator as a binding.
            DB::statement(<<<'SQL'
                UPDATE autopilot_runs
                SET summary = (summary::jsonb - 'last_run')
                WHERE summary IS NOT NULL
                  AND jsonb_typeof(summary::jsonb) = 'object'
                  AND jsonb_exists(summary::jsonb, 'last_run')
            SQL);

            return;
        }

        DB::table('autopilot_runs')
            ->whereNotNull('summary')
            ->orderBy('id')
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $run) {
                    $summary = json_decode((string) $run->summary, true);
                    if (! is_array($summary) || ! array_key_exists('last_run', $summary)) {
                        continue;
                    }

                    unset($summary['last_run']);
                    DB::table('autopilot_runs')->where('id', $run->id)->update([
                        'summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: this only removes recursively duplicated
        // previous-run snapshots. Canonical run metrics remain in their rows.
    }
};
