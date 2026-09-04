<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;

class LoansController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $loans = Loan::with(['user', 'loanProduct'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10);

        return view('loans.index', compact('loans', 'status'));
    }
}
