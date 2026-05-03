<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\AirtelCollectionService;
use App\Services\AirtelDisbursementService;
use App\Services\CitotechPaymentService;
use App\Services\LoanService;
use App\Services\MtnMomoService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTransactionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opfin:check-transaction-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check transaction status from payment gateway API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $transactions = Transaction::whereIn('status', ['PENDING'])
                ->limit(2)
                ->get();

            foreach ($transactions as $transaction) {
                if ($transaction->type === 'Repayment') {
                    $operationType = 'collection';
                } else {
                    $operationType = 'disbursement';
                }
                try {
                    // Decide which provider to check
                    $channel = $transaction->network;

                    if ($channel === 'AIRTEL') {
                        if ($operationType === 'disbursement') {
                            $result = app(AirtelDisbursementService::class)->checkStatus($transaction->external_reference);
                        } else {
                            $result = app(AirtelCollectionService::class)->checkStatus($transaction->reference);
                        }
                        // } else if ($channel === 'CITOTECH') {
                        //     $result = app(CitotechPaymentService::class)->checkStatus($transaction);
                    } else if ($channel === 'MTN') {
                        $mtnService = new MtnMomoService();
                        $result = $mtnService->checkStatus($transaction->reference,  $operationType);
                    } else {
                        throw new \Exception("Unsupported payment gateway: {$channel}");
                    }
                    if (!$result['success']) {
                        throw new \Exception($result['message'] ?? 'Status check failed');
                    }

                    // Update transaction
                    $transaction->update([
                        'status' => $result['status'],
                        'network_reference' => $result['network_reference'] ?? null,
                        'updated_at' => now(),
                    ]);

                    // Process accordingly
                    if ($transaction->status == 'SUCCESSFUL') {
                        app(LoanService::class)->processSuccessfulTransaction($transaction);
                    } else if ($transaction->status == 'FAILED') {
                        $transaction->loanApplication->update([
                            'status' => 'Rejected',
                            'rejected_at' => now(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Status check failed for transaction', [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return 1;
        } catch (\Exception $e) {
            Log::error('Transaction status command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
