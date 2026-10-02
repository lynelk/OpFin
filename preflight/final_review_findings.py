from pathlib import Path

p = Path('apps/api/app/Services/PayrollDeduction/PayrollDeductionService.php')
s = p.read_text()
anchor = "            $instructionHash = $this->instructionHash('create_case', ["
assert s.count(anchor) == 1
s = s.replace(anchor, r'''            $currency = strtoupper(trim((string) $lockedApplication->product->currency));
            if (! preg_match('/^[A-Z]{3}$/', $currency)
                || (isset($data['currency']) && strtoupper(trim((string) $data['currency'])) !== $currency)) {
                throw new InvalidArgumentException('Payroll currency must match the financing product currency.');
            }

''' + anchor)
old = "'currency' => strtoupper((string) ($data['currency'] ?? 'UGX'))"
assert s.count(old) == 2
s = s.replace(old, "'currency' => $currency")
start = s.index('    public function recordReservation(')
end = s.index('    public function submitKeyFacts(', start)
section = s[start:end]
old = "($data['provider_agreement_reference'] ?? $locked->provider_agreement_reference ?? '')"
assert section.count(old) == 1
section = section.replace(old, "($data['provider_agreement_reference'] ?? '')")
p.write_text(s[:start] + section + s[end:])

p = Path('apps/api/app/Services/AccountDeletionObligationService.php')
s = p.read_text()
s = s.replace("['Cleared', 'Cancelled', 'Rejected']", "['Cleared', 'Cancelled', 'Rejected', 'Reversed']")
s = s.replace("['paid', 'rejected', 'closed', 'withdrawn', 'cancelled']", "['paid', 'declined', 'rejected', 'closed', 'withdrawn', 'cancelled']")
old = "['premium_due', 'active', 'claim_pending']"
assert old in s
s = s.replace(old, "['premium_due', 'premium_pending', 'pending_issuance', 'active', 'claim_pending', 'lapsed']")
marker = "        if (Schema::hasTable('protection_claims')) {"
assert s.count(marker) == 1
s = s.replace(marker, r'''        if (Schema::hasTable('protection_premium_payments')) {
            foreach (DB::table('protection_premium_payments as payments')
                ->join('protection_policies as policies', 'policies.id', '=', 'payments.protection_policy_id')
                ->join('protection_products as products', 'products.id', '=', 'policies.protection_product_id')
                ->leftJoin('institutions as institutions', 'institutions.id', '=', 'payments.institution_id')
                ->where('payments.user_id', $userId)
                ->whereNotIn('payments.status', ['confirmed', 'failed', 'reversed', 'cancelled'])
                ->select('payments.*', 'products.insurer_name', 'institutions.name as provider_name',
                    'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
                ->get() as $payment) {
                $items[] = $this->item('protection_premium_pending', 'Protection premium or reversal still requires finality',
                    $payment->payment_reference, $payment->status, (int) $payment->amount_minor,
                    $payment->currency, $payment->coverage_period_end,
                    $this->contact($payment->provider_name ?? $payment->insurer_name ?? 'Insurance partner',
                        $payment->provider_phone, $payment->provider_email, $payment->provider_address));
            }
        }

''' + marker)
p.write_text(s)

Path('apps/api/database/migrations/2026_10_02_000300_upgrade_payroll_event_protection.php').write_text(r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Install protection on databases that ran an earlier payroll migration.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION opfin_payroll_event_immutable()
                RETURNS trigger LANGUAGE plpgsql AS $opfin$
                BEGIN RAISE EXCEPTION 'Payroll deduction event evidence is immutable'; END;
                $opfin$
                SQL);
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable ON payroll_deduction_events');
            DB::unprepared('CREATE TRIGGER payroll_deduction_events_immutable BEFORE UPDATE OR DELETE ON payroll_deduction_events FOR EACH ROW EXECUTE FUNCTION opfin_payroll_event_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = 'payroll_deduction_events_immutable_'.strtolower($operation);
                DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON payroll_deduction_events BEGIN SELECT RAISE(ABORT, 'Payroll deduction event evidence is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        // Rollback must not disable immutable evidence protection from the baseline.
    }
};
''')

p = Path('apps/api/tests/Feature/PayrollDeductionWorkflowTest.php')
s = p.read_text()
marker = '    private function financingApplication('
assert marker in s
methods = r'''    public function test_payroll_currency_is_authoritative_and_mismatches_are_rejected(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        $application->product->update(['currency' => 'KES']);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id, 'currency' => 'UGX'], $this->headers('wrong-currency'))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_deduction_cases', 0);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('authoritative-currency'))->assertCreated()->assertJsonPath('data.case.currency', 'KES');
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id, 'currency' => 'KES'], $this->headers('authoritative-currency'))->assertCreated()->assertJsonPath('data.case.currency', 'KES');
        $this->assertDatabaseCount('payroll_deduction_cases', 1);
    }

    public function test_customer_agreement_reference_cannot_be_promoted_to_confirmed_provider_evidence(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/undertaking', [
            'authorised' => true, 'requested_deduction_minor' => 350000,
            'provider_agreement_reference' => 'CUSTOMER-DECLARED-ONLY',
        ], $this->headers('declared-agreement'))->assertOk();
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'reserved' => true, 'reservation_reference' => 'PROVIDER-RESERVATION',
        ], $this->headers('missing-verified-agreement'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'reservation_pending']);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'reserved' => true, 'reservation_reference' => 'PROVIDER-RESERVATION', 'provider_agreement_reference' => 'PROVIDER-AGREEMENT',
        ], $this->headers('verified-agreement'))->assertOk()->assertJsonPath('data.case.status', 'reserved');
    }

    public function test_forward_migration_restores_event_protection_on_existing_payroll_database(): void
    {
        [$caseId] = $this->caseAtPayrollSubmission();
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable ON payroll_deduction_events');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable_delete');
        }
        $migration = require database_path('migrations/2026_10_02_000300_upgrade_payroll_event_protection.php');
        $migration->up();
        $migration->up();
        $this->expectException(QueryException::class);
        DB::table('payroll_deduction_events')->where('payroll_deduction_case_id', $caseId)->update(['event_type' => 'tampered']);
    }

