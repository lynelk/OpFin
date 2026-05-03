<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MtnMomoService;
use Illuminate\Support\Str;

class TestMtnMomo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     * php artisan momo:test collection 0771234567 1000
     * php artisan momo:test disbursement 0771234567 5000
     */
    protected $signature = 'momo:test {type : collection|disbursement|status} 
                                         {phone? : Recipient phone number in 256 format} 
                                         {amount? : Amount to send or request} 
                                         {ref? : Reference ID for status check (optional)}';

    /**
     * The console command description.
     */
    protected $description = 'Test MTN MoMo disbursement, collection, or transaction status.';

    protected $mtn;

    public function __construct(MtnMomoService $mtn)
    {
        parent::__construct();
        $this->mtn = $mtn;
    }

    public function handle()
    {
        $type = $this->argument('type');
        $phone = $this->argument('phone');
        $amount = $this->argument('amount');
        $ref = Str::uuid()->toString();

        if ($type === 'collection') {
            $this->info("🔸 Initiating Collection Request...");
            $response = $this->mtn->collect($phone, $amount, $ref, 'Loan Repayment', 'Thanks');
        } elseif ($type === 'disbursement') {
            $this->info("🔸 Initiating Disbursement...");
            $response = $this->mtn->disburse($phone, $amount, $ref, 'Loan Disbursement');
        } elseif ($type === 'status') {
            $this->info("🔸 Checking Transaction Status for {$ref}...");
            $response = $this->mtn->checkStatus($ref, $this->choice('Transaction type?', ['collection', 'disbursement'], 0));
        } else {
            $this->error('❌ Invalid type. Use "collection", "disbursement", or "status".');
            return;
        }

        $this->newLine();
        $this->info('🔍 Response:');
        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("✅ Reference ID: {$ref}");
    }
}
