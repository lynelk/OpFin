<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CustomerCreditProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerCreditProfileController extends Controller
{
    public function __construct(private readonly CustomerCreditProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success('Credit profile loaded.', $this->profiles->status($request->user()));
    }

    public function refresh(Request $request): JsonResponse
    {
        $profile = $this->profiles->refresh($request->user(), true);

        return ApiResponse::success(
            $profile->status === 'pending'
                ? 'Your credit profile is still being completed.'
                : 'Your credit profile has been updated.',
            $this->profiles->status($request->user()),
        );
    }

    public function accessibility(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preferred_language' => 'nullable|string|max:16',
            'simple_language' => 'nullable|boolean',
            'large_text' => 'nullable|boolean',
            'screen_reader_optimised' => 'nullable|boolean',
            'reduced_motion' => 'nullable|boolean',
            'audio_guidance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $language = $validated['preferred_language'] ?? $request->user()->preferred_language ?? 'en';
        unset($validated['preferred_language']);

        $request->user()->forceFill([
            'preferred_language' => $language,
            'accessibility_preferences' => array_merge(
                $request->user()->accessibility_preferences ?? [],
                $validated,
            ),
        ])->save();

        return ApiResponse::success('Accessibility preferences updated.', [
            'preferred_language' => $request->user()->preferred_language,
            'accessibility_preferences' => $request->user()->accessibility_preferences,
        ]);
    }
}
