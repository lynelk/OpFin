<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AirtelService;
use Illuminate\Support\Facades\Log;

class TestAirtelAuth extends Command
{
    protected $signature = 'airtel:test-auth';
    protected $description = 'Test Airtel OAuth authentication and fetch access token';

    public function handle()
    {
        $this->info("Testing Airtel OAuth authentication...");

        try {
            $airtel = new AirtelService();

            $this->info("Using Base URL: " . config('services.airtel.base_url'));
            $this->info("Using Client ID:  " . config('services.airtel.client_id'));

            $token = $airtel->getAccessToken();

            $this->info("------------------------------------------------------");
            $this->info(" SUCCESS! Airtel Access Token Retrieved:");
            $this->info(" Token: " . $token);
            $this->info("------------------------------------------------------");
        } catch (\Exception $e) {

            $this->error("------------------------------------------------------");
            $this->error(" FAILED to fetch Airtel access token!");
            $this->error($e->getMessage());
            $this->error("------------------------------------------------------");

            Log::error("Airtel OAuth Token Test Failed", [
                'error' => $e->getMessage()
            ]);
        }

        return Command::SUCCESS;
    }
}
