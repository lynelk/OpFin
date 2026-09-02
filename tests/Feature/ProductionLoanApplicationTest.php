<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\Institution;
use App\Models\KycCase;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionLoanApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_application_is_created_for_assessment_without_disbursement(): void
    {
        [$user, $institution, $product, $term] = $this->eligibleCustomer();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/credit/applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount_minor' => 150000,
            'reason' => 'Education expense',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', 'Pending')
            ->assertJsonPath('data.application.amount', 150000)
            ->assertJsonPath('data.next_state', 'assessment');

        $this->assertDatabaseHas('loan_applications', [
            'user_id' => $user->id,
            'amount' => 150000,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'credit.application.submitted',
            'actor_id' => $user->id,
        ]);
    }

    public function test_customer_can_submit_one_simple_request_and_backend_selects_the_configured_credit_route(): void
    {
        [$user, $institution, $product, $term] = $this->eligibleCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/credit/applications', [
            'amount_minor' => 175000,
            'reason' => 'School fees',
        ])
            ->assertCreated()
            ->assertJsonPath('data.routing_mode', 'system_selected')
            ->assertJsonPath('data.application.loan_product_id', $product->id)
            ->assertJsonPath('data.application.loan_product_term_id', $term->id)
            ->assertJsonPath('data.application.institution_id', $institution->id)
            ->assertJsonPath('data.application.amount', 175000);

        $this->assertDatabaseCount('loan_applications', 1);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
    }

    public function test_partial_product_selection_is_rejected_instead_of_guessing_missing_product_configuration(): void
    {
        [$user, , $product] = $this->eligibleCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/credit/applications', [
            'loan_product_id' => $product->id,
            'amount_minor' => 100000,
            'reason' => 'Emergency expense',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'PARTIAL_PRODUCT_SELECTION');

        $this->assertDatabaseCount('loan_applications', 0);
    }

    public function test_legacy_application_post_is_a_safe_compatibility_alias_without_disbursement(): void
    {
        [$user, $institution, $product, $term] = $this->eligibleCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 125000,
            'reason' => 'Legacy client migration',
        ])
            ->assertCreated()
            ->assertJsonPath('data.application.amount', 125000)
            ->assertJsonPath('data.next_state', 'assessment');

        $this->assertDatabaseCount('loan_applications', 1);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_customer_cannot_submit_without_verified_current_kyc(): void
    {
        [$user, $institution, $product, $term] = $this->customerAndProduct();
        $this->grantCreditConsent($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/credit/applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount_minor' => 100000,
            'reason' => 'Emergency expense',
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code.0', 'KYC_VERIFICATION_REQUIRED');

        $this->assertDatabaseCount('loan_applications', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_customer_cannot_submit_without_active_credit_consent(): void
    {
        [$user, $institution, $product, $term] = $this->customerAndProduct();
        $this->verifyKyc($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/credit/applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount_minor' => 100000,
            'reason' => 'Emergency expense',
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code.0', 'CREDIT_CONSENT_REQUIRED');

        $this->assertDatabaseCount('loan_applications', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_customer_cannot_read_another_customers_application(): void
    {
        [$owner, $institution, $product, $term] = $this->eligibleCustomer();
        Sanctum::actingAs($owner);

        $applicationId = $this->postJson('/api/credit/applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount_minor' => 120000,
            'reason' => 'Household expense',
        ])->json('data.application.id');

        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($other);

        $this->getJson("/api/credit/applications/{$applicationId}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function eligibleCustomer(): array
    {
        [$user, $institution, $product, $term] = $this->customerAndProduct();
        $this->verifyKyc($user);
        $this->grantCreditConsent($user);

        return [$user, $institution, $product, $term];
    }

    private function customerAndProduct(): array
    {
        $institution = Institution::create([
            'name' => 'Production Credit Institution',
            'address' => 'Kampala',
            'phone' => '256700000501',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
        ]);

        $product = LoanProduct::create([
            'name' => 'Responsible Credit',
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
            'status' => 'Active',
        ]);

        return [$user, $institution, $product, $term];
    }

    private function verifyKyc(User $user): void
    {
        KycCase::create([
            'user_id' => $user->id,
            'provider' => 'test',
            'national_id' => 'CM'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
            'status' => KycCase::STATUS_VERIFIED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
            'expires_at' => now()->addYear(),
        ]);
    }

    private function grantCreditConsent(User $user): void
    {
        ConsentRecord::create([
            'user_id' => $user->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-consent-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'test',
            'granted_at' => now(),
        ]);
    }
}
