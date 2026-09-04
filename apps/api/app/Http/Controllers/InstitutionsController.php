<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstitutionsController extends Controller
{
    public function index()
    {
        $institutions = Institution::latest()->get();
        return view('institutions.index', compact('institutions'));
    }
    public function show($id)
    {
        $institution = Institution::findOrFail($id);
        return view('institutions.show', compact('institution'));
    }
    public function create()
    {
        return view('institutions.create');
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ]);
        Institution::create($request->all());
        return redirect()->route('institutions.index')->with('success', 'Institution created successfully.');
    }
    public function edit($id)
    {
        $institution = Institution::findOrFail($id);
        $users = $institution->users; // Assuming Institution has a users relationship
        return view('institutions.edit', compact('institution', 'users'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
        ]);
        $institution = Institution::findOrFail($id);
        $institution->update($request->all());
        return redirect()->route('institutions.index')->with('success', 'Institution updated successfully.');
    }
    public function destroy($id)
    {
        $institution = Institution::findOrFail($id);
        $institution->delete();
        return redirect()->route('institutions.index')->with('success', 'Institution deleted successfully.');
    }
    public function postAdministrator(Request $request)
    {
        try {
            $request->validate([
                'institution_id' => 'required|exists:institutions,id',
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|in:Admin', // Assuming role is passed in the request
            ]);

            $institution = Institution::findOrFail($request->institution_id);
            $institution->users()->create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'role' => $request->role, // Assuming role is passed in the request
                'password' => bcrypt($request->password),
            ]);
            return redirect()->route('institutions.edit', $institution)->with('success', 'Administrator added successfully.');
        } catch (\Exception $e) {
            Log::error('Error adding administrator: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function updateAdministrator(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'phone', 'email']);

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return redirect()->route('institutions.edit', $user->institution)->with('success', 'Administrator added successfully.');
    }
}
