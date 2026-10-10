<?php

namespace App\Services\Cito;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * OpFin controls service ownership and approval; Cito owns billable usage.
 * This journal is not a replacement for OpFin loan or investment ledgers.
 */
class CitoBaasGovernanceService
{
    public function draft(User $maker, string $operation, string $idempotencyKey, array $payload): object
    {
        $this->authorised($maker);
        $operation = strtolower(trim($operation));
        if (! in_array($operation, ['charge', 'usage', 'subscription'], true)) {
            throw new InvalidArgumentException('Unsupported governed BaaS operation.');
        }
        if (! preg_match('/^[A-Za-z0-9:._-]{8,128}$/', $idempotencyKey)) {
            throw new InvalidArgumentException('Invalid BaaS idempotency reference.');
        }
        $account = $operation === 'subscription'
            ? ($payload['accountReference'] ?? null) : ($payload['billingAccountReference'] ?? null);
        if (! is_string($account) || ! preg_match('/^[A-Za-z0-9:._-]{1,128}$/', $account)) {
            throw new InvalidArgumentException('BaaS billing account reference is required.');
        }
        if (in_array($operation, ['charge', 'usage'], true)
            && ($payload['idempotencyKey'] ?? null) !== $idempotencyKey) {
            throw new InvalidArgumentException('BaaS payload must use the original idempotency key.');
        }
        $this->assertFields($operation, $payload);
        $environment = strtoupper(trim((string) config('services.cito.environment', 'SANDBOX')));
        if (! in_array($environment, ['SANDBOX', 'PRODUCTION'], true)) {
            throw new InvalidArgumentException('Invalid BaaS environment.');
        }
        if ($environment === 'PRODUCTION'
            && ! in_array($account, (array) config('services.cito.baas_allowed_accounts', []), true)) {
            throw new InvalidArgumentException('Production billing account is not independently authorised.');
        }
        $hash = hash('sha256', json_encode([$environment, $operation, $account, $payload], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($maker, $operation, $idempotencyKey, $payload, $account, $environment, $hash) {
            $existing = DB::table('cito_baas_operation_intents')
                ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->instruction_hash !== $hash || (int) $existing->maker_user_id !== (int) $maker->id) {
                    throw new InvalidArgumentException('BaaS key belongs to a different instruction or actor.');
                }

                return $existing;
            }
            $id = DB::table('cito_baas_operation_intents')->insertGetId([
                'environment' => $environment, 'operation' => $operation,
                'billing_account_reference' => $account, 'idempotency_key' => $idempotencyKey,
                'instruction_hash' => $hash, 'request_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'maker_user_id' => $maker->id, 'status' => 'pending_approval',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('cito.baas.intent_drafted', $maker, $maker, [
                'intent_id' => $id, 'operation' => $operation,
                'environment' => $environment, 'instruction_hash' => $hash,
            ]);

            return DB::table('cito_baas_operation_intents')->find($id);
        });
    }

    public function approveAndSubmit(User $checker, int $id): object
    {
        $this->authorised($checker);
        $intent = DB::transaction(function () use ($checker, $id) {
            $row = DB::table('cito_baas_operation_intents')->where('id', $id)->lockForUpdate()->first();
            if (! $row) {
                throw new InvalidArgumentException('Unknown BaaS instruction.');
            }
            if ((int) $row->maker_user_id === (int) $checker->id) {
                throw new InvalidArgumentException('BaaS maker and checker must be different.');
            }
            if ($row->status !== 'pending_approval') {
                return null;
            }
            if ($row->environment !== strtoupper((string) config('services.cito.environment', 'SANDBOX'))) {
                throw new InvalidArgumentException('BaaS instruction belongs to another environment.');
            }
            app(CitoFeatureGate::class)->requireEnabled('billing');
            if (! (bool) config('services.cito.baas_write_enabled', false)) {
                throw new RuntimeException('BaaS writes are not approved for this environment.');
            }
            app(CitoBaasWriteBudgetService::class)->reserve();
            DB::table('cito_baas_operation_intents')->where('id', $id)->update([
                'checker_user_id' => $checker->id, 'status' => 'submission_started',
                'approved_at' => now(), 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('cito.baas.intent_approved', $checker, $checker, [
                'intent_id' => $id, 'operation' => $row->operation,
                'environment' => $row->environment,
                'instruction_hash' => $row->instruction_hash,
            ]);

            return $row;
        });
        if ($intent === null) {
            return $this->find($checker, $id);
        }
        try {
            $payload = json_decode($intent->request_payload, true, 512, JSON_THROW_ON_ERROR);
            $client = app(CitoBillingClient::class);
            $response = match ($intent->operation) {
                'charge' => $client->authorizeCharge($payload),
                'usage' => $client->usageEvent($payload),
                'subscription' => $client->createSubscription($payload),
            };
            $reference = $response['reference'] ?? $response['chargeReference']
                ?? $response['subscriptionReference'] ?? $response['eventReference'] ?? null;
            // Remote acknowledgement does not prove a service fee is earned,
            // a customer subscription is active, or a bank balance reconciled.
            DB::table('cito_baas_operation_intents')->where('id', $id)->update([
                'status' => 'submitted_unconfirmed', 'provider_reference' => is_string($reference) ? substr($reference, 0, 128) : null,
                'provider_status' => substr((string) ($response['status'] ?? 'ACCEPTED'), 0, 48),
                'submitted_at' => now(), 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('cito.baas.submitted_unconfirmed', $checker, $checker, [
                'intent_id' => $id, 'provider_reference' => is_string($reference) ? $reference : null,
            ]);
        } catch (Throwable $exception) {
            // Never automatically resubmit an unknown billable operation.
            report($exception);
            DB::table('cito_baas_operation_intents')->where('id', $id)->update([
                'status' => 'submission_unknown', 'updated_at' => now(),
            ]);
            app(AuditLogger::class)->record('cito.baas.submission_unknown', $checker, $checker, [
                'intent_id' => $id, 'exception_type' => $exception::class,
            ]);
        }

        return $this->find($checker, $id);
    }

    public function find(User $reader, int $id): object
    {
        $this->authorised($reader);
        $row = DB::table('cito_baas_operation_intents')->where('id', $id)->first();
        if (! $row || $row->environment !== strtoupper((string) config('services.cito.environment', 'SANDBOX'))) {
            throw new InvalidArgumentException('BaaS instruction is not available.');
        }
        // Omit request bodies from management API output: they may contain customer references.
        unset($row->request_payload);

        return $row;
    }

    private function authorised(User $user): void
    {
        if (! $user->hasRole(User::ROLE_PLATFORM_ADMIN) || $user->trashed()) {
            throw new AuthorizationException('Platform billing authority is required.');
        }
    }

    private function assertFields(string $operation, array $payload): void
    {
        $required = match ($operation) {
            'charge' => ['billingAccountReference', 'serviceCode', 'usageQuantity', 'netAmount', 'currency', 'idempotencyKey'],
            'usage' => ['billingAccountReference', 'serviceCode', 'meterCode', 'quantity', 'sourceReference', 'idempotencyKey'],
            'subscription' => ['customerReference', 'accountReference', 'contractReference', 'subscriptionReference', 'serviceCode', 'planCode'],
        };
        // Preserve the versioned external contract and reject unexpected
        // fields so customer secrets or loan data cannot be smuggled into Cito.
        $allowed = match ($operation) {
            'charge' => [
                'billingAccountReference', 'serviceCode', 'entitlementCode',
                'usageQuantity', 'netAmount', 'currency', 'idempotencyKey', 'expiresAt',
            ],
            'usage' => [
                'billingAccountReference', 'serviceCode', 'meterCode', 'eventTime',
                'quantity', 'currency', 'dimensions', 'sourceReference',
                'idempotencyKey', 'taxCode',
            ],
            'subscription' => [
                'customerReference', 'accountReference', 'contractReference',
                'subscriptionReference', 'serviceCode', 'planCode', 'quantity',
                'startsAt', 'endsAt',
            ],
        };
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new InvalidArgumentException('Unexpected field in Cito BaaS operation.');
        }
        if (count($payload) > 15 || strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > 6000) {
            throw new InvalidArgumentException('Cito BaaS operation exceeds the safe size limit.');
        }
        foreach ($required as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
                throw new InvalidArgumentException('Missing BaaS contract field: '.$field);
            }
        }
        foreach (['netAmount', 'quantity', 'usageQuantity'] as $amount) {
            if (isset($payload[$amount]) && ! preg_match('/^\d+(?:\.\d+)?$/', (string) $payload[$amount])) {
                throw new InvalidArgumentException('BaaS quantities and amounts must use nonnegative decimal strings.');
            }
        }
    }
}
