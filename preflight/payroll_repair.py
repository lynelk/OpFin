from pathlib import Path
import re

p = Path('apps/api/app/Services/PayrollDeduction/PayrollDeductionService.php')
s = p.read_text()

def one(old, new):
    global s
    if s.count(old) != 1:
        raise RuntimeError('Unexpected payroll source anchor: ' + old[:80])
    s = s.replace(old, new)

# Validate direct service calls as well as HTTP headers.
s = s.replace("        if (trim($idempotencyKey) === '') {\n            throw new InvalidArgumentException('An idempotency key is required.');\n        }", "        $this->assertKeys($idempotencyKey, $correlationId);")
one('            $lockedApplication = FinancingApplication::query()',
    "            User::withoutGlobalScopes()->whereKey($user->id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();\n            app(\\App\\Services\\FinancingService::class)->assertSpaceAuthority($user, (int) $application->financial_space_id);\n            $boundStart = PayrollDeductionEvent::query()->where('actor_user_id', $user->id)->where('idempotency_key', $idempotencyKey)->where('event_type', 'case_started')->first();\n            if ($boundStart && (int) PayrollDeductionCase::whereKey($boundStart->payroll_deduction_case_id)->value('financing_application_id') !== (int) $application->id) {\n                throw new InvalidArgumentException('This Idempotency-Key is already bound to a different payroll application.');\n            }\n\n            $lockedApplication = FinancingApplication::query()")
one('                $locked = PayrollDeductionCase::whereKey($case->id)->lockForUpdate()->firstOrFail();',
    "                User::withoutGlobalScopes()->whereKey($case->user_id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();\n                $locked = PayrollDeductionCase::whereKey($case->id)->lockForUpdate()->firstOrFail();\n                if ((int) $actor->id === (int) $locked->user_id) {\n                    app(\\App\\Services\\FinancingService::class)->assertSpaceAuthority($actor, (int) $locked->financial_space_id);\n                }")
one('                    if (! hash_equals((string) $existingEvent->instruction_hash, $instructionHash)) {',
    '                    if ((int) $existingEvent->actor_user_id !== (int) $actor->id || ! hash_equals((string) $existingEvent->instruction_hash, $instructionHash)) {')

