<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $transactions = Transaction::when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10);

        return view('transactions.index', compact('transactions', 'status'));
    }
}
