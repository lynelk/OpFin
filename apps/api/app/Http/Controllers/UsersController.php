<?php

namespace App\Http\Controllers;

use App\Models\CreditScore;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    /**
     * The legacy forms still post the old role names. Members are customers
     * registered by staff; institution administrators have no equivalent role
     * and wait for a platform administrator to review their access.
     */
    private const LEGACY_ROLE_MAP = [
        'Member' => User::ROLE_CUSTOMER,
        'Admin' => User::ROLE_STAFF_PENDING_REVIEW,
    ];

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
            'institution_id' => 'nullable|exists:institutions,id',
        ]);

        // No default credential: the account holder sets their own PIN through
        // the OTP-verified reset journey before they can sign in.
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(64)),
            'role' => self::LEGACY_ROLE_MAP[$request->role],
            'institution_id' => $request->institution_id,
            'phone' => $request->phone,
        ]);

        return redirect()->route('users.index')->with('success', 'User created. They set their own PIN with a one-time code before signing in.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $institutions = Institution::all();

        return view('users.edit', compact('user', 'institutions'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,'.$id,
            'phone' => 'required|digits:12|unique:users,phone,'.$id,
            'institution_id' => 'required|exists:institutions,id',
        ]);

        $user = User::findOrFail($id);
        abort_if($user->is($request->user()), 403, 'You cannot change your own account here.');
        abort_unless(in_array($user->role, array_values(self::LEGACY_ROLE_MAP), true), 403, 'Staff access is managed in the Web back office.');

        // The role is deliberately not editable here; the legacy form always
        // posts a hidden role value that would otherwise overwrite it.
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
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