start = s.index('    public function requestReservation(')
end = s.index('    public function recordReservation(', start)
s = s[:start] + r'''    public function grantUndertaking(
        PayrollDeductionCase $case, User $customer, array $data, string $idempotencyKey, ?string $correlationId = null,
    ): PayrollDeductionCase {
        if ((int) $case->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('This payroll case does not belong to the customer.');
        }
        return $this->mutate($case, $customer, $idempotencyKey, $correlationId,
            'undertaking_and_reservation', $data, function (PayrollDeductionCase $locked) use ($customer, $data) {
                $this->requireStatus($locked, ['affordable', 'amendment_required']);
                $requested = (int) $data['requested_deduction_minor'];
                if (($data['authorised'] ?? false) !== true || $requested <= 0
                    || $locked->affordable_amount_minor === null || $requested > (int) $locked->affordable_amount_minor) {
                    throw new InvalidArgumentException('Explicit authorisation and a deduction within the verified affordable amount are required.');
                }
                $consent = ConsentRecord::create([
                    'user_id' => $customer->id, 'purpose' => 'payroll_deduction',
                    'policy_version' => self::UNDERTAKING_POLICY_VERSION, 'status' => ConsentRecord::STATUS_GRANTED,
                    'channel' => 'app', 'granted_at' => now(), 'metadata' => [
                        'scope' => self::UNDERTAKING_SCOPE, 'payroll_case_id' => $locked->id,
                        'payroll_case_reference' => $locked->reference, 'requested_deduction_minor' => $requested,
                    ],
                ]);
                $locked->fill([
                    'status' => 'reservation_pending', 'requested_deduction_minor' => $requested,
                    'undertaking_consent_record_id' => $consent->id, 'reservation_reference' => null,
                    'provider_agreement_reference' => $data['provider_agreement_reference'] ?? null,
                    'reservation_expires_at' => now()->addHours(max(1, (int) config('payroll_deduction.reservation_ttl_hours', 72))),
                    'rejection_code' => null, 'rejection_reason' => null,
                ])->save();
                app(\App\Services\AuditLogger::class)->record('payroll.undertaking.granted', $customer, $consent,
                    ['payroll_case_id' => $locked->id, 'requested_deduction_minor' => $requested]);
                return ['event' => 'undertaking_and_reservation_requested', 'to' => 'reservation_pending',
                    'evidence' => ['consent_record_id' => $consent->id, 'requested_deduction_minor' => $requested]];
            });
    }

    public function requestReservation(
        PayrollDeductionCase $case, User $customer, array $data, string $idempotencyKey, ?string $correlationId = null,
    ): PayrollDeductionCase {
        if ((int) $case->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('This payroll deduction case does not belong to the customer.');
        }
        return $this->mutate($case, $customer, $idempotencyKey, $correlationId,
            'request_reservation', $data, function (PayrollDeductionCase $locked) use ($data) {
                $this->requireStatus($locked, ['affordable', 'amendment_required']);
                $requested = (int) $data['requested_deduction_minor'];
                if ($requested <= 0 || $locked->affordable_amount_minor === null || $requested > (int) $locked->affordable_amount_minor) {
                    throw new InvalidArgumentException('The requested payroll deduction exceeds the verified affordable amount.');
                }
                $consent = $this->liveUndertaking($locked, (int) $data['undertaking_consent_record_id'], $requested);
                $locked->fill([
                    'status' => 'reservation_pending', 'requested_deduction_minor' => $requested,
                    'undertaking_consent_record_id' => $consent->id,
                    'provider_agreement_reference' => $data['provider_agreement_reference'] ?? null,
                    'reservation_reference' => null,
                    'reservation_expires_at' => now()->addHours(max(1, (int) config('payroll_deduction.reservation_ttl_hours', 72))),
                    'rejection_code' => null, 'rejection_reason' => null,
                ])->save();
                return ['event' => 'reservation_requested', 'to' => 'reservation_pending', 'evidence' => [
                    'requested_deduction_minor' => $requested, 'consent_record_id' => $consent->id,
                    'reservation_expires_at' => $locked->reservation_expires_at?->toIso8601String(),
                ]];
            });
    }

''' + s[end:]
# Recheck the transaction-specific mandate while the case is locked.
for marker in ["$this->requireStatus($locked, ['reserved']);", "$this->requireStatus($locked, ['deduction_approved']);"]:
    assert marker in s
    s = s.replace(marker, marker + "\n                $this->liveUndertaking($locked);\n                $this->requireReservation($locked);")
