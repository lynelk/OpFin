<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\Institution;
use App\Models\KycCase;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionReadinessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_kyc_and_operations_can_review_it(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);

        $caseId = $this->postJson('/api/kyc/cases', [
            'national_id' => 'CM1234567890',
            'provider' => 'manual',
            'evidence' => ['document_type' => 'national_id'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.kyc_case.status', KycCase::STATUS_PENDING_REVIEW)
            ->json('data.kyc_case.id');

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OPERATIONS]));

        $this->patchJson("/api/admin/kyc/cases/{$caseId}", [
            'status' => KycCase::STATUS_VERIFIED,
            'review_notes' => 'Verified against provider evidence.',
        ])
            ->assertOk()
            ->assertJsonPath('data.kyc_case.status', KycCase::STATUS_VERIFIED);

        $this->assertDatabaseHas('audit_logs', ['event' => 'kyc.reviewed']);
    }

    public function test_customer_can_grant_and_revoke_versioned_consent(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);

        $consentId = $this->postJson('/api/consents', [
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-consent-v1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.consent.status', ConsentRecord::STATUS_GRANTED)
            ->json('data.consent.id');

        $this->deleteJson("/api/consents/{$consentId}")
            ->assertOk()
            ->assertJsonPath('data.consent.status', ConsentRecord::STATUS_REVOKED);

        $this->assertDatabaseHas('audit_logs', ['event' => 'consent.revoked']);
    }

    public function test_credit_decision_refers_application_without_crb_and_records_clear_crb_gate(): void
    {
        [$customer, $application] = $this->createApplicationWithKycAndConsent();
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operations);

        $this->postJson("/api/admin/loan-applications/{$application->id}/decision")
            ->assertOk()
            ->assertJsonPath('data.decision.status', 'referred')
            ->assertJsonPath('data.decision.reason_codes.0', 'CRB_REPORT_REQUIRED');

        $this->postJson('/api/admin/crb-reports', [
            'user_id' => $customer->id,
            'provider' => 'licensed-crb-provider',
            'provider_reference' => 'CRB-REF-001',
            'status' => CrbReport::STATUS_CLEAR,
            'score' => 720,
        ])->assertCreated();

        $this->postJson("/api/admin/loan-applications/{$application->id}/decision")
            ->assertOk()
            ->assertJsonPath('data.decision.status', 'referred')
            ->assertJsonPath('data.decision.reason_codes.2', 'CRB_CLEAR');
    }

    public function test_operations_can_create_reconciliation_support_and_compliance_records(): void
    {
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operations);

        MobileMoneyTransaction::create([
            'user_id' => $operations->id,
            'provider' => 'mtn',
            'direction' => MobileMoneyTransaction::DIRECTION_DISBURSEMENT,
            'amount_minor' => 100000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'idempotency_key' => 'mm-prod-1',
            'internal_reference' => 'INT-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
        ]);

        $this->postJson('/api/admin/reconciliation-runs', [
            'provider' => 'mtn',
            'business_date' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.item_count', 1);

        $this->getJson('/api/admin/reconciliation-runs')
            ->assertOk()
            ->assertJsonPath('data.runs.0.provider', 'mtn');

        $itemId = $this->getJson('/api/admin/reconciliation-runs/1/items')
            ->assertOk()
            ->json('data.items.0.id');

        $this->patchJson("/api/admin/reconciliation-items/{$itemId}", [
            'status' => 'matched',
            'provider_amount_minor' => 100000,
            'notes' => 'Provider statement matches system transaction.',
        ])
            ->assertOk()
            ->assertJsonPath('data.item.status', 'matched');

        $this->postJson('/api/admin/support-cases', [
            'customer_id' => $operations->id,
            'category' => 'payment',
            'subject' => 'Payment status check',
            'description' => 'Customer requested help reconciling a repayment.',
        ])->assertCreated();

        $this->getJson('/api/admin/support-cases')
            ->assertOk()
            ->assertJsonPath('data.support_cases.0.category', 'payment');

        $caseId = $this->getJson('/api/admin/support-cases')->json('data.support_cases.0.id');

        $this->patchJson("/api/admin/support-cases/{$caseId}", [
            'status' => 'resolved',
            'note' => 'Resolved after confirming provider settlement.',
        ])
            ->assertOk()
            ->assertJsonPath('data.support_case.status', 'resolved')
            ->assertJsonPath('data.support_case.notes.0.note', 'Resolved after confirming provider settlement.');

        $this->postJson('/api/admin/compliance-reports', [
            'report_type' => 'monthly_credit_register',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/admin/compliance-reports')
            ->assertOk()
            ->assertJsonPath('data.reports.0.report_type', 'monthly_credit_register');

        $reportId = $this->getJson('/api/admin/compliance-reports')->json('data.reports.0.id');

        $this->postJson("/api/admin/compliance-reports/{$reportId}/exports", [
            'format' => 'csv',
        ])
            ->assertCreated()
            ->assertJsonPath('data.export.format', 'csv');
    }

    private function createApplicationWithKycAndConsent(): array
    {
        $institution = Institution::create([
            'name' => 'Production Readiness Institution',
            'address' => 'Kampala',
            'phone' => '256700009999',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'nin_status' => 'VALID',
        ]);

        KycCase::create([
            'user_id' => $customer->id,
            'provider' => 'manual',
            'national_id' => 'CM1234567890',
            'status' => KycCase::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-consent-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'api',
            'granted_at' => now(),
        ]);

        $product = LoanProduct::create([
            'name' => 'Production Readiness Loan',
            'type' => 'Cash',
            'institution_id' => $institution->id,
        ]);

        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 30,
        ]);

        $application = LoanApplication::create([
            'user_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 250000,
            'status' => 'Pending',
            'reason' => 'Production readiness test',
        ]);

        return [$customer, $application];
    }
}
