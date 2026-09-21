<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InclusiveFinanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class InclusiveFinanceController extends Controller
{
    public function __construct(private readonly InclusiveFinanceService $service) {}

    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success('Inclusive-finance profile loaded.', $this->service->profile($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_measurement_consent' => ['sometimes', 'boolean'],
            'measurement_attributes' => ['sometimes', 'array'],
            'measurement_attributes.gender' => ['nullable', 'string', 'max:80'],
            'measurement_attributes.age_cohort' => ['nullable', 'string', 'max:40'],
            'measurement_attributes.disability_status' => ['nullable'],
            'measurement_attributes.refugee_or_displaced_status' => ['nullable'],
            'measurement_attributes.rural_urban' => ['nullable', 'string', 'max:40'],
            'measurement_attributes.employment_category' => ['nullable', 'string', 'max:80'],
            'measurement_attributes.first_time_formal_borrower' => ['nullable', 'boolean'],
            'service_preferences' => ['sometimes', 'array'],
        ]);

        return $this->guard(fn () => $this->service->updateProfile($request->user(), $validated));
    }

    public function capability(Request $request): JsonResponse
    {
        return ApiResponse::success('Financial-capability guidance loaded.', $this->service->capability($request->user()));
    }

    public function recordCapabilityEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'event_type' => ['required', Rule::in(['guidance_shown', 'guidance_opened', 'action_taken', 'dismissed', 'completed', 'education_viewed'])],
            'intervention_code' => ['nullable', 'string', 'max:100'],
            'context' => ['nullable', 'array'],
            'outcome' => ['nullable', 'array'],
            'guidance_version' => ['nullable', 'string', 'max:80'],
        ]);

        return ApiResponse::success('Financial-capability event recorded.', $this->service->recordCapabilityEvent($request->user(), $validated), 201);
    }

    public function reputation(Request $request): JsonResponse
    {
        return ApiResponse::success('Financial-reputation pathway loaded.', $this->service->reputation($request->user()));
    }

    public function signals(Request $request): JsonResponse
    {
        return ApiResponse::success('Alternative-data signals loaded.', $this->service->signals($request->user()));
    }

    public function storeSignal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'signal_key' => ['required', 'string', 'max:120'],
            'signal_value' => ['required'],
            'purpose' => ['required', Rule::in(['service_adaptation', 'programme_measurement', 'financial_capability'])],
            'channel' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success('Customer signal recorded outside credit decisioning.', $this->service->storeUserSignal($request->user(), $validated), 201);
    }

    public function programmes(): JsonResponse
    {
        return ApiResponse::success('Open inclusive-finance programmes loaded.', $this->service->programmes());
    }

    public function enrol(Request $request, int $programme): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'eligibility_evidence' => ['nullable', 'array'],
        ]);

        return $this->guard(fn () => $this->service->enrol($request->user(), $programme, $validated), 201);
    }

    public function supportInstruments(Request $request): JsonResponse
    {
        return ApiResponse::success('Credit-support instruments loaded.', $this->service->supportInstruments($request->user()));
    }

    public function storeSupportInstrument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'loan_application_id' => ['nullable', 'integer', 'exists:loan_applications,id'],
            'instrument_type' => ['required', Rule::in(InclusiveFinanceService::SUPPORT_INSTRUMENT_TYPES)],
            'provider_name' => ['nullable', 'string', 'max:160'],
            'external_reference' => ['nullable', 'string', 'max:160'],
            'value_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'evidence' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success('Credit-support evidence recorded for verification.', $this->service->storeSupportInstrument($request->user(), $validated), 201);
    }

    public function fairTreatment(Request $request): JsonResponse
    {
        return ApiResponse::success('Fair-treatment information loaded.', $this->service->fairTreatment($request->user()));
    }

    public function adminImpact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success('Inclusive-finance impact summary loaded.', $this->service->impactSummary($validated['programme_id'] ?? null));
    }

    public function createProgramme(Request $request): JsonResponse
    {
        $validated = $this->programmeValidation($request);

        return $this->guard(fn () => $this->service->createProgramme($validated), 201);
    }

    public function updateProgramme(Request $request, int $programme): JsonResponse
    {
        $validated = $this->programmeValidation($request, true);

        return $this->guard(fn () => $this->service->updateProgramme($programme, $validated));
    }

    public function ingestSignal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'financial_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'source_type' => ['required', Rule::in(['mno', 'crb', 'employer', 'partner', 'transactional', 'warehouse', 'other_verified'])],
            'signal_key' => ['required', 'string', 'max:120'],
            'signal_value' => ['required'],
            'purpose' => ['nullable', Rule::in(['credit_assessment', 'affordability', 'financial_capability', 'programme_measurement'])],
            'consent_record_id' => ['nullable', 'integer'],
            'provider_reference' => ['required', 'string', 'max:200'],
            'provenance' => ['nullable', 'array'],
            'observed_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        return $this->guard(fn () => $this->service->ingestProviderSignal($validated, $request->user()), 201);
    }

    public function verifySignal(Request $request, int $signal): JsonResponse
    {
        $validated = $request->validate(['risk_eligible' => ['required', 'boolean']]);

        return $this->guard(fn () => $this->service->verifySignal($signal, (bool) $validated['risk_eligible'], $request->user()));
    }

    public function verifySupportInstrument(Request $request, int $instrument): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
        ]);

        return $this->guard(fn () => $this->service->verifySupportInstrument($instrument, $validated['status'], $request->user()));
    }

    public function assessApplication(int $application): JsonResponse
    {
        return $this->guard(fn () => $this->service->assessApplication($application));
    }

    private function programmeValidation(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$required, 'string', 'max:80'],
            'name' => [$required, 'string', 'max:180'],
            'sponsor_space_id' => ['nullable', 'integer', 'exists:financial_spaces,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused', 'closed'])],
            'target_population' => ['nullable', 'array'],
            'eligibility_rules' => ['nullable', 'array'],
            'product_config' => ['nullable', 'array'],
            'reporting_config' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function guard(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return ApiResponse::success('Inclusive-finance operation completed.', $callback(), $status);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }
}
