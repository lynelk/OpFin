<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancingApplication;
use App\Models\PayrollDeductionCase;
use App\Services\PayrollDeduction\PayrollDeductionProviderManager;
use App\Services\PayrollDeduction\PayrollDeductionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayrollDeductionController extends Controller
{
    public function __construct(
        private readonly PayrollDeductionService $payroll,
        private readonly PayrollDeductionProviderManager $providers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cases = PayrollDeductionCase::query()
            ->where('user_id', $request->user()->id)
            ->with('reconciliations')
            ->latest()
            ->get()
            ->map(fn (PayrollDeductionCase $case) => $this->customerPayload($case));

        return ApiResponse::success('Payroll deduction cases loaded.', ['cases' => $cases->values()->all()]);
    }

    public function show(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $this->assertCustomer($request, $case);
        $case->load('reconciliations');

        return ApiResponse::success('Payroll deduction case loaded.', ['case' => $this->customerPayload($case)]);
    }

    public function providerCapability(): JsonResponse
    {
        return ApiResponse::success('Payroll deduction provider capability loaded.', [
            'provider' => $this->providers->provider()->capability(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$idempotency, $correlation] = $this->keys($request);
        $data = $request->validate([
            'financing_application_id' => ['required', 'integer'],
            'scheme' => ['nullable', 'in:government_pdms'],
            'provider' => ['nullable', 'in:pdms'],
            'vote_code' => ['nullable', 'string', 'max:80'],
            'vote_name' => ['nullable', 'string', 'max:180'],
            'employment_reference' => ['nullable', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $application = FinancingApplication::where('user_id', $request->user()->id)
            ->findOrFail($data['financing_application_id']);

        try {
            $case = $this->payroll->createCase($request->user(), $application, $data, $idempotency, $correlation);

            return ApiResponse::success('Payroll deduction process started.', ['case' => $this->customerPayload($case)], 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function undertaking(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $this->assertCustomer($request, $case);
        [$idempotency, $correlation] = $this->keys($request);
        $data = $request->validate([
            'requested_deduction_minor' => ['required', 'integer', 'min:1'],
            'authorised' => ['required', 'accepted'],
            'provider_agreement_reference' => ['nullable', 'string', 'max:180'],
        ]);
        $data['authorised'] = true;
        try {
            $case = $this->payroll->grantUndertaking($case, $request->user(), $data, $idempotency, $correlation);

            return ApiResponse::success('Payroll undertaking and reservation request recorded.', [
                'case' => $this->customerPayload($case), 'consent' => ['id' => $case->undertaking_consent_record_id],
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function requestReservation(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $this->assertCustomer($request, $case);
        [$idempotency, $correlation] = $this->keys($request);
        $data = $request->validate([
            'requested_deduction_minor' => ['required', 'integer', 'min:1'],
            'undertaking_consent_record_id' => ['required', 'integer'],
            'provider_agreement_reference' => ['nullable', 'string', 'max:180'],
        ]);

        try {
            $case = $this->payroll->requestReservation($case, $request->user(), $data, $idempotency, $correlation);

            return ApiResponse::success('Payroll reservation requested.', ['case' => $this->customerPayload($case)]);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function cancel(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $this->assertCustomer($request, $case);
        [$idempotency, $correlation] = $this->keys($request);

        try {
            $case = $this->payroll->cancel($case, $request->user(), $idempotency, $correlation);
            $message = $case->status === 'cancellation_pending'
                ? 'Payroll deduction cancellation is awaiting provider release confirmation.'
                : 'Payroll deduction process cancelled.';

            return ApiResponse::success($message, ['case' => $this->customerPayload($case)]);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function operationsIndex(Request $request): JsonResponse
    {
        $query = PayrollDeductionCase::query()->with(['events', 'reconciliations'])->latest();
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('provider')) {
            $query->where('provider', (string) $request->query('provider'));
        }

        return ApiResponse::success('Payroll deduction operations queue loaded.', [
            'cases' => $query->limit(200)->get()->toArray(),
        ]);
    }

    public function operationsShow(PayrollDeductionCase $case): JsonResponse
    {
        return ApiResponse::success('Payroll deduction operations case loaded.', [
            'case' => $case->load(['events', 'reconciliations'])->toArray(),
        ]);
    }

    public function affordability(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'affordable' => ['required', 'boolean'],
            'affordable_amount_minor' => ['nullable', 'integer', 'min:0'],
            'buyoff_quote_required' => ['nullable', 'boolean'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->recordAffordability($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function reservation(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'reserved' => ['required', 'boolean'],
            'reservation_reference' => ['nullable', 'string', 'max:180'],
            'provider_agreement_reference' => ['nullable', 'string', 'max:180'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
            'rejection_code' => ['nullable', 'string', 'max:120'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->recordReservation($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function keyFacts(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'key_facts' => ['required', 'array'],
            'key_facts.version' => ['nullable', 'string', 'max:80'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->submitKeyFacts($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function voteDecision(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
            'rejection_code' => ['nullable', 'string', 'max:120'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->recordVoteDecision($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function payrollSubmission(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'payroll_period' => ['required', 'date_format:Y-m'],
            'submission_file_reference' => ['nullable', 'string', 'max:180'],
            'submission_code' => ['nullable', 'in:482'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->recordPayrollSubmission($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function payrollResult(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'payroll_period' => ['required', 'date_format:Y-m'],
            'result_category' => ['required', 'in:success,rejected,off_payroll_lt_3_months,off_payroll_ge_3_months'],
            'recovered_minor' => ['nullable', 'integer', 'min:0'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
            'rejection_code' => ['nullable', 'string', 'max:120'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'array'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->recordPayrollResult($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function amend(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'requested_deduction_minor' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->amendAfterReject($case, $request->user(), $data, $idempotency, $correlation));
    }

    public function cancellationRelease(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'released' => ['required', 'boolean'],
            'release_reference' => ['nullable', 'string', 'max:180'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
            'evidence' => ['nullable', 'array'],
        ]);

        return $this->operationsMutation(
            $request,
            $case,
            fn ($idempotency, $correlation) => $this->payroll->confirmCancellationRelease(
                $case,
                $request->user(),
                $data,
                $idempotency,
                $correlation,
            )
        );
    }

    public function reconcile(Request $request, PayrollDeductionCase $case): JsonResponse
    {
        $data = $request->validate([
            'payroll_period' => ['required', 'date_format:Y-m'],
            'recovered_minor' => ['required', 'integer', 'min:0'],
            'provider_reference' => ['nullable', 'string', 'max:180'],
            'evidence' => ['nullable', 'array'],
        ]);

        return $this->operationsMutation($request, $case, fn ($idempotency, $correlation) => $this->payroll->reconcile($case, $request->user(), $data, $idempotency, $correlation));
    }

    private function operationsMutation(Request $request, PayrollDeductionCase $case, callable $callback): JsonResponse
    {
        [$idempotency, $correlation] = $this->keys($request);
        try {
            $case = $callback($idempotency, $correlation);

            return ApiResponse::success('Payroll deduction case updated.', [
                'case' => $case->load(['events', 'reconciliations'])->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    private function keys(Request $request): array
    {
        $idempotency = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotency === '' || strlen($idempotency) > 180) {
            abort(422, 'A valid Idempotency-Key header is required.');
        }
        $correlation = trim((string) $request->header('X-Correlation-ID', ''));
        if ($correlation !== '' && ! Str::isUuid($correlation)) {
            abort(422, 'X-Correlation-ID must be a UUID when supplied.');
        }

        return [$idempotency, $correlation !== '' ? $correlation : null];
    }

    private function assertCustomer(Request $request, PayrollDeductionCase $case): void
    {
        abort_unless((int) $case->user_id === (int) $request->user()->id, 404);
    }

    private function customerPayload(PayrollDeductionCase $case): array
    {
        return [
            'id' => $case->id,
            'reference' => $case->reference,
            'financing_application_id' => $case->financing_application_id,
            'scheme' => $case->scheme,
            'provider' => $case->provider,
            'vote_name' => $case->vote_name,
            'status' => $case->status,
            'affordability_status' => $case->affordability_status,
            'affordable_amount_minor' => $case->affordable_amount_minor,
            'requested_deduction_minor' => $case->requested_deduction_minor,
            'currency' => $case->currency,
            'provider_agreement_reference' => $case->provider_agreement_reference,
            'reservation_reference' => $case->reservation_reference,
            'reservation_expires_at' => $case->reservation_expires_at?->toIso8601String(),
            'rejection_code' => $case->rejection_code,
            'rejection_reason' => $case->rejection_reason,
            'approved_at' => $case->approved_at?->toIso8601String(),
            'payroll_submitted_at' => $case->payroll_submitted_at?->toIso8601String(),
            'reconciled_at' => $case->reconciled_at?->toIso8601String(),
            'reconciliations' => $case->relationLoaded('reconciliations')
                ? $case->reconciliations->map(fn ($item) => [
                    'payroll_period' => $item->payroll_period,
                    'submission_attempt' => $item->submission_attempt,
                    'expected_minor' => $item->expected_minor,
                    'recovered_minor' => $item->recovered_minor,
                    'variance_minor' => $item->variance_minor,
                    'currency' => $item->currency,
                    'status' => $item->status,
                    'result_category' => $item->result_category,
                ])->values()->all()
                : [],
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }
}
