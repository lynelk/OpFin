from pathlib import Path

p = Path('apps/api/tests/Feature/PayrollDeductionWorkflowTest.php')
s = p.read_text()
s = s.replace("'code' => 'SALARY-PAYROLL',", "'code' => 'SALARY-PAYROLL-'.Str::uuid(),")
marker = "            ->json('data.case');\n\n        Sanctum::actingAs($operations);"
assert marker in s
s = s.replace(marker, "            ->json('data.case');\n\n        $consent->update(['metadata' => ['scope' => 'government_payroll_deduction', 'payroll_case_id' => $case['id'], 'payroll_case_reference' => $case['reference'], 'requested_deduction_minor' => 350000]]);\n        Sanctum::actingAs($operations);")
marker = "        ], $this->headers('setup-create'))->json('data.case.id');"
assert marker in s
s = s.replace(marker, marker + "\n        $consent->update(['metadata' => ['scope' => 'government_payroll_deduction', 'payroll_case_id' => $caseId, 'payroll_case_reference' => DB::table('payroll_deduction_cases')->where('id', $caseId)->value('reference'), 'requested_deduction_minor' => 350000]]);")
# Setup helpers must fail at the broken transition, not hide it until a later assertion.
for name in ['setup-create', 'setup-aff', 'setup-reserve-request', 'setup-reserve', 'setup-keyfacts', 'setup-vote', 'setup-submission']:
    if name == 'setup-create':
        s = s.replace("$this->headers('setup-create'))->json", "$this->headers('setup-create'))->assertCreated()->json")
    else:
        s = s.replace("$this->headers('" + name + "'));", "$this->headers('" + name + "'))->assertOk();")
