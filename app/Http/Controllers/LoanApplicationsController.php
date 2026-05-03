<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use Illuminate\Http\Request;

class LoanApplicationsController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $applications = LoanApplication::when($status, function ($q) use ($status) {
            $q->where('status', $status);
        })->latest()->paginate(10);

        return view('loan-applications.index', compact('applications', 'status'));
    }
}
