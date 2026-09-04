<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductsController extends Controller
{
    public function index()
    {
        $loanProducts = LoanProduct::latest()->get();
        return view('loan-products.index', compact('loanProducts'));
    }

    public function show(LoanProduct $loanProduct)
    {
        return view('loan-products.show', compact('loanProduct'));
    }
    public function create()
    {
        return view('loan-products.create');
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);
        LoanProduct::create($request->all());
        return redirect()->route('loan-products.index')->with('success', 'Loan product created successfully.');
    }
    public function edit(LoanProduct $loanProduct)
    {
        $institutions = Institution::all();
        return view('loan-products.edit', compact('loanProduct', 'institutions'));
    }
    public function update(Request $request, LoanProduct $loanProduct)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'institution_id' => 'nullable|exists:institutions,id',
        ]);
        $loanProduct->update($request->all());
        return redirect()->route('loan-products.index')->with('success', 'Loan product updated successfully.');
    }
    public function changeStatus(LoanProduct $loanProduct)
    {
        $loanProduct->status == 'Inactive' ? $loanProduct->status = 'Active' : $loanProduct->status = 'Inactive';
        $loanProduct->save();
        return redirect()->route('loan-products.index')->with('success', 'Loan product status updated successfully.');
    }
    public function addTerm(LoanProduct $loanProduct)
    {
        return view('loan-products.add-term', compact('loanProduct'));
    }

    public function storeTerm(Request $request, LoanProduct $loanProduct)
    {
        $request->validate([
            'interest_rate' => 'required|numeric|min:0',
            'interest_type' => 'required|string|max:50',
            'interest_cycle' => 'required|string|max:50',
            'repayment_frequency' => 'required|string|max:50',
            'duration' => 'required|integer|min:1',
        ]);
        $loanProduct->terms()->create($request->all());
        return redirect()->route('loan-products.show', $loanProduct)->with('success', 'Loan product term added successfully.');
    }
    public function editTerm(LoanProduct $loanProduct, $termId)
    {
        $term = $loanProduct->terms()->findOrFail($termId);
        return view('loan-products.edit-term', compact('loanProduct', 'term'));
    }
    public function updateTerm(Request $request, LoanProduct $loanProduct, $termId)
    {
        $request->validate([
            'interest_rate' => 'required|numeric|min:0',
            'interest_type' => 'required|string|max:50',
            'interest_cycle' => 'required|string|max:50',
            'repayment_frequency' => 'required|string|max:50',
            'duration' => 'required|integer|min:1',
        ]);
        $term = $loanProduct->terms()->findOrFail($termId);
        $term->update($request->all());
        return redirect()->route('loan-products.show', $loanProduct)->with('success', 'Loan product term updated successfully.');
    }
    public function changeTermStatus(LoanProduct $loanProduct, $termId)
    {
        $term = $loanProduct->terms()->findOrFail($termId);
        $term->status == 'Inactive' ? $term->status = 'Active' : $term->status = 'Inactive';
        $term->save();
        return redirect()->route('loan-products.show', $loanProduct)->with('success', 'Loan product term status updated successfully.');
    }
}
