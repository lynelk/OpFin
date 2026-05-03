<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Transaction;
use App\Services\AirtelCollectionService;
use App\Services\AirtelService;
use App\Services\MtnMomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoanRepaymentController extends Controller
{
    /**
     * Repay a loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $loan_id
     * @return \Illuminate\Http\JsonResponse
     */

    public function repay(Request $request, $loan_id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $loan = Loan::findOrFail($loan_id);

            if ($loan->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 403);
            }

            if ($loan->status == 'Cleared') {
                return response()->json([
                    'success' => false,
                    'message' => 'This loan has already been cleared.'
                ], 400);
            }

            if ($request->amount > $loan->outstanding_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount exceeds the repayment amount.'
                ], 400);
            }

            $transaction = Transaction::where('loan_id', $loan->id)
                ->where('type', 'Repayment')
                ->where('status', 'Pending')
                ->first();
            if ($transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have a pending repayment transaction. Please complete the payment on your phone. We will notify you once the transaction is successful.'
                ], 409);
            }

            // Create a repayment transaction
            $repaymentTransaction = Transaction::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'loan_application_id' => $loan->loanApplication->id,
                'type' => 'Repayment',
                'amount' => $request->amount,
                'phone' => $loan->user->phone,
                'reference' => Str::uuid()->toString(),
                'status' => 'Pending',
            ]);
            $channel = $repaymentTransaction->network;
            if ($channel === 'AIRTEL') {
                $airtelService = new AirtelCollectionService(new AirtelService());
                $paymentResponse = $airtelService->collect($repaymentTransaction);
            } else if ($channel === 'MTN') {
                $mtnService = new MtnMomoService();
                $paymentResponse = $mtnService->collect($repaymentTransaction->phone, $repaymentTransaction->amount, $repaymentTransaction->reference, 'Loan Repayment', 'Thanks for repaying your loan');
            } else {
                throw new \Exception("Unsupported payment gateway: {$channel}");
            }
            if (!$paymentResponse['success']) {
                throw new \Exception($paymentResponse['message'] ?? 'Payment processing failed');
            }

            // Update transaction on success
            $updateData = [
                'status' => $paymentResponse['status'],
                'external_reference' => $paymentResponse['transaction_id'] ?? null,
                'updated_at' => now(),
            ];

            $repaymentTransaction->update($updateData);
            return response()->json([
                'success' => true,
                'message' => 'Payment request initiated. Please complete the payment on your phone. You will be notified once the transaction is successful.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error processing loan repayment: ' . $e->getMessage());
            if (isset($repaymentTransaction)) {
                $repaymentTransaction->update([
                    'status' => 'FAILED',
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
