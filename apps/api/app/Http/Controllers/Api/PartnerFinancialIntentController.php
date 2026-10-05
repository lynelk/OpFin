<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialIntent;
use App\Models\PartnerFinancialIntentRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FinancingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PartnerFinancialIntentController extends Controller
{
    public function __construct(private readonly FinancingService $financing, private readonly AuditLogger $audit) {}

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
        if (($partner->financial_intent_source_platform ?? null) !== $validated['source_platform']) {
            return ApiResponse::error('The requested source platform is not approved for this partner account.', 403);
        }
        $customerModel = User::withoutGlobalScopes()->whereNull('deleted_at')->where('role', User::ROLE_CUSTOMER)->findOrFail($customer);

        $record = DB::transaction(function () use ($request, $validated, $idempotency, $customerModel) {
            User::withoutGlobalScopes()->whereKey($customerModel->id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
            $currentPartner = DB::table('partner_distribution_accounts')->where('id', $validated['partner_account_id'])->lockForUpdate()->first();
            abort_unless($currentPartner && in_array($currentPartner->status, ['active', 'approved'], true)
                && $currentPartner->financial_intent_source_platform === $validated['source_platform']
                && $this->partnerAllowsFinancialIntent($currentPartner->allowed_products), 403);
            $existing = PartnerFinancialIntentRequest::query()
                ->where('partner_account_id', (int) $validated['partner_account_id'])
                ->where(function ($query) use ($validated, $idempotency) {
                    $query->where('idempotency_key', $idempotency)
                        ->orWhere('external_reference', (string) $validated['external_reference']);
                })
                ->lockForUpdate()
                ->first();

            $requestPayload = [
                'customer_user_id' => $customerModel->id,
                'source_platform' => (string) $validated['source_platform'],
                'external_reference' => (string) $validated['external_reference'],
                'need_type' => (string) $validated['need_type'],
                'amount_minor' => isset($validated['amount_minor']) ? (int) $validated['amount_minor'] : null,
                'currency' => strtoupper((string) ($validated['currency'] ?? 'UGX')),
                'purpose' => $validated['purpose'] ?? null,
                'customer_consent_reference' => (string) $validated['customer_consent_reference'],
                'metadata' => array_merge((array) ($validated['metadata'] ?? []), [
                    'customer_confirmation_required' => true,
                ]),
            ];

            if ($existing) {
                $existingPayload = [
                    'customer_user_id' => (int) $existing->customer_user_id,
                    'source_platform' => $existing->source_platform,
                    'external_reference' => $existing->external_reference,
                    'need_type' => $existing->need_type,
                    'amount_minor' => $existing->amount_minor !== null ? (int) $existing->amount_minor : null,
                    'currency' => $existing->currency,
                    'purpose' => $existing->purpose,
                    'customer_consent_reference' => $existing->customer_consent_reference,
                    'metadata' => $existing->metadata,
                ];

                if ($this->normalisePayload($existingPayload) !== $this->normalisePayload($requestPayload)) {
                    throw new ConflictHttpException(
                        'The Idempotency-Key or external reference is already bound to a different financial-intent request.'
                    );
                }

                return $existing;
            }

            $created = PartnerFinancialIntentRequest::create([
                'reference' => (string) Str::uuid(),
                'partner_account_id' => (int) $validated['partner_account_id'],
                'idempotency_key' => $idempotency,
                'status' => 'customer_confirmation_pending',
                'expires_at' => now()->addDays(7),
                ...$requestPayload,
            ]);
            $this->audit->record('partner.financial_intent.referred', $request->user(), $created, [
                'customer_id' => $customerModel->id, 'source_platform' => $created->source_platform,
                'partner_account_id' => $created->partner_account_id,
            ], $request);

            return $created;
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
                User::withoutGlobalScopes()->whereKey($request->user()->id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();
                $this->financing->assertSpaceAuthority($request->user(), (int) $validated['financial_space_id']);
                $locked = PartnerFinancialIntentRequest::query()->whereKey($partnerRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'confirmed' && $locked->confirmed_financial_intent_id) {
                    $existing = FinancialIntent::findOrFail($locked->confirmed_financial_intent_id);
                    if ((int) $existing->financial_space_id !== (int) $validated['financial_space_id']
                        || $existing->principles_preference !== ($validated['principles_preference'] ?? 'ALL_SUITABLE')) {
                        throw new ConflictHttpException('This referral was already confirmed with different customer instructions.');
                    }

                    return $existing;
                }
                if ($locked->status !== 'customer_confirmation_pending') {
                    throw new InvalidArgumentException('This partner financial intent request is no longer awaiting confirmation.');
                }
                if ($locked->expires_at && $locked->expires_at->isPast()) {
                    $locked->update(['status' => 'expired']);

                    return null;
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

                $this->audit->record('partner.financial_intent.confirmed', $request->user(), $locked, [
                    'financial_space_id' => $intent->financial_space_id, 'financial_intent_id' => $intent->id,
                    'principles_preference' => $intent->principles_preference,
                ], $request);

                return $intent;
            });
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        if ($intent === null) {
            return ApiResponse::error('This partner financial intent request has expired.', 422);
        }

        return ApiResponse::success('Financial intent confirmed. Product matching remains customer-controlled in OpFin.', [
            'financial_intent' => $intent,
            'product_match_required' => true,
        ], 201);
    }

    public function decline(Request $request, PartnerFinancialIntentRequest $partnerRequest): JsonResponse
    {
        $this->assertCustomer($request, $partnerRequest);
        DB::transaction(function () use ($request, $partnerRequest) {
            $locked = PartnerFinancialIntentRequest::query()->whereKey($partnerRequest->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'customer_confirmation_pending') {
                $locked->update(['status' => 'declined', 'declined_at' => now()]);
                $this->audit->record('partner.financial_intent.declined', $request->user(), $locked, [], $request);
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

    private function normalisePayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalisePayload($item);
        }

        return $value;
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
