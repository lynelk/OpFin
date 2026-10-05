<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FloatTopup;
use App\Services\FloatTopupService;
use DomainException;
use Illuminate\Http\Request;

class FloatManagementController extends Controller
{
    public function __construct(private readonly FloatTopupService $floatTopups) {}

    public function index()
    {
        $floatTopups = FloatTopup::with(['recordedBy', 'approvedBy'])->latest()->get();
        $account = Account::where('name', 'Disbursement')->first();

        return view('float-management.index', compact('floatTopups', 'account'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999999999.99',
            'image' => 'nullable|image|max:2048', // max 2MB
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('float_topups', 'public');
        }

        $this->floatTopups->record($request->user(), (string) $validated['amount'], $imagePath, $request);

        return redirect()
            ->back()
            ->with('success', 'Float top-up recorded. A different staff member must approve it before the balance changes.');
    }

    public function approve(Request $request, FloatTopup $floatTopup)
    {
        try {
            $this->floatTopups->approve($floatTopup, $request->user(), $request);
        } catch (DomainException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('success', 'Float top-up approved and the Disbursement balance updated.');
    }
}
