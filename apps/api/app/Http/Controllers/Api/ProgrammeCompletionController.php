<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommercialInsightsService;
use App\Services\FinancialHealthEnrichmentService;
use App\Services\InclusiveImpactService;
use App\Services\ProgrammeDeliveryService;
use App\Services\ProgrammeProviderAdapterService;
use App\Services\ProgrammeReportExportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ProgrammeCompletionController extends Controller
{
    public function __construct(
        private readonly ProgrammeDeliveryService $delivery,
        private readonly CommercialInsightsService $commercial,
        private readonly FinancialHealthEnrichmentService $enrichment,
        private readonly ProgrammeProviderAdapterService $adapters,
        private readonly ProgrammeReportExportService $exports,
        private readonly AuditLogger $audit,
    ) {}

    public function dueCheckIns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(ProgrammeDeliveryService::CHANNELS)],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        return ApiResponse::success(
            'Programme check-ins loaded.',
            $this->delivery->dueInstruments(
                $request->user(),
                $validated['channel'] ?? 'app',
                $validated['locale'] ?? null,
            ),
        );
    }

    public function submitCheckIn(Request $request, int $instrument): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['nullable', 'integer', 'exists:programme_follow_up_schedules,id'],
            'channel' => ['required', Rule::in(['app', 'web', 'whatsapp', 'ussd'])],
            'locale' => ['nullable', 'string', 'max:16'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:programme_instrument_questions,id'],
            'answers.*.value' => ['present'],
        ]);

        return $this->audited(
            'programme.check_in.submitted',
            $request,
            fn () => $this->delivery->submitResponse(
                $request->user(),
                $instrument,
                $validated['answers'],
                $validated['channel'],
                $validated['locale'] ?? null,
                $validated['schedule_id'] ?? null,
            ),
            ['instrument_id' => $instrument, 'channel' => $validated['channel']],
            201,
        );
    }

    public function openFollowUp(Request $request, int $schedule): JsonResponse
    {
        $this->delivery->markOpened($request->user(), $schedule);

        return ApiResponse::success('Programme follow-up opened.', ['schedule_id' => $schedule]);
    }

    public function enrichmentPreview(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Financial-health enrichment preview loaded.',
            $this->enrichment->preview($request->user()),
        );
    }

    public function recordEnrichment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return $this->audited(
            'financial_health.enrichment.recorded',
            $request,
            fn () => $this->enrichment->record($request->user(), $validated['programme_id'] ?? null),
            ['programme_id' => $validated['programme_id'] ?? null],
            201,
        );
    }

    public function instruments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Programme instruments loaded.',
            $this->delivery->adminInstruments($validated['programme_id'] ?? null),
        );
    }

    public function createInstrument(Request $request): JsonResponse
    {
        $validated = $this->instrumentValidation($request);

        return $this->audited(
            'programme.instrument.created',
            $request,
            fn () => $this->delivery->createInstrument($validated, $request->user()),
            ['programme_id' => $validated['programme_id'], 'instrument_code' => $validated['code']],
            201,
        );
    }

    public function updateInstrument(Request $request, int $instrument): JsonResponse
    {
        $validated = $this->instrumentValidation($request, true);

        return $this->audited(
            'programme.instrument.updated',
            $request,
            fn () => $this->delivery->updateInstrument($instrument, $validated, $request->user()),
            ['instrument_id' => $instrument],
        );
    }

    public function addQuestion(Request $request, int $instrument): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'prompt' => ['required', 'string', 'max:2000'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'indicator_definition_id' => ['nullable', 'integer', 'exists:impact_indicator_definitions,id'],
            'answer_type' => ['required', Rule::in(ProgrammeDeliveryService::ANSWER_TYPES)],
            'required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:500'],
            'translated_options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'array'],
            'verification_source' => ['nullable', Rule::in(['self_reported', 'system', 'provider', 'administrative'])],
        ]);

        return $this->audited(
            'programme.instrument_question.created',
            $request,
            fn () => $this->delivery->addQuestion($instrument, $validated),
            ['instrument_id' => $instrument, 'question_code' => $validated['code']],
            201,
        );
    }

    public function upsertTranslation(Request $request, int $question): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(ProgrammeDeliveryService::LOCALES)],
            'prompt' => ['required', 'string', 'max:2000'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array'],
        ]);

        return $this->audited(
            'programme.question_translation.updated',
            $request,
            fn () => $this->delivery->upsertTranslation($question, $validated),
            ['question_id' => $question, 'locale' => $validated['locale']],
        );
    }

    public function generateFollowUps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'instrument_id' => ['nullable', 'integer', 'exists:programme_instruments,id'],
        ]);

        return ApiResponse::success(
            'Programme follow-up schedule generated.',
            $this->delivery->generateFollowUps(
                $validated['programme_id'] ?? null,
                $validated['instrument_id'] ?? null,
            ),
        );
    }

    public function operations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Programme operations loaded.',
            $this->delivery->operationsSummary($validated['programme_id'] ?? null),
        );
    }

    public function assistedCapture(Request $request, int $instrument): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:programme_follow_up_schedules,id'],
            'locale' => ['nullable', 'string', 'max:16'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:programme_instrument_questions,id'],
            'answers.*.value' => ['present'],
        ]);

        $subject = User::withoutGlobalScopes()->findOrFail($validated['user_id']);

        return $this->audited(
            'programme.check_in.assisted',
            $request,
            fn () => $this->delivery->submitResponse(
                $subject,
                $instrument,
                $validated['answers'],
                'assisted',
                $validated['locale'] ?? null,
                $validated['schedule_id'] ?? null,
                $request->user(),
            ),
            ['instrument_id' => $instrument, 'subject_user_id' => $subject->id],
            201,
        );
    }

    public function templates(): JsonResponse
    {
        return ApiResponse::success('Programme templates loaded.', $this->delivery->templates());
    }

    public function applyTemplate(Request $request, int $programme): JsonResponse
    {
        $validated = $request->validate([
            'template_code' => ['required', Rule::in(ProgrammeDeliveryService::TEMPLATE_CODES)],
        ]);

        return $this->audited(
            'programme.template.applied',
            $request,
            fn () => $this->delivery->applyTemplate($programme, $validated['template_code'], $request->user()),
            ['programme_id' => $programme, 'template_code' => $validated['template_code']],
            201,
        );
    }

    public function invitePartner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['required', 'integer', 'exists:inclusive_finance_programmes,id'],
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'invited_name' => ['required', 'string', 'max:180'],
            'invited_phone' => ['nullable', 'string', 'max:30'],
            'invited_email' => ['nullable', 'email', 'max:255'],
            'access_level' => ['required', Rule::in(InclusiveImpactService::PARTNER_ACCESS_LEVELS)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        return $this->audited(
            'programme.partner_invitation.created',
            $request,
            fn () => $this->delivery->createPartnerInvitation($validated, $request->user()),
            ['programme_id' => $validated['programme_id'], 'partner_id' => $validated['partner_id']],
            201,
        );
    }

    public function acceptPartnerInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:40', 'max:200'],
            'name' => ['required', 'string', 'max:180'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'pin' => ['required', 'digits:6', 'confirmed'],
            'preferred_language' => ['nullable', 'string', 'max:16'],
        ]);

        try {
            return ApiResponse::success(
                'Programme-partner account activated.',
                $this->delivery->acceptPartnerInvitation($validated),
                201,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function revokePartnerAccess(Request $request, int $programme, int $user): JsonResponse
    {
        return $this->audited(
            'programme.partner_access.revoked',
            $request,
            fn () => $this->delivery->revokePartnerAccess($programme, $user),
            ['programme_id' => $programme, 'subject_user_id' => $user],
        );
    }

    public function recordAttribution(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'acquisition_channel' => ['required', Rule::in(CommercialInsightsService::ACQUISITION_CHANNELS)],
            'source' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:160'],
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'acquired_at' => ['nullable', 'date', 'before_or_equal:now'],
            'metadata' => ['nullable', 'array'],
        ]);
        $subject = User::withoutGlobalScopes()->findOrFail($user);

        return $this->audited(
            'commercial.attribution.recorded',
            $request,
            fn () => $this->commercial->recordAttribution($subject, $validated, $request->user()),
            ['subject_user_id' => $subject->id, 'acquisition_channel' => $validated['acquisition_channel']],
        );
    }

    public function recordCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cost_type' => ['required', Rule::in(CommercialInsightsService::COST_TYPES)],
            'channel' => ['nullable', Rule::in(CommercialInsightsService::ACQUISITION_CHANNELS)],
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'source_reference' => ['nullable', 'string', 'max:200'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->audited(
            'commercial.cost.recorded',
            $request,
            fn () => $this->commercial->recordCost($validated, $request->user()),
            ['cost_type' => $validated['cost_type']],
            201,
        );
    }

    public function commercialDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'channel' => ['nullable', Rule::in(CommercialInsightsService::ACQUISITION_CHANNELS)],
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Commercial performance loaded.',
            $this->commercial->dashboard(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $validated['channel'] ?? null,
                $validated['programme_id'] ?? null,
            ),
        );
    }

    public function evaluateGraduation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Commercial graduation evaluation completed.',
            $this->commercial->evaluateGraduations($validated['programme_id'] ?? null),
        );
    }

    public function graduationSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Commercial graduation summary loaded.',
            $this->commercial->graduationSummary($validated['programme_id'] ?? null),
        );
    }

    public function adapters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
        ]);

        return ApiResponse::success(
            'Programme provider adapters loaded.',
            $this->adapters->adapters($validated['programme_id'] ?? null),
        );
    }

    public function configureAdapter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:programme_provider_adapters,id'],
            'programme_id' => ['nullable', 'integer', 'exists:inclusive_finance_programmes,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:180'],
            'adapter_type' => ['required', Rule::in(ProgrammeProviderAdapterService::ADAPTER_TYPES)],
            'status' => ['nullable', Rule::in(['draft', 'ready', 'active', 'disabled'])],
            'purpose' => ['nullable', Rule::in(ProgrammeProviderAdapterService::PURPOSES)],
            'allowed_signal_keys' => ['required', 'array', 'min:1'],
            'allowed_signal_keys.*' => ['string', 'max:120'],
            'signal_mapping' => ['nullable', 'array'],
            'requires_credit_processing_consent' => ['nullable', 'boolean'],
            'credentials_configured' => ['nullable', 'boolean'],
            'legal_basis_confirmed' => ['nullable', 'boolean'],
            'activation_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        return $this->audited(
            'programme.provider_adapter.configured',
            $request,
            fn () => $this->adapters->configure($validated, $request->user()),
            ['adapter_code' => $validated['code']],
            201,
        );
    }

    public function ingestAdapter(Request $request, int $adapter): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'provider_reference' => ['required', 'string', 'max:200'],
            'signals' => ['required', 'array', 'min:1'],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $subject = User::withoutGlobalScopes()->findOrFail($validated['user_id']);

        return $this->audited(
            'programme.provider_adapter.ingested',
            $request,
            fn () => $this->adapters->ingest($adapter, $subject, $validated, $request->user()),
            ['adapter_id' => $adapter, 'subject_user_id' => $subject->id],
            201,
        );
    }

    public function exportProgramme(Request $request, int $programme, string $format): Response
    {
        if (! in_array($format, ['csv', 'xlsx', 'zip'], true)) {
            return ApiResponse::error('Unsupported programme export format.', 404);
        }

        try {
            [$bytes, $contentType, $extension] = match ($format) {
                'csv' => [$this->exports->csv($programme), 'text/csv; charset=UTF-8', 'csv'],
                'xlsx' => [$this->exports->xlsx($programme), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
                default => [$this->exports->reportPack($programme), 'application/zip', 'zip'],
            };
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        $this->audit->record(
            'programme.export.generated',
            $request->user(),
            null,
            ['programme_id' => $programme, 'format' => $format],
            $request,
        );

        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="opfin-programme-'.$programme.'-'.now()->format('Ymd').'.'.$extension.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function partnerExport(Request $request, int $programme, string $format): Response
    {
        $access = DB::table('programme_partner_access')
            ->where('programme_id', $programme)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();

        if (! $access) {
            return ApiResponse::error('Programme access is not available for this partner account.', 403);
        }

        return $this->exportProgramme($request, $programme, $format);
    }

    private function instrumentValidation(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'programme_id' => [$required, 'integer', 'exists:inclusive_finance_programmes,id'],
            'code' => [$required, 'string', 'max:100'],
            'name' => [$required, 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'outcome_domain' => [$required, Rule::in(InclusiveImpactService::OUTCOME_DOMAINS)],
            'default_measurement_stage' => ['nullable', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'consent_classification' => ['nullable', Rule::in(ProgrammeDeliveryService::CONSENT_CLASSIFICATIONS)],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => [Rule::in(ProgrammeDeliveryService::CHANNELS)],
            'default_locale' => ['nullable', Rule::in(ProgrammeDeliveryService::LOCALES)],
            'supported_locales' => ['nullable', 'array'],
            'supported_locales.*' => [Rule::in(ProgrammeDeliveryService::LOCALES)],
            'schedule_config' => ['nullable', 'array'],
            'schedule_config.*.stage' => ['required_with:schedule_config', Rule::in(InclusiveImpactService::MEASUREMENT_STAGES)],
            'schedule_config.*.offset_days' => ['required_with:schedule_config', 'integer', 'min:0', 'max:3650'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'retired'])],
            'version' => ['nullable', 'string', 'max:40'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
        ]);
    }

    private function audited(
        string $event,
        Request $request,
        callable $callback,
        array $metadata = [],
        int $status = 200,
    ): JsonResponse {
        try {
            $result = $callback();
            $this->audit->record($event, $request->user(), null, $metadata, $request);

            return ApiResponse::success('Programme/commercial operation completed.', $result, $status);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }
}