methods = r'''    private function affordableCase(): array
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $application = $this->financingApplication($customer);
        Sanctum::actingAs($customer);
        $case = $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('boundary-create'))
            ->assertCreated()->json('data.case');
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/affordability', [
            'affordable' => true, 'affordable_amount_minor' => 500000,
        ], $this->headers('boundary-affordability'))->assertOk();
        return [$case, $customer, $operations, $application];
    }

    private function reserveWithAtomicUndertaking(array $case, User $customer): void
    {
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/undertaking', [
            'authorised' => true, 'requested_deduction_minor' => 350000,
        ], $this->headers('atomic-undertaking'))->assertOk()->assertJsonPath('data.case.status', 'reservation_pending');
    }

    public function test_atomic_undertaking_is_case_bound_replay_safe_and_does_not_revoke_generic_credit_consent(): void
    {
        [$case, $customer] = $this->affordableCase();
        $generic = ConsentRecord::create(['user_id' => $customer->id, 'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'generic-credit-v1', 'status' => 'granted', 'channel' => 'app', 'granted_at' => now()]);
        $this->reserveWithAtomicUndertaking($case, $customer);
        $this->reserveWithAtomicUndertaking($case, $customer);
        $this->assertSame(1, ConsentRecord::where('purpose', 'payroll_deduction')->count());
        $this->assertSame('granted', $generic->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'payroll.undertaking.granted')->count());
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/undertaking', [
            'authorised' => true, 'requested_deduction_minor' => 360000,
        ], $this->headers('atomic-undertaking'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'requested_deduction_minor' => 350000]);
    }

    public function test_generic_credit_consent_cannot_authorise_payroll_deduction(): void
    {
        [$case, $customer] = $this->affordableCase();
        $generic = ConsentRecord::create(['user_id' => $customer->id, 'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'generic-credit-v1', 'status' => 'granted', 'channel' => 'app', 'granted_at' => now()]);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'requested_deduction_minor' => 350000, 'undertaking_consent_record_id' => $generic->id,
        ], $this->headers('generic-consent'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'affordable']);
    }

    public function test_payroll_replay_key_is_bound_to_command_and_actor(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/cancel', [], $this->headers('boundary-affordability'))->assertUnprocessable();
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/affordability', [
            'affordable' => true, 'affordable_amount_minor' => 500001,
        ], $this->headers('boundary-affordability'))->assertUnprocessable();
    }

    public function test_invalid_correlation_and_wrong_salary_product_are_rejected_without_creating_cases(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], [
            'Idempotency-Key' => 'bad-correlation', 'X-Correlation-ID' => 'payroll-run-not-a-uuid',
        ])->assertUnprocessable();
        $application->product->update(['family' => 'asset_finance']);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('wrong-family'))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_deduction_cases', 0);
    }

    public function test_removed_space_authority_cannot_start_payroll_case(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        DB::table('financial_space_memberships')->where('user_id', $customer->id)->update(['deleted_at' => now()]);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('removed-member'))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_deduction_cases', 0);
    }

    public function test_revoked_undertaking_blocks_positive_reservation_confirmation(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        $this->reserveWithAtomicUndertaking($case, $customer);
        ConsentRecord::where('user_id', $customer->id)->where('purpose', 'payroll_deduction')->update(['status' => 'revoked', 'revoked_at' => now()]);
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'reserved' => true, 'reservation_reference' => 'REAL-EVIDENCE-REFERENCE', 'provider_agreement_reference' => 'AGREEMENT-REFERENCE',
        ], $this->headers('revoked-reservation'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'reservation_pending']);
    }

    public function test_cancellation_and_expiry_require_evidenced_provider_release_and_preserve_servicing_access(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        $this->reserveWithAtomicUndertaking($case, $customer);
        DB::table('payroll_deduction_cases')->where('id', $case['id'])->update(['reservation_expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(\App\Services\PayrollDeduction\PayrollDeductionService::class)->expireReservations());
        $this->assertSame(0, app(\App\Services\PayrollDeduction\PayrollDeductionService::class)->expireReservations());
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'cancellation_pending']);
        Sanctum::actingAs($customer);
        $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])
            ->assertStatus(409)->assertJsonPath('data.deletion_status', 'blocked_obligations');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('support_cases', ['customer_id' => $customer->id, 'category' => 'account_deletion']);
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/cancellation-release', [
            'released' => true,
        ], $this->headers('release-without-evidence'))->assertUnprocessable();
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/cancellation-release', [
            'released' => true, 'release_reference' => 'TEST-RELEASE-REFERENCE',
        ], $this->headers('release-with-evidence'))->assertOk()->assertJsonPath('data.case.status', 'cancelled');
    }

    public function test_amended_same_month_retains_original_payroll_result_attempt(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);
        $prefix = '/api/operations/payroll-deduction/cases/'.$caseId;
        $this->postJson($prefix.'/payroll-result', ['payroll_period' => '2026-10', 'result_category' => 'rejected', 'provider_reference' => 'FIRST-REJECTION'], $this->headers('first-result'))->assertOk();
        $this->postJson($prefix.'/amend', [], $this->headers('retry-reservation'))->assertOk()->assertJsonPath('data.case.status', 'reservation_pending');
        $this->postJson($prefix.'/key-facts', ['key_facts' => ['version' => 'v2']], $this->headers('premature-key-facts'))->assertUnprocessable();
        $this->postJson($prefix.'/reservation', ['reserved' => true, 'reservation_reference' => 'SECOND-RESERVATION', 'provider_agreement_reference' => 'SECOND-AGREEMENT'], $this->headers('second-reservation'))->assertOk();
        $this->postJson($prefix.'/key-facts', ['key_facts' => ['version' => 'v2']], $this->headers('second-facts'))->assertOk();
        $this->postJson($prefix.'/vote-decision', ['approved' => true], $this->headers('second-vote'))->assertOk();
        $this->postJson($prefix.'/payroll-submission', ['payroll_period' => '2026-10'], $this->headers('second-submission'))->assertOk();
        $this->postJson($prefix.'/payroll-result', ['payroll_period' => '2026-10', 'result_category' => 'success', 'provider_reference' => 'SECOND-RESULT', 'recovered_minor' => 350000], $this->headers('second-result'))->assertOk();
        $this->assertDatabaseCount('payroll_deduction_reconciliations', 2);
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 1, 'result_category' => 'rejected', 'provider_reference' => 'FIRST-REJECTION']);
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 2, 'result_category' => 'success']);
        $this->postJson($prefix.'/reconcile', ['payroll_period' => '2026-10', 'recovered_minor' => 350000], $this->headers('second-settlement'))->assertOk();
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 1, 'status' => 'rejected']);
    }

    public function test_payroll_original_result_cannot_be_changed_through_raw_sql(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-result', [
            'payroll_period' => '2026-10', 'result_category' => 'success', 'recovered_minor' => 340000,
        ], $this->headers('immutable-result'))->assertOk();
        $this->expectException(QueryException::class);
        DB::table('payroll_deduction_reconciliations')->where('payroll_deduction_case_id', $caseId)->update(['expected_minor' => 340000]);
    }

    public function test_canonical_financing_arrangement_blocks_deletion_without_legacy_loan(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $application = $this->financingApplication($customer);
        \App\Models\FinancingArrangement::create([
            'reference' => (string) Str::uuid(), 'financing_application_id' => $application->id,
            'financial_product_id' => $application->financial_product_id, 'user_id' => $customer->id,
            'financial_space_id' => $application->financial_space_id, 'contract_type' => 'CREDIT',
            'principal_or_cost_minor' => 200000, 'total_obligation_minor' => 230000,
            'currency' => 'UGX', 'status' => 'contracting',
        ]);
        Sanctum::actingAs($customer);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', false)
            ->assertJsonPath('data.active_obligations.0.code', 'financing_arrangement')
            ->assertJsonPath('data.active_obligations.0.amount_basis', 'contract_total_not_current_balance')
            ->assertJsonPath('data.active_obligations.0.provider.direct_contact_available', false);
        $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
    }

'''
marker = '    private function financingApplication('
assert marker in s
s = s.replace(marker, methods + marker)
p.write_text(s)

