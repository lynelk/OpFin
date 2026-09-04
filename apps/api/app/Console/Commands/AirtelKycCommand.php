<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AirtelService;

class AirtelKycCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * phone is required argument
     */
    protected $signature = 'airtel:kyc {phone}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch KYC details for a given Airtel phone number';

    /**
     * Execute the console command.
     */
    public function handle(AirtelService $airtel)
    {
        $phone = $this->argument('phone');

        $this->info("🔍 Fetching KYC info for: {$phone} ...");

        try {
            $response = $airtel->getKycInfo($phone);

            $this->info("✅ KYC Response:");
            $this->line(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->error("❌ Failed: " . $e->getMessage());
        }

        return 0;
    }
}
