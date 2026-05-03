<?php

namespace App\Console\Commands;

use App\Services\MtnMomoService;
use Illuminate\Console\Command;

class MomoBalance extends Command
{
    protected $signature = 'momo:balance {type=collection}';
    protected $description = 'Check MTN MoMo account balance';

    public function handle()
    {
        $type = $this->argument('type');

        $mtn = new MtnMomoService();
        $result = $mtn->getBalance($type);

        if (!$result['success']) {
            $this->error('Failed: ' . $result['message']);
            return 1;
        }

        $this->info("Balance ({$type}): {$result['balance']} {$result['currency']}");
        return 0;
    }
}