marker = "$accepted = (bool) $data['reserved'];"
assert marker in s
s = s.replace(marker, marker + "\n                if ($accepted) { $this->liveUndertaking($locked); }")
marker = "$approved = (bool) $data['approved'];"
assert marker in s
s = s.replace(marker, marker + "\n                if ($approved) { $this->liveUndertaking($locked); $this->requireReservation($locked); }")
marker = "$this->requireStatus($locked, ['amendment_required']);"
assert marker in s
s = s.replace(marker, marker + "\n                $this->liveUndertaking($locked);")
old = "if (in_array($locked->status, ['reservation_pending', 'reserved', 'vote_approval_pending'], true))"
assert old in s
s = s.replace(old, "if (in_array($locked->status, ['reservation_pending', 'reserved', 'vote_approval_pending'], true) || $locked->reservation_reference || (int) (($locked->provider_state ?? [])['submission_attempt'] ?? 0) > 0)")
# The original provider outcome stays immutable even as the settlement projection changes.
needle = "                    'evidence' => $data['evidence'] ?? null,\n                ]);"
assert s.count(needle) == 1
s = s.replace(needle, "                    'evidence' => $data['evidence'] ?? null,\n                    'provider_result_snapshot' => [\n                        'expected_minor' => $expected, 'recovered_minor' => $recovered, 'variance_minor' => $variance,\n                        'result_category' => $category, 'provider_reference' => $data['provider_reference'] ?? null,\n                        'evidence' => $data['evidence'] ?? null, 'recorded_at' => now()->toIso8601String(),\n                    ],\n                ]);")
start = s.index('    public function expireReservations(): int')
end = s.index('    private function mutate(', start)
s = s[:start] + r'''    public function expireReservations(): int
    {
        $count = 0;
        $statuses = ['reservation_pending', 'reserved', 'vote_approval_pending', 'deduction_approved'];
        PayrollDeductionCase::query()->whereIn('status', $statuses)
            ->whereNotNull('reservation_expires_at')->where('reservation_expires_at', '<=', now())
            ->chunkById(100, function ($cases) use (&$count, $statuses) {
                foreach ($cases as $case) {
                    DB::transaction(function () use ($case, &$count, $statuses) {
                        $locked = PayrollDeductionCase::whereKey($case->id)->lockForUpdate()->first();
                        if (! $locked || ! in_array($locked->status, $statuses, true)
                            || ! $locked->reservation_expires_at || $locked->reservation_expires_at->isFuture()) {
                            return;
                        }
                        $from = $locked->status;
                        $locked->update(['status' => 'cancellation_pending']);
                        $this->recordEvent($locked, null,
                            'reservation-expiry:'.$locked->reservation_expires_at->timestamp,
                            $this->instructionHash('expire_reservation', ['expires_at' => $locked->reservation_expires_at->toIso8601String()]),
                            null, 'reservation_expiry_release_required', $from, 'cancellation_pending',
                            ['reservation_expires_at' => $locked->reservation_expires_at->toIso8601String(), 'provider_release_confirmed' => false]);
                        $count++;
                    });
                }
            });
        return $count;
    }

''' + s[end:]
helpers = r'''    private function assertKeys(string $idempotencyKey, ?string $correlationId): void
    {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 180) {
            throw new InvalidArgumentException('A valid Idempotency-Key is required.');
        }
        if ($correlationId !== null && ! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('X-Correlation-ID must be a UUID.');
        }
    }

    private function liveUndertaking(PayrollDeductionCase $case, ?int $consentId = null, ?int $amount = null): ConsentRecord
    {
        $consent = ConsentRecord::query()->whereKey($consentId ?? $case->undertaking_consent_record_id)
            ->where('user_id', $case->user_id)->whereIn('purpose', ['payroll_deduction', ConsentRecord::PURPOSE_CREDIT_PROCESSING])
            ->where('policy_version', self::UNDERTAKING_POLICY_VERSION)->where('status', ConsentRecord::STATUS_GRANTED)
            ->whereNull('revoked_at')->lockForUpdate()->first();
        $metadata = (array) ($consent?->metadata ?? []);
        if (! $consent || ($metadata['scope'] ?? null) !== self::UNDERTAKING_SCOPE
            || (int) ($metadata['payroll_case_id'] ?? 0) !== (int) $case->id
            || (string) ($metadata['payroll_case_reference'] ?? '') !== (string) $case->reference
            || (int) ($metadata['requested_deduction_minor'] ?? 0) !== (int) ($amount ?? $case->requested_deduction_minor)) {
            throw new InvalidArgumentException('A current payroll undertaking consent bound to this case and deduction amount is required.');
        }
        return $consent;
    }

    private function requireReservation(PayrollDeductionCase $case): void
    {
        if (! $case->reservation_expires_at || $case->reservation_expires_at->isPast()
            || ! $case->reservation_reference || ! $case->provider_agreement_reference) {
            throw new InvalidArgumentException('A current confirmed provider reservation is required.');
        }
    }

'''
s = s.replace('    private function instructionHash(', helpers + '    private function instructionHash(')
p.write_text(s)

# Keep the existing strict response and role boundaries; expose the new atomic command.
p = Path('apps/api/app/Http/Controllers/Api/PayrollDeductionController.php')
s = p.read_text()
method = r'''    public function undertaking(Request $request, PayrollDeductionCase $case): JsonResponse
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

'''
assert '    public function requestReservation(' in s
p.write_text(s.replace('    public function requestReservation(', method + '    public function requestReservation('))
p = Path('apps/api/routes/payroll_deduction.php')
s = p.read_text()
lines = s.splitlines()
new = []
for line in lines:
    new.append(line)
    if "'requestReservation'" in line:
        new.append(line.replace('/reservation', '/undertaking').replace("'requestReservation'", "'undertaking'"))
    if "'payrollSubmission'" in line:
        new.append(line.replace('/payroll-submission', '/cancellation-release').replace("'payrollSubmission'", "'cancellationRelease'"))
