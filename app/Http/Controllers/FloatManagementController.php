<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FloatTopup;
use Illuminate\Http\Request;

class FloatManagementController extends Controller
{
    public function index()
    {
        $floatTopups = FloatTopup::latest()->get();
        $account = Account::where('name', 'Disbursement')->first();
        return view('float-management.index', compact('floatTopups', 'account'));
    }
    // store topup
    public function store(Request $request)
    {
        // Validate incoming data
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'image'  => 'nullable|image|max:2048', // max 2MB
        ]);

        // Handle image upload (optional)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('float_topups', 'public');
        }

        // Create float top-up record
        FloatTopup::create([
            'amount' => $validated['amount'],
            'image'  => $imagePath,
            'status' => 'Approved',
        ]);

        // OPTIONAL: Update account balance — tell me your logic  
        // Example:
        $account = Account::where('name', 'Disbursement')->first();
        $account->balance += $validated['amount'];
        $account->save();

        return redirect()
            ->back()
            ->with('success', 'Float top-up submitted successfully.');
    }
}
