<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerFinancialIntentRequest;
use App\Models\User;
use App\Services\FinancingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class PartnerFinancialIntentController extends Controller
{
    public function __construct(private readonly FinancingService $financing) {}

    public function store(Request $request, int $customer): JsonResponse
    {
        $idempotency = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotency === '' || strlen($idempotency) > 180) {
            return ApiResponse::error('A valid Idempotency-Key header is required.', 422);
        }

        $validated = $request->validate([
            'partner_account_id' => ['required', 'integer', 'exists:partner_distribution_accounts,id'],
            'source_platform' => ['required', Rule::in(['stolets', 'shamba', 'coreworks'])],
            'external_reference' => ['required', 'string', 'max:160'],
            'need_type' => ['required', 'string', 'max:80'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'purpose' => ['nullable', 'array'],
            'customer_consent_reference' => ['required', 'string', 'max:180'],
            'metadata' => ['nullable', 'array'],
        ]);

        $partner = $this->assertPartnerAccess($request, (int) $validated['partner_account_id']);
        if (! $this->partnerAllowsFinancialIntent($partner->allowed_products ?? null)) {
            return ApiResponse::error('This partner account is not enabled for financial-intent referrals.', 403);
        }
        $customerModel = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($customer);

        $record = DB::transaction(function () use ($validated, $idempotency, $customerModel) {
            $existing = PartnerFinancialIntentRequest::query()
                ->where('partner_account_id', (int) $validated['partner_account_id'])
                ->where(function ($query) use ($validated, $idempotency) {
                    $query->where('idempotency_key', $idempotency)
                        ->orWhere('external_reference', (string) $validated['external_reference']);
                })
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            return PartnerFinancialIntentRequest::create([
                'reference' => (string) Str::uuid(),
                'partner_account_id' => (int) $validated['partner_account_id'],
                'customer_user_id' => $customerModel->id,
                'source_platform' => (string) $validated['source_platform'],
                'external_reference' => (string) $validated['external_reference'],
                'idempotency_key' => $idempotency,
                'need_type' => (string) $validated['need_type'],
                'amount_minor' => $validated['amount_minor'] ?? null,
                'currency' => strtoupper((string) ($validated['currency'] ?? 'UGX')),
                'purpose' => $validated['purpose'] ?? null,
                'customer_consent_reference' => (string) $validated['customer_consent_reference'],
                'status' => 'customer_confirmation_pending',
                'expires_at' => now()->addDays(7),
                'metadata' => array_merge((array) ($validated['metadata'] ?? []), [
                    'customer_confirmation_required' => true,
                ]),
            ]);
        });

        return ApiResponse::success('Financial intent referral recorded; customer confirmation in OpFin is required.', [
            'request' => $this->partnerPayload($record),
            'customer_confirmation_required' => true,
        ], 201);
    }

    public function customerIndex(Request $request): JsonResponse
    {
        $records = PartnerFinancialIntentRequest::query()
            ->where('customer_user_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (PartnerFinancialIntentRequest $record) => $this->customerPayload($record));

        return ApiResponse::success('Partner financial intent requests loaded.', ['requests' => $records->values()->all()]);
    }

    public function confirm(Request $request, PartnerFinancialIntentRequest $partnerRequest): JsonResponse
    {
        $this->assertCustomer($request, $partnerRequest);
        $validated = $request->validate([
            'financial_space_id' => ['required', 'integer', 'exists:financial_spaces,id'],
            'principles_preference' => ['nullable', Rule::in(FinancingService::PREFERENCES)],
        ]);

        try {
            $intent = DB::transaction(function () use ($request, $partnerRequest, $validated) {
                $locked = PartnerFinancialIntentRequest::query()->whereKey($partnerRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'confirmed' && $locked->confirmed_financial_intent_id) {
                    return \App\Models\FinancialIntent::findOrFail($locked->confirmed_financial_intent_id);
                }
                if ($locked->status !== 'customer_confirmation_pending') {
                    throw new InvalidArgumentException('This partner financial intent request is no longer awaiting confirmation.');
                }
                if ($locked->expires_at && $locked->expires_at->isPast()) {
                    $locked->update(['status' => 'expired']);
                    throw new InvalidArgumentException('This partner financial intent request has expired.');
                }

                $purpose = array_merge((array) ($locked->purpose ?? []), [
                    'source_platform' => $locked->source_platform,
                    'partner_account_id' => $locked->partner_account_id,
                    'partner_request_reference' => $locked->reference,
                    'external_reference' => $locked->external_reference,
                ]);
                $intent = $this->financing->createIntent($request->user(), [
                    'financial_space_id' => (int) $validated['financial_space_id'],
                    'need_type' => $locked->need_type,
                    'principles_preference' => $validated['principles_preference'] ?? 'ALL_SUITABLE',
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'purpose' => $purpose,
                    'expires_at' => now()->addDays(30),
                ]);

                $locked->update([
                    'status' => 'confirmed',
                    'confirmed_financial_intent_id' => $intent->id,
                    'confirmed_financial_space_id' => (int) $validated['financial_space_id'],
                    'confirmed_at' => now(),
                ]);

                return $intent;
            });
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Financial intent confirmed. Product matching remains customer-controlled in OpFin.', [
            'financial_intent' => $intent,
            'product_match_required' => true,
        ], 201);
    }

    public function decline(Request $request, PartnerFinancialIntentRequest $partnerRequest): JsonResponse
    {
        $this->assertCustomer($request, $partnerRequest);
        DB::transaction(function () use ($partnerRequest) {
            $locked = PartnerFinancialIntentRequest::query()->whereKey($partnerRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'customer_confirmation_pending') {
                $locked->update(['status' => 'declined', 'declined_at' => now()]);
            }
        });

        return ApiResponse::success('Financial intent referral declined.', [
            'request' => $this->customerPayload($partnerRequest->fresh()),
        ]);
    }

    private function assertPartnerAccess(Request $request, int $partnerAccountId): object
    {
        $query = DB::table('partner_distribution_accounts')
            ->where('id', $partnerAccountId)
            ->whereIn('status', ['active', 'approved']);
        if (! in_array($request->user()->role, [User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS], true)) {
            $query->where('created_by', $request->user()->id);
        }
        $partner = $query->first();
        abort_unless($partner, 403);

        return $partner;
    }

    private function partnerAllowsFinancialIntent(mixed $allowed): bool
    {
        if (is_string($allowed)) {
            $decoded = json_decode($allowed, true);
            $allowed = json_last_error() === JSON_ERROR_NONE ? $decoded : [$allowed];
        }
        $values = array_map('strtolower', array_filter((array) $allowed, 'is_string'));

        return count(array_intersect($values, ['finance', 'credit', 'financial_intents'])) > 0;
    }

    private function assertCustomer(Request $request, PartnerFinancialIntentRequest $partnerRequest): void
    {
        abort_unless((int) $partnerRequest->customer_user_id === (int) $request->user()->id, 404);
    }

    private function partnerPayload(PartnerFinancialIntentRequest $record): array
    {
        return [
            'reference' => $record->reference,
            'external_reference' => $record->external_reference,
            'source_platform' => $record->source_platform,
            'status' => $record->status,
            'expires_at' => $record->expires_at?->toIso8601String(),
            'confirmed_at' => $record->confirmed_at?->toIso8601String(),
        ];
    }

    private function customerPayload(PartnerFinancialIntentRequest $record): array
    {
        return [
            'id' => $record->id,
            'reference' => $record->reference,
            'source_platform' => $record->source_platform,
            'need_type' => $record->need_type,
            'amount_minor' => $record->amount_minor,
            'currency' => $record->currency,
            'purpose' => $record->purpose,
            'status' => $record->status,
            'expires_at' => $record->expires_at?->toIso8601String(),
            'confirmed_at' => $record->confirmed_at?->toIso8601String(),
            'declined_at' => $record->declined_at?->toIso8601String(),
        ];
    }
}
