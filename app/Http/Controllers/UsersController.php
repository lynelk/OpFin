<?php

namespace App\Http\Controllers;

use App\Models\CreditScore;
use App\Models\Institution;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsersController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(10);
        return view('users.index', compact('users'));
    }
    public function create()
    {
        return view('users.create');
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'role' => 'required|string|in:Admin,Member',
            'phone' => 'required|digits:12|unique:users,phone',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'institution_id' => $request->institution_id,
            'phone' => $request->phone,
            'password' => 'Password@123'
        ]);
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }
    public function edit($id)
    {
        $admin = Auth::user();
        $user = User::findOrFail($id);
        if ($admin->role == 'Super') {
            $institutions = Institution::all();
        } else {
            $institutions = Institution::where('id', $admin->institution_id)->get();
        }
        return view('users.edit', compact('user', 'institutions'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $id,
            'role' => 'required|string|in:Admin,Member',
            'phone' => 'required|digits:12|unique:users,phone,' . $id,
            'institution_id' => 'required|exists:institutions,id',
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'institution_id' => $request->institution_id,
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function show(User $user)
    {
        // Latest score (within 30 days)
        $latestScore = CreditScore::where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        // Optional: history
        $scoreHistory = CreditScore::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('users.show', compact(
            'user',
            'latestScore',
            'scoreHistory'
        ));
    }
}
