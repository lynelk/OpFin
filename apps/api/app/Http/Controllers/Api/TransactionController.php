<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Approve a pending transaction and trigger loan processing.
     */
    public function approve(Request $request, $id)
    {
        if (! $this->canManageTransactions($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        try {
            $transaction = Transaction::findOrFail($id);

            if ($transaction->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending transactions can be approved.',
                ], 400);
            }

            $transaction->update(['status' => 'SUCCESSFUL']);

            $loanService = app(LoanService::class);
            $freshTransaction = $transaction->fresh();
            $loanService->processSuccessfulTransaction($freshTransaction);

            return response()->json([
                'success' => true,
                'message' => 'Transaction approved successfully.',
                'data' => $freshTransaction,
            ]);
        } catch (\Exception $exception) {
            Log::error('Error approving transaction: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error approving transaction: '.$exception->getMessage(),
            ], 500);
        }
    }

    private function canManageTransactions(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->is_admin || $user?->hasAnyRole([
            User::ROLE_PLATFORM_ADMIN,
            User::ROLE_OPERATIONS,
        ]));
    }
}
