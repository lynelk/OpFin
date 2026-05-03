<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\LoanService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Approve a pending transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        try {
            // Validate the incoming request
            $validated = $request->validate([
                'amount' => 'nullable|numeric',
                'payer_number' => 'nullable|string',
                'reference' => 'required|string',
                'signature' => 'nullable|string',
                'network_ref' => 'required|string',
                'status' => 'required|string|in:SUCCESSFUL,FAILED,PENDING',
                'description' => 'nullable|string',
                'created_on' => 'nullable|string',
                'completed_on' => 'required|string',
            ]);
            // Find the transaction by reference
            $transaction = Transaction::where('reference', $validated['reference'])->first();

            if (!$transaction) {
                Log::error('Transaction not found', ['reference' => $validated['reference']]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }
            // Update transaction status
            $transaction->update([
                'status' => $validated['status'],
                'network_reference' => $validated['network_ref'],
                'updated_at' => $validated['completed_on'],
            ]);

            // Additional business logic based on payment status
            if ($validated['status'] === 'SUCCESSFUL') {
                $loanService = app(LoanService::class);
                $loanService->processSuccessfulTransaction($transaction);
            } else if ($validated['status'] == 'FAILED') {
                $transaction->loanApplication->update([
                    'status' => 'Rejected',
                    'rejected_at' => now(),
                ]);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Callback processed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing payment callback', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error processing callback ' . $e->getMessage()
            ], 500);
        }
    }

    public function airtelCallback(Request $request)
    {
        // Validate payload structure
        $data = $request->input('transaction');
        if (!$data || !isset($data['id'])) {
            Log::warning('Invalid Airtel callback format.');
            return response()->json(['status' => 'invalid payload'], 400);
        }

        $transactionId   = $data['id'];
        $statusCode      = $data['status_code'] ?? null;
        $airtelMoneyId   = $data['airtel_money_id'] ?? null;
        $message         = $data['message'] ?? null;

        $transaction = Transaction::where('reference', $transactionId)->first();
        if (!$transaction) {
            Log::warning("Transaction with reference {$transactionId} not found.");
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }

        $transaction->update([
            'status' => $statusCode === 'TS' ? 'SUCCESSFUL' : ($statusCode === 'TF' ? 'FAILED' : 'PENDING'),
            'network_reference' => $airtelMoneyId,
            'updated_at' => now(),
            'data' => $message,
        ]);

        // Additional business logic based on payment status
        if ($transaction->status === 'SUCCESSFUL') {

            $loanService = app(LoanService::class);
            $loanService->processSuccessfulTransaction($transaction);
        } else if ($transaction->status == 'FAILED') {
            if ($transaction->type == 'Disbursement') {
                $transaction->loanApplication->update([
                    'status' => 'Rejected',
                    'rejected_at' => now(),
                ]);
            }
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Callback processed successfully'
        ]);
    }
    public function mtnCallback(Request $request)
    {
        // Validate expected structure
        $status = $request->input('status');
        $externalId = $request->input('externalId');
        $financialTransactionId = $request->input('financialTransactionId');

        if (!$externalId) {
            Log::warning('Invalid MTN callback format: missing externalId.');
            return response()->json(['status' => 'invalid payload'], 400);
        }

        // Fetch your local transaction record
        $transaction = Transaction::where('reference', $externalId)->first();

        if (!$transaction) {
            Log::warning("Transaction with reference {$externalId} not found.");
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }

        // Map MTN status to internal status
        $mappedStatus = match (strtoupper($status)) {
            'SUCCESSFUL' => 'SUCCESSFUL',
            'FAILED' => 'FAILED',
            default => 'PENDING',
        };

        // Update the transaction record
        $transaction->update([
            'status' => $mappedStatus,
            'network_reference' => $financialTransactionId,
            'updated_at' => now(),
            'data' => json_encode($request->all()),
        ]);

        // Handle business logic for successful payments
        if ($mappedStatus === 'SUCCESSFUL') {
            $loanService = app(LoanService::class);
            $loanService->processSuccessfulTransaction($transaction);
        } elseif ($mappedStatus === 'FAILED') {
            if ($transaction->type == 'Disbursement') {
                $transaction->loanApplication->update([
                    'status' => 'Rejected',
                    'rejected_at' => now(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Callback processed successfully',
        ]);
    }
}
