<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppStoreCreditPolicy;
use App\Services\CreditDistributionService;
use App\Services\CustomerCreditProfileService;
use App\Services\PlatformCreditRoutingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $data = $request->validate([
            'distribution_channel' => ['nullable', Rule::in(app(CreditDistributionService::class)->channels())],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $channel = $data['distribution_channel'] ?? 'play_store';
        $country = $data['country'] ?? config('opfin.default_country', 'UG');
        $options = app(PlatformCreditRoutingService::class)->options($channel, $country, $data['amount_minor'] ?? null, $data['reason'] ?? null)
            ->map(fn ($item) => [
                'loan_product_id' => $item['product']->id,
                'loan_product_term_id' => $item['term']->id,
                'institution_id' => $item['product']->institution_id,
                'product_name' => $item['product']->name,
                'lender' => $item['product']->institution->lenderDisclosure(),
                'currency' => $item['product']->currency,
                'country' => $item['product']->country,
                'duration_days' => (int) $item['term']->duration,
                'repayment_frequency' => (string) $item['term']->repayment_frequency,
                'interest_rate_percent' => (float) $item['term']->interest_rate,
                'interest_cycle' => (string) $item['term']->interest_cycle,
                'interest_type' => (string) $item['term']->interest_type,
            ])->values();

        return ApiResponse::success('Eligible repayment options loaded.', [
            'options' => $options, 'distribution_channel' => $channel, 'country' => $country,
            'message' => $options->isEmpty() ? 'No lender product is currently available for this market and channel.' : null,
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
            'high_contrast' => 'nullable|boolean',
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
