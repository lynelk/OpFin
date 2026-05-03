<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AirtelService;
use App\Services\MtnMomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $airtel = new AirtelService();
        try {
            $airtelResponse = $airtel->getBalance();
            $airtelBalance =  $airtelResponse['data']['balance'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Error fetching Airtel balance: ' . $e->getMessage());
            $airtelBalance = 0;
        }

        $mtn = new MtnMomoService();
        try {
            $mtnResponse = $mtn->getBalance('collection');
            $mtnBalance = $mtnResponse['balance'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Error fetching MTN MoMo balance: ' . $e->getMessage());
            $mtnBalance = 0;
        }
        return view('home', [
            'institutionCount' => Institution::count(),
            'loansCount' => Loan::count(),
            'userCount' => User::count(),
            'adminCount' => User::where('role', 'Admin')->count(),
            'loanProductCount' => LoanProduct::count(),
            'activeLoanProducts' => LoanProduct::where('status', 'Active')->count(),
            // Add more statistics as needed
            'recentApplications' => LoanApplication::latest()->take(5)->get(),
            'recentTransactions' => Transaction::latest()->take(5)->get(),
            'airtelBalance' => $airtelBalance,
            'mtnBalance' => $mtnBalance,
        ]);
    }

    public function getUserGrowthData()
    {
        $months = [];
        $userCounts = [];

        // Last 6 months data
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
            'data' => $userCounts
        ]);
    }

    public function getLoansDisbursedData()
    {
        $months = [];
        $loanCounts = [];
        $amounts = [];

        // Last 6 months data
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
            'amounts' => $amounts
        ]);
    }
}