p = Path('apps/api/tests/Feature/PartnerFinancialIntentWorkflowTest.php')
s = p.read_text().replace("'partner_name' => 'Stolets Test Partner',", "'partner_name' => 'Stolets Test Partner',\n            'financial_intent_source_platform' => 'stolets',")
methods = r'''    public function test_partner_cannot_spoof_another_source_platform_or_use_unconfigured_source(): void
    {
        [$partner, $account] = $this->partner();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);
        $payload = $this->payload($account, 'SOURCE-CHECK');
        $payload['source_platform'] = 'shamba';
        $this->postJson('/api/partner/financial-intents/'.$customer->id, $payload, ['Idempotency-Key' => 'source-1'])->assertForbidden();
        $payload['source_platform'] = 'stolets';
        DB::table('partner_distribution_accounts')->where('id', $account)->update(['financial_intent_source_platform' => null]);
        $this->postJson('/api/partner/financial-intents/'.$customer->id, $payload, ['Idempotency-Key' => 'source-2'])->assertForbidden();
        $this->assertDatabaseCount('partner_financial_intent_requests', 0);
    }

    public function test_confirmation_is_audited_once_and_replay_cannot_change_customer_space(): void
    {
        [$partner, $account] = $this->partner();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);
        $this->postJson('/api/partner/financial-intents/'.$customer->id, $this->payload($account, 'AUDIT-CHECK'), ['Idempotency-Key' => 'audit-referral'])->assertCreated();
        $id = DB::table('partner_financial_intent_requests')->where('external_reference', 'AUDIT-CHECK')->value('id');
        $space = $this->personalSpace($customer);
        Sanctum::actingAs($customer);
        $data = ['financial_space_id' => $space, 'principles_preference' => 'CONVENTIONAL_ONLY'];
        $one = $this->postJson('/api/partner-financial-intents/'.$id.'/confirm', $data)->assertCreated();
        $two = $this->postJson('/api/partner-financial-intents/'.$id.'/confirm', $data)->assertCreated();
        $this->assertSame($one->json('data.financial_intent.id'), $two->json('data.financial_intent.id'));
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'partner.financial_intent.confirmed')->where('actor_id', $customer->id)->count());
        $data['financial_space_id'] = $this->personalSpace($customer);
        $this->postJson('/api/partner-financial-intents/'.$id.'/confirm', $data)->assertStatus(409);
        DB::table('financial_space_memberships')->where('financial_space_id', $space)->update(['deleted_at' => now()]);
        $data['financial_space_id'] = $space;
        $this->postJson('/api/partner-financial-intents/'.$id.'/confirm', $data)->assertUnprocessable();
    }

'''
marker = '    private function partner(): array'
assert marker in s
p.write_text(s.replace(marker, methods + marker))

p = Path('apps/api/tests/Feature/AccountDeletionAndAppStorePolicyTest.php')
s = p.read_text()
methods = r'''    public function test_selective_location_deletion_keeps_other_customers_and_regulated_asset_context(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $records = [];
        foreach ([[$owner->id, 'user', $owner->id, 'personal_service_discovery'], [$other->id, 'user', $other->id, 'personal_service_discovery'], [$owner->id, 'asset', 500, 'asset_verification']] as [$userId, $subjectType, $subjectId, $purpose]) {
            $records[] = DB::table('location_contexts')->insertGetId([
                'public_id' => (string) Str::uuid(), 'user_id' => $userId,
                'subject_type' => $subjectType, 'subject_id' => $subjectId, 'purpose' => $purpose,
                'source' => 'manual', 'consent_purpose' => $purpose, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/account/data', ['pin' => '482951', 'confirmation' => 'DELETE_DATA', 'data_categories' => ['location_context']])->assertOk();
        $this->assertDatabaseMissing('location_contexts', ['id' => $records[0]]);
        $this->assertDatabaseHas('location_contexts', ['id' => $records[1]]);
        $this->assertDatabaseHas('location_contexts', ['id' => $records[2]]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    }

    public function test_legacy_web_deletion_uses_the_same_obligation_check_and_requires_reauthentication(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        DB::table('participatory_finance_listings')->insert([
            'reference' => (string) Str::uuid(), 'borrower_user_id' => $user->id, 'purpose' => 'Test obligation',
            'target_amount_minor' => 500000, 'funded_amount_minor' => 500000, 'term_days' => 90,
            'status' => 'funded', 'lender_of_record' => 'Recorded lender', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->from('/account/delete')->delete('/account/delete', ['phone' => $user->phone, 'pin' => '482951', 'confirmation' => 'DELETE'])
            ->assertRedirect('/account/delete')->assertSessionHas('deletion_blockers');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->from('/account/delete')->delete('/account/delete', ['phone' => $user->phone, 'pin' => '000000', 'confirmation' => 'DELETE'])
            ->assertRedirect('/account/delete')->assertSessionHas('error');
    }

'''
marker = '    public function test_wrong_password_cannot_delete_account(): void'
assert marker in s
p.write_text(s.replace(marker, methods + marker))

# Durable collection evidence blocks immediately, with no misleading pending request.
p = Path('apps/api/tests/Feature/EssentialsDurableCollectionsTest.php')
if p.exists():
    s = p.read_text().replace("'pending_obligations'", "'blocked_obligations'")
    p.write_text(s)
print('Payroll, referral, account ownership and legacy channel regressions prepared.')