assert len(new) == len(lines) + 2
p.write_text('\n'.join(new) + '\n')
p = Path('apps/api/routes/console.php')
s = p.read_text()
if "payroll-deduction:expire-reservations" not in s:
    s += "\n\\Illuminate\\Support\\Facades\\Schedule::command('payroll-deduction:expire-reservations')->everyFiveMinutes()->withoutOverlapping()->onOneServer();\n"
p.write_text(s)

p = Path('apps/api/app/Models/PayrollDeductionReconciliation.php')
s = p.read_text().replace("'provider_reference', 'evidence', 'reconciled_at'", "'provider_reference', 'evidence', 'reconciled_at', 'provider_result_snapshot'")
s = s.replace("'evidence' => 'array',", "'evidence' => 'array',\n            'provider_result_snapshot' => 'array',")
p.write_text(s)

Path('apps/api/database/migrations/2026_10_02_000200_preserve_payroll_result_snapshots.php').write_text(r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_deduction_events', 'instruction_hash')) {
            Schema::table('payroll_deduction_events', fn (Blueprint $table) => $table->string('instruction_hash', 64)->nullable());
        }
        if (! Schema::hasColumn('payroll_deduction_reconciliations', 'submission_attempt')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->unsignedInteger('submission_attempt')->default(1));
        }
        $indexes = collect(Schema::getIndexes('payroll_deduction_reconciliations'))->pluck('name');
        if ($indexes->contains('payroll_deduction_period_unique')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->dropUnique('payroll_deduction_period_unique'));
        }
        if (! $indexes->contains('payroll_deduction_attempt_unique')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->unique(
                ['payroll_deduction_case_id', 'payroll_period', 'submission_attempt'], 'payroll_deduction_attempt_unique'));
        }
        Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->json('provider_result_snapshot')->nullable());
        $columns = ['payroll_deduction_case_id', 'payroll_period', 'submission_attempt', 'expected_minor', 'currency', 'result_category', 'provider_reference', 'provider_result_snapshot'];
        if (DB::getDriverName() === 'pgsql') {
            $comparisons = array_map(fn ($column) => 'OLD.'.$column.'::text IS DISTINCT FROM NEW.'.$column.'::text', $columns);
            DB::unprepared("CREATE OR REPLACE FUNCTION opfin_payroll_result_immutable() RETURNS trigger LANGUAGE plpgsql AS \$opfin\$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Payroll result evidence cannot be deleted'; END IF; IF ".implode(' OR ', $comparisons)." THEN RAISE EXCEPTION 'Original payroll result evidence is immutable'; END IF; RETURN NEW; END; \$opfin\$");
            DB::unprepared('CREATE TRIGGER payroll_result_immutable BEFORE UPDATE OR DELETE ON payroll_deduction_reconciliations FOR EACH ROW EXECUTE FUNCTION opfin_payroll_result_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            $comparisons = array_map(fn ($column) => 'OLD.'.$column.' IS NOT NEW.'.$column, $columns);
            DB::unprepared("CREATE TRIGGER payroll_result_immutable_update BEFORE UPDATE ON payroll_deduction_reconciliations WHEN ".implode(' OR ', $comparisons)." BEGIN SELECT RAISE(ABORT, 'Original payroll result evidence is immutable'); END");
            DB::unprepared("CREATE TRIGGER payroll_result_immutable_delete BEFORE DELETE ON payroll_deduction_reconciliations BEGIN SELECT RAISE(ABORT, 'Payroll result evidence cannot be deleted'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable ON payroll_deduction_reconciliations');
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_payroll_result_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable_delete');
        }
        Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->dropColumn('provider_result_snapshot'));
        // Keep additive compatibility columns and attempt uniqueness; do not collapse history.
    }
};
''')
print('Payroll transaction, consent, expiry and immutable-result repairs prepared.')
