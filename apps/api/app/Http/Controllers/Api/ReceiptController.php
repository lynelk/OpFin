<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success('Transaction receipts loaded.', [
            'receipts' => DB::table('transaction_receipts')
                ->where('user_id', $request->user()->id)
                ->latest('issued_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function show(int $receipt, Request $request): JsonResponse
    {
        $record = DB::table('transaction_receipts')
            ->where('id', $receipt)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $record) {
            return ApiResponse::error('Receipt not found.', 404);
        }

        return ApiResponse::success('Transaction receipt loaded.', ['receipt' => $record]);
    }
}
