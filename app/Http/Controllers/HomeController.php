<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home', [
            'institutionCount' => Institution::count(),
            'loansCount' => Loan::count(),
            'userCount' => User::count(),
            'adminCount' => User::where('role', 'Admin')->count(),
            'loanProductCount' => LoanProduct::count(),
            'activeLoanProducts' => LoanProduct::where('status', 'Active')->count(),
            'recentApplications' => LoanApplication::latest()->take(5)->get(),
            'recentTransactions' => Transaction::latest()->take(5)->get(),
            'paymentBoundary' => 'CPay',
        ]);
    }

    public function getUserGrowthData()
    {
        $months = [];
        $userCounts = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthYear = $date->format('M Y');

            $months[] = $monthYear;
            $userCounts[] = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        return response()->json([
            'labels' => $months,
            'data' => $userCounts,
        ]);
    }

    public function getLoansDisbursedData()
    {
        $months = [];
        $loanCounts = [];
        $amounts = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthYear = $date->format('M Y');

            $months[] = $monthYear;

            $loans = Loan::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);

            $loanCounts[] = $loans->count();
            $amounts[] = $loans->sum('amount');
        }

        return response()->json([
            'labels' => $months,
            'counts' => $loanCounts,
            'amounts' => $amounts,
        ]);
    }
}
