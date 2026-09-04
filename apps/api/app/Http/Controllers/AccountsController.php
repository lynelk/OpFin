<?php

namespace App\Http\Controllers;

use App\Models\Account;

class AccountsController extends Controller
{
    public function index()
    {
        $accounts = Account::whereNotNull('loan_product_id')->get();
        $collectionAccounts = Account::whereLike('name', '%Collection%')->get();
        $disbursementAccounts = Account::whereLike('name', '%Disbursement%')->get();
        return view('accounts.index', compact('accounts', 'collectionAccounts', 'disbursementAccounts'));
    }
}
