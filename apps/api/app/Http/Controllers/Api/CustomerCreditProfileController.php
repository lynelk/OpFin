<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\AppStoreCreditPolicy;
use App\Services\CustomerCreditProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerCreditProfileController extends Controller
{
    public function __construct(
        private readonly CustomerCreditProfileService $profiles,
        private readonly AppStoreCreditPolicy $appStorePolicy,
    ) {}

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

    public function options(Request $request): JsonResponse
    {
        $channel = (string) $request->query('distribution_channel', 'play_store');
        $storeChannel = in_array($channel, AppStoreCreditPolicy::STORE_CHANNELS, true);
        $minimumDuration = $storeChannel ? AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS : 1;

        $options = LoanProduct::query()
            ->where('status', 'Active')
            ->whereNotNull('institution_id')
            ->with(['terms' => fn ($query) => $query
                ->where('status', 'Active')
                ->where('duration', '>=', $minimumDuration)
                ->orderBy('duration')])
            ->orderBy('id')
            ->get()
            ->flatMap(fn ($product) => $product->terms->map(fn ($term) => [
                'loan_product_id' => $product->id,
                'loan_product_term_id' => $term->id,
                'institution_id' => $product->institution_id,
                'duration_days' => (int) $term->duration,
                'repayment_frequency' => (string) $term->repayment_frequency,
                'interest_rate_percent' => (float) $term->interest_rate,
                'interest_cycle' => (string) $term->interest_cycle,
                'interest_type' => (string) $term->interest_type,
            ]))
            ->sortBy('duration_days')
            ->values();

        return ApiResponse::success('Eligible repayment options loaded.', [
            'options' => $options,
            'distribution_channel' => $channel,
        ]);
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
