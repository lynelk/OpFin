<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Services\AirtelCollectionService;
use App\Services\AirtelDisbursementService;
use Illuminate\Support\Facades\Log;

class TestAirtelCommand extends Command
{
    protected $signature = 'airtel:test 
        {mode : collect|disburse|collection-status|disbursement-status}
        {phone? : Phone number 2567XXXXXXX (required for collect/disburse)}
        {amount? : Amount (required for collect/disburse)}
        {transactionId? : Airtel transaction id (required for status)}';

    protected $description = 'Test Airtel Money API: collections, disbursements and status checks';

    public function handle()
    {
        $mode = $this->argument('mode');
        $airtel = app(AirtelDisbursementService::class);

        switch ($mode) {

            case 'collect':
                $airtel = app(AirtelCollectionService::class);
                return $this->testCollection($airtel);
            case 'collection-status':
                $airtel = app(AirtelCollectionService::class);
                return $this->testCollectionStatus($airtel);
            case 'disburse':
                return $this->testDisbursement($airtel);

            case 'disbursement-status':
                return $this->testDisbursementStatus($airtel);

            default:
                $this->error("Invalid mode. Use: collect | disburse | status");
                return Command::FAILURE;
        }
    }


    /* -----------------------------
     *  TEST COLLECTION
     * ----------------------------- */
    private function testCollection(AirtelCollectionService $airtel)
    {
        $phone = $this->argument('phone');
        $amount = $this->argument('amount');

        if (!$phone || !$amount) {
            $this->error("phone and amount are required for collection test.");
            return Command::FAILURE;
        }

        // Create fake transaction object
        $transaction = new Transaction();
        $transaction->phone = $phone;
        $transaction->amount = $amount;
        $transaction->reference = Str::uuid()->toString();
        $transaction->type = 'Test Collection';

        $this->info("Initiating Airtel Collection…");

        $response = $airtel->collect($transaction);

        $this->displayResponse($response);

        return Command::SUCCESS;
    }


    /* -----------------------------
     *  TEST DISBURSEMENT
     * ----------------------------- */
    private function testDisbursement(AirtelDisbursementService $airtel)
    {
        $phone = $this->argument('phone');
        $amount = $this->argument('amount');

        if (!$phone || !$amount) {
            $this->error("phone and amount are required for disbursement test.");
            return Command::FAILURE;
        }

        // Fake transaction model
        $transaction = new Transaction();
        $transaction->phone = $phone;
        $transaction->amount = $amount;
        $transaction->reference = Str::uuid()->toString();
        $transaction->type = 'Test Disbursement';

        $this->info("Initiating Airtel Disbursement…");

        $response = $airtel->disburse($transaction);

        $this->displayResponse($response);

        $this->line("Returned Airtel Transaction ID: " . ($response['transaction_id'] ?? 'NONE'));

        return Command::SUCCESS;
    }


    /* -----------------------------
     *  TEST DISBURSEMENT STATUS
     * ----------------------------- */
    private function testDisbursementStatus(AirtelDisbursementService $airtel)
    {
        $txnId = $this->argument('transactionId');

        if (!$txnId) {
            $this->error("transactionId is required for status check.");
            return Command::FAILURE;
        }

        $this->info("Checking Airtel disbursement status for: $txnId");

        $response = $airtel->checkStatus($txnId);

        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /* -----------------------------
     *  TEST DISBURSEMENT STATUS
     * ----------------------------- */
    private function testCollectionStatus(AirtelCollectionService $airtel)
    {
        $txnId = $this->argument('transactionId');

        if (!$txnId) {
            $this->error("transactionId is required for status check.");
            return Command::FAILURE;
        }

        $this->info("Checking Airtel collection status for: $txnId");

        $response = $airtel->checkStatus($txnId);

        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /* -----------------------------
     *  FORMAT RESPONSE OUTPUT
     * ----------------------------- */
    private function displayResponse(array $response)
    {
        $this->newLine();
        $this->info("=== Airtel API Response ===");

        foreach ($response as $key => $value) {
            $this->line(ucfirst($key) . ": " . json_encode($value));
        }
    }
}
