<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $nin = (string) ($user->national_id ?? '');
        $maskedNin = $nin === '' ? null : substr($nin, 0, 2).str_repeat('*', max(0, strlen($nin) - 6)).substr($nin, -4);

        return ApiResponse::success('Profile retrieved successfully.', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'other_name' => $user->other_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'institution_id' => $user->institution_id,
                'national_id_masked' => $maskedNin,
                'date_of_birth' => $user->date_of_birth,
                'nin_status' => $user->nin_status,
                'preferred_language' => $user->preferred_language,
                'accessibility_preferences' => $user->accessibility_preferences ?? [],
            ],
            'permissions' => $user->permissions(),
        ]);
    }
}