'''
p.write_text(s.replace(marker, methods + marker))

p = Path('apps/api/tests/Feature/AccountDeletionAndAppStorePolicyTest.php')
s = p.read_text()
marker = '    public function test_wrong_password_cannot_delete_account(): void'
assert marker in s
methods = r'''    public function test_nonterminal_protection_processing_blocks_deletion_even_after_policy_cancellation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $product = \App\Models\ProtectionProduct::create([
            'code' => 'DELETION-PREMIUM', 'name' => 'Test protection', 'insurer_name' => 'Recorded insurer',
            'country_code' => 'UG', 'currency' => 'UGX', 'product_type' => 'funeral', 'status' => 'draft',
            'premium_amount_minor' => 1000, 'premium_frequency' => 'monthly', 'disclosure_payload' => [],
        ]);
        $policy = \App\Models\ProtectionPolicy::create([
            'protection_product_id' => $product->id, 'user_id' => $user->id, 'policy_reference' => 'POLICY-PENDING',
            'status' => 'premium_pending', 'premium_amount_minor' => 1000, 'premium_frequency' => 'monthly',
            'disclosure_hash' => str_repeat('a', 64), 'enrolled_at' => now(),
        ]);
        Sanctum::actingAs($user);
        foreach (['premium_pending', 'pending_issuance'] as $status) {
            $policy->update(['status' => $status]);
            $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', false);
        }
        $policy->update(['status' => 'cancelled']);
        $payment = \App\Models\ProtectionPremiumPayment::create([
            'protection_policy_id' => $policy->id, 'user_id' => $user->id, 'payment_reference' => 'PREMIUM-UNSETTLED',
            'idempotency_key' => 'premium-deletion-boundary', 'status' => 'collected_pending_partner',
            'amount_minor' => 1000, 'currency' => 'UGX', 'requested_at' => now(),
        ]);
        foreach (['collection_pending', 'collected_pending_partner', 'reversal_exception'] as $status) {
            $payment->update(['status' => $status]);
            $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])->assertStatus(409)
                ->assertJsonPath('data.active_obligations.0.code', 'protection_premium_pending');
        }
        $payment->update(['status' => 'reversed']);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', true);
        \App\Models\ProtectionClaim::create([
            'protection_policy_id' => $policy->id, 'user_id' => $user->id, 'claim_reference' => 'DECLINED-CLAIM',
            'status' => 'declined', 'incident_date' => now()->toDateString(), 'category' => 'test',
            'description' => 'Resolved test claim', 'submitted_at' => now(), 'resolved_at' => now(),
        ]);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', true);
    }

'''
p.write_text(s.replace(marker, methods + marker))

addition = '''\n\n### Final review corrections\n\nPayroll currency is copied from the authoritative financing product; client-supplied mismatches are rejected. Positive reservation confirmation requires the operations caller to supply the agreement reference independently, not inherit a customer-declared reference. Nonterminal premium processing and reversal exceptions block account deletion, including when the policy itself is cancelled. Fully reversed legacy loans and declined claims are terminal for deletion checks, without deleting their required history. The forward payroll migration installs immutable-event protection on databases that ran an earlier schema.\n'''
for rel in ['apps/api/docs/api/frontend-backend-contract.md', 'apps/api/docs/api/ACCOUNT_AND_DATA_DELETION.md', 'apps/api/docs/api/PAYROLL_DEDUCTION.md', 'docs/releases/2026-10-02-pr142-release-acceptance.md']:
    p = Path(rel)
    p.write_text((p.read_text().rstrip() + addition).rstrip() + '\n')
print('Reviewed currency, agreement provenance, terminal-state, premium-finality and forward-migration corrections prepared.')
