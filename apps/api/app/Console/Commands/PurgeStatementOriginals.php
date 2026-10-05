<?php

namespace App\Console\Commands;

use App\Models\FinancialSpace;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeStatementOriginals extends Command
{
    protected $signature = 'opfin:statements:purge-originals {--limit=500}';

    protected $description = 'Delete statement originals and their analysis once permission has ended and the retention period has passed';

    public function handle(AuditLogger $audit): int
    {
        $cutoff = now()->subDays(max(0, (int) config('financial_intelligence.statement_retention_days')));
        $today = now()->toDateString();
        $disk = Storage::disk(config('financial_intelligence.statement_disk'));
        $rows = DB::table('fi_statements')->whereNull('original_purged_at')->where('status', '!=', 'analysing')
            ->where(fn ($query) => $query->whereNull('legal_hold_until')->orWhere('legal_hold_until', '<', $today))
            ->where(fn ($query) => $query->where('revoked_at', '<', $cutoff)->orWhere('authority_expires_at', '<', $cutoff))
            ->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get(['id', 'financial_space_id', 'storage_path']);
        $purged = 0;
        foreach ($rows as $row) {
            if ($disk->exists($row->storage_path) && ! $disk->delete($row->storage_path)) {
                continue;
            }
            // The file hash, authority record and assurance codes remain as audit evidence; content does not.
            $updated = DB::table('fi_statements')->where('id', $row->id)->whereNull('original_purged_at')->update([
                'analysis_cipher' => null, 'pipeline_cipher' => null, 'original_purged_at' => now(), 'updated_at' => now(),
            ]);
            if ($updated === 1) {
                $purged++;
                $audit->record('intelligence.statement_original_purged', null, FinancialSpace::query()->find($row->financial_space_id),
                    ['statement_id' => $row->id]);
            }
        }
        $this->info("Purged {$purged} statement original(s).");

        return self::SUCCESS;
    }
}
