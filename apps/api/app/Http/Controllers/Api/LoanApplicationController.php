<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoanApplicationController extends Controller
{
    public function getLoanBalance(User $user)
    {
        if ($user->id !== Auth::id()) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        return ApiResponse::success('Loan balance retrieved successfully.', [
            'outstandingAmount' => $user->outstandingAmount(),
        ]);
    }

    public function index($id)
    {
        if ((int) $id !== Auth::id()) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        try {
            $applications = LoanApplication::where('user_id', $id)
                ->with(['user', 'loanProduct', 'loanProductTerm', 'institution', 'loan'])
                ->latest()
                ->get()
                ->each(function ($application) {
                    if ($application->loan) {
                        $application->loan->append('outstanding_balance');
                    }
                });

            return ApiResponse::success('Loan applications retrieved successfully.', $applications->toArray());
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * The legacy application write path is intentionally disabled.
     * New applications must use ProductionLoanApplicationController so all
     * credit decisions and CPay-owned money movement follow the governed flow.
     */
    public function store(Request $request)
    {
        return ApiResponse::error(
            'Legacy loan application submission is retired. Use the governed production credit application endpoint.',
            410
        );
    }

    public function updateStatus(Request $request, $id)
    {
        if (!$this->canManageLoans($request)) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Approved,Rejected,Disbursed,Cancelled',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        DB::beginTransaction();

        try {
            $loanApplication = LoanApplication::findOrFail($id);

            if ($loanApplication->status !== 'Pending') {
                DB::rollBack();

                return ApiResponse::error('Loan application is not in a pending state.', 400);
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

            return ApiResponse::success('Loan application status updated successfully.', $loanApplication->toArray());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating loan application status: '.$e->getMessage());

            return ApiResponse::error('An error occurred while updating the loan application status.', 500);
        }
    }

    private function canManageLoans(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->is_admin || $user?->hasAnyRole([
            User::ROLE_PLATFORM_ADMIN,
            User::ROLE_OPERATIONS,
        ]));
    }

    public function getProducts()
    {
        try {
            $user = Auth::user();
            $products = LoanProduct::with('institution')->where(function ($query) use ($user) {
                $query->where('institution_id', $user->institution_id)
                    ->orWhere('institution_id', null);
            })->get();

            return ApiResponse::success('Products retrieved successfully.', $products->toArray());
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function getProductTerms($id)
    {
        try {
            $terms = LoanProductTerm::with('product.institution')->where('loan_product_id', $id)->get();

            return ApiResponse::success('Product terms retrieved successfully.', $terms->toArray());
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function getInstitutions()
    {
        try {
            $institutions = Institution::all();

            return ApiResponse::success('Institutions retrieved successfully.', $institutions->toArray());
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
