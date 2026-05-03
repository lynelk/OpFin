<?php

namespace App\Http\Controllers\Api;

use App\Models\LoanApplication;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AirtelDisbursementService;
use App\Services\AirtelService;
use App\Services\MtnMomoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    public function getLoanBalance(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => 'Loan balance retrieved successfully',
            'outstandingAmount' => $user->outstandingAmount(),
        ]);
    }

    public function index($id)
    {
        try {
            $applications = LoanApplication::where('user_id', $id)->with(['user', 'loanProduct', 'loanProductTerm', 'institution', 'loan'])->latest()->get()->each(function ($application) {
                if ($application->loan) {
                    $application->loan->append('outstanding_balance');
                }
            });
            return response()->json(['data' => $applications], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|exists:loan_products,id',
            'loan_product_term_id' => 'required|exists:loan_product_terms,id',
            'institution_id' => 'required|exists:institutions,id',
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for loan application', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $ninStatus = $user->nin_status;
        if ($ninStatus !== 'VALID') {
            return response()->json([
                'success' => false,
                'message' => 'Your NIN is not verified. Please head to the profile page to validate your NIN'
            ], 400);
        }

        try {
            $userId = $user->id;

            // Check if the user has any uncleared loans
            $unclearedLoan = Loan::where('user_id', $userId)
                ->whereNotIn('status', ['Cleared'])
                ->first();

            if ($unclearedLoan) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have an uncleared loan, please clear that first to be able to qualify for another loan.'
                ], 400);
            }

            // Check if the user has any pending applications
            $pendingApplication = LoanApplication::where('user_id', $userId)
                ->where('status', 'Pending')
                ->first();

            if ($pendingApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending application, please wait for it to get processed.'
                ], 400);
            }

            $loanApplication = LoanApplication::create([
                'user_id' => $userId,
                'loan_product_id' => $request->loan_product_id,
                'loan_product_term_id' => $request->loan_product_term_id,
                'institution_id' => $request->institution_id,
                'amount' => $request->amount,
                'status' => 'Pending',
                'reason' => $request->reason,
            ]);
            $this->createTransaction($loanApplication);

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully.',
                'data' => $loanApplication
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating loan application: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating loan application. ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Approved,Rejected,Disbursed,Cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        try {
            $loanApplication = LoanApplication::findOrFail($id);

            if ($loanApplication->status !== 'Pending') {
                return response()->json(['error' => 'Loan application is not in a pending state.'], 400);
            }

            $loanApplication->status = $request->status;

            if ($request->status === 'Approved') {
                $loanApplication->approved_at = now();
            } elseif ($request->status === 'Rejected') {
                $loanApplication->rejected_at = now();
            } elseif ($request->status === 'Disbursed') {
                $loanApplication->disbursed_at = now();
            } elseif ($request->status === 'Cancelled') {
                $loanApplication->cancelled_at = now();
            }

            $loanApplication->save();
            DB::commit();
            return response()->json(['data' => $loanApplication], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating loan application status: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the loan application status.'], 500);
        }
    }

    public function getProducts()
    {
        try {
            $user = Auth::user();
            $products = LoanProduct::with('institution')->where(function ($query) use ($user) {
                $query->where('institution_id', $user->institution_id)
                    ->orWhere('institution_id', null);
            })->get();

            return response()->json(['data' => $products], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getProductTerms($id)
    {
        try {
            $terms = LoanProductTerm::with('product.institution')->where('loan_product_id', $id)->get();
            return response()->json(['data' => $terms], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getInstitutions()
    {
        try {
            $institutions = Institution::all();
            return response()->json(['data' => $institutions], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createTransaction(LoanApplication $loanApplication)
    {

        $transaction =  Transaction::create([
            'user_id' => $loanApplication->user_id,
            'institution_id' => $loanApplication->institution_id,
            'loan_application_id' => $loanApplication->id,
            'loan_id' => null,
            'type' => 'Disbursement',
            'amount' => $loanApplication->amount,
            'phone' => $loanApplication->user->phone,
            'reference' => Str::uuid()->toString(),
            'status' => 'Pending',
        ]);
        $this->disburseLoan($loanApplication, $transaction);
    }

    public function disburseLoan(LoanApplication $loanApplication, Transaction $transaction)
    {
        try {
            // Mark transaction as processing
            $transaction->update([
                'status' => 'Processing',
                'processing_at' => now()
            ]);
            $channel = $transaction->network;
            if ($channel === 'AIRTEL') {
                $airtelService = new AirtelDisbursementService(new AirtelService());
                $paymentResponse = $airtelService->disburse($transaction);
            } else if ($channel === 'MTN') {
                $mtnService = new MtnMomoService();
                $paymentResponse = $mtnService->disburse($transaction->phone, $transaction->amount, $transaction->reference, 'Loan Disbursement');
            } else {
                throw new \Exception("Unsupported payment gateway: {$channel}");
            }
            if (!$paymentResponse['success']) {
                throw new \Exception($paymentResponse['message'] ?? 'Payment processing failed');
            }

            // Update transaction on success
            $updateData = [
                'status' => $paymentResponse['status'],
                'external_reference' => $paymentResponse['transaction_id'],
                'updated_at' => now(),
            ];

            $transaction->update($updateData);
        } catch (\Exception $e) {
            // Log detailed error
            Log::error('Loan Disbursement failed', [
                'error' => $e->getMessage(),
                'loan_id' => $loanApplication->id,
                'transaction_id' => $transaction->id,
            ]);

            // Update transaction with failure status
            $transaction->update([
                'status' => 'FAILED',
            ]);

            $transaction->loanApplication->update([
                'status' => 'Rejected',
                'rejected_at' => now(),
            ]);

            // Return detailed error response
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction' => $transaction,
                'loan_application' => $loanApplication->fresh()
            ];
        }
    }
}
