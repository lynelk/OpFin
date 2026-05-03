<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AirtelService;

class AirtelCheckBalance extends Command
{
    protected $signature = 'airtel:balance';
    protected $description = 'Check Airtel wallet balance';

    public function handle(AirtelService $airtel)
    {
        try {
            $response = $airtel->getBalance();

            $account = $response['data'] ?? [];

            $this->info('Airtel Wallet Balance');
            $this->line('----------------------');
            $this->line('Balance: ' . ($account['balance'] ?? 'N/A'));
            $this->line('Currency: ' . ($account['currency'] ?? 'N/A'));
            $this->line('Status: ' . ($account['account_status'] ?? 'N/A'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
