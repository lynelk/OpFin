<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies that every API endpoint produces the standard ApiEnvelope shape
 * { success: bool, message: string, data: mixed } that the Next.js frontend
 * expects. Each test maps directly to an opfinApi method in client.ts.
 */
class FrontEndAlignmentTest extends TestCase
{
    use RefreshDatabase;

    // POST /login → LoginResponse (data.user must include national_id, date_of_birth)
    public function test_login_response_includes_national_id_and_date_of_birth(): void
    {
        User::factory()->create([
            'phone' => '256700000001',
            'password' => bcrypt('Password1!@'),
            'role' => User::ROLE_CUSTOMER,
            'national_id' => 'CM12345678',
            'date_of_birth' => '1990-01-15',
            'nin_status' => 'VALID',
        ]);

        $this->postJson('/api/login', [
            'phone' => '256700000001',
            'password' => 'Password1!@',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => ['id', 'name', 'phone', 'role', 'nin_status', 'national_id', 'date_of_birth'],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.national_id', 'CM12345678')
            ->assertJsonPath('data.user.date_of_birth', '1990-01-15');
    }

    // GET /profile → Profile (data.user must include national_id, date_of_birth, nin_status)
    public function test_profile_response_includes_national_id_date_of_birth_nin_status(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'national_id' => 'CM87654321',
            'date_of_birth' => '1985-06-20',
            'nin_status' => 'PENDING',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'phone', 'role', 'national_id', 'date_of_birth', 'nin_status', 'institution_id'],
                    'permissions',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.national_id', 'CM87654321')
            ->assertJsonPath('data.user.date_of_birth', '1985-06-20')
            ->assertJsonPath('data.user.nin_status', 'PENDING');
    }

    // GET /loan-balance/{user} → { outstandingAmount } MUST be inside data, not at root
    public function test_loan_balance_outstanding_amount_is_nested_inside_data(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/loan-balance/{$user->id}")
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['outstandingAmount'],
            ])
            ->assertJsonPath('success', true);

        // Confirm outstandingAmount is NOT at the JSON root (the old broken shape)
        $this->assertNull($response->json('outstandingAmount'), 'outstandingAmount must live inside data, not at the response root');
        $this->assertIsNumeric($response->json('data.outstandingAmount'));
    }

    // GET /loan-applications/{user} → LoanApplication[] inside data
    public function test_loan_applications_list_returns_full_api_envelope(): void
    {
        [$user] = $this->createCustomerWithApplication();
        Sanctum::actingAs($user);

        $this->getJson("/api/loan-applications/{$user->id}")
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);

        $this->assertIsArray($this->getJson("/api/loan-applications/{$user->id}")->json('data'));
    }

    // POST /loan-applications/{id}/status → LoanApplication inside data with success + message
    public function test_loan_status_update_returns_full_api_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        [, $application] = $this->createCustomerWithApplication();
        Sanctum::actingAs($admin);

        $this->postJson("/api/loan-applications/{$application->id}/status", [
            'status' => 'Approved',
        ])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'status']])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Approved');
    }

    // GET /products → LoanProduct[] inside data
    public function test_products_list_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);

        $this->assertIsArray($this->getJson('/api/products')->json('data'));
    }

    // GET /institutions → Institution[] inside data
    public function test_institutions_list_returns_full_api_envelope(): void
    {
        Institution::create([
            'name' => 'Alignment Bank',
            'address' => 'Kampala',
            'phone' => '256700000099',
            'email' => 'alignment@bank.example',
        ]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/institutions')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);

        $this->assertIsArray($this->getJson('/api/institutions')->json('data'));
    }

    // GET /product-terms/{product} → ProductTerm[] inside data
    public function test_product_terms_list_returns_full_api_envelope(): void
    {
        $institution = Institution::create([
            'name' => 'Terms Bank',
            'address' => 'Kampala',
            'phone' => '256700000002',
            'email' => 'terms@bank.example',
        ]);
        $product = LoanProduct::create([
            'name' => 'Terms Product',
            'type' => 'Cash',
            'institution_id' => $institution->id,
        ]);
        LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 30,
        ]);

        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson("/api/product-terms/{$product->id}")
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);

        $this->assertIsArray($this->getJson("/api/product-terms/{$product->id}")->json('data'));
    }

    // GET /kyc/status → { latest_case: KycCase|null } inside data
    public function test_kyc_status_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/kyc/status')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['latest_case']])
            ->assertJsonPath('success', true);
    }

    // POST /kyc/cases → { kyc_case: KycCase } inside data
    public function test_kyc_case_submission_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/kyc/cases', [
            'national_id' => 'CM1234567890',
            'provider' => 'manual',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['kyc_case' => ['id', 'national_id', 'status']]])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kyc_case.national_id', 'CM1234567890');
    }

    // GET /consents → { consents: ConsentRecord[] } inside data
    public function test_consents_list_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/consents')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['consents']])
            ->assertJsonPath('success', true);
    }

    // POST /consents → { consent: ConsentRecord } inside data
    public function test_grant_consent_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $this->postJson('/api/consents', [
            'purpose' => 'credit_processing',
            'policy_version' => 'credit-consent-v1',
            'channel' => 'web',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['consent' => ['id', 'purpose', 'status']]])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consent.status', 'granted');
    }

    // DELETE /consents/{id} → { consent: ConsentRecord } inside data
    public function test_revoke_consent_returns_full_api_envelope(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $grant = $this->postJson('/api/consents', [
            'purpose' => 'credit_processing',
            'policy_version' => 'credit-consent-v1',
        ])->json('data.consent');

        $this->deleteJson("/api/consents/{$grant['id']}")
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['consent' => ['id', 'status']]])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consent.status', 'revoked');
    }

    // GET /admin/ledger-transactions → { ledger_transactions: LedgerTransaction[] } inside data
    public function test_admin_ledger_transactions_returns_full_api_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/ledger-transactions')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['ledger_transactions']])
            ->assertJsonPath('success', true);
    }

    // GET /admin/reconciliation-runs → { runs: ReconciliationRun[] } inside data
    public function test_admin_reconciliation_runs_returns_full_api_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reconciliation-runs')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['runs']])
            ->assertJsonPath('success', true);
    }

    // GET /admin/support-cases → { support_cases: SupportCase[] } inside data
    public function test_admin_support_cases_returns_full_api_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/support-cases')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['support_cases']])
            ->assertJsonPath('success', true);
    }

    // GET /admin/compliance-reports → { reports: ComplianceReport[] } inside data
    public function test_admin_compliance_reports_returns_full_api_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/compliance-reports')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['reports']])
            ->assertJsonPath('success', true);
    }

    // Unauthenticated requests must return 401, not 500
    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
        $this->getJson('/api/kyc/status')->assertStatus(401);
        $this->getJson('/api/consents')->assertStatus(401);
        $this->getJson('/api/products')->assertStatus(401);
    }

    private function createCustomerWithApplication(): array
    {
        $institution = Institution::create([
            'name' => 'Alignment Institution',
            'address' => 'Kampala',
            'phone' => '256700000010',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'nin_status' => 'VALID',
        ]);

        $product = LoanProduct::create([
            'name' => 'Alignment Product',
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
            'user_id' => $user->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 100000,
            'status' => 'Pending',
            'reason' => 'Alignment test',
        ]);

        return [$user, $application];
    }
}
