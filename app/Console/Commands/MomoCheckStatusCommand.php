<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\MtnMomoService;

class MomoCheckStatusCommand extends Command
{
    protected $signature = 'momo:check 
                            {type : collection|disbursement} 
                            {referenceId : The transaction reference ID}';

    protected $description = 'Check MTN MoMo transaction status (Collection or Disbursement)';

    public function handle()
    {
        $type = strtolower($this->argument('type'));
        $referenceId = $this->argument('referenceId');

        if (!in_array($type, ['collection', 'disbursement'])) {
            $this->error("Invalid type: must be 'collection' or 'disbursement'");
            return Command::FAILURE;
        }

        $this->info("🔍 Checking {$type} status for Reference ID: {$referenceId} ...");

        try {
            $momo = new MtnMomoService();

            $status = $momo->checkStatus($type, $referenceId);

            $this->line('');
            $this->info("✅ Status Response:");
            $this->line(json_encode($status, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
