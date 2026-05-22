<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestorDemoSliceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_complete_investor_demo_credit_flow(): void
    {
        [$customer, $product, $term] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->postJson('/api/demo/consent', [
            'purpose' => 'credit_processing',
        ])->assertOk()
            ->assertJsonPath('data.status', 'granted');

        $applicationResponse = $this->postJson('/api/demo/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $customer->institution_id,
            'amount' => 250000,
            'reason' => 'Investor demo school fees',
        ])->assertCreated()
            ->assertJsonPath('data.decision.status', 'approved')
            ->assertJsonPath('data.decision.reason_codes.0', 'KYC_VERIFIED')
            ->assertJsonPath('data.offer.status', 'pending_acceptance');

        $offerId = $applicationResponse->json('data.offer.id');

        $acceptanceResponse = $this->postJson("/api/demo/loan-offers/{$offerId}/accept")
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'accepted')
            ->assertJsonPath('data.loan.status', 'Disbursed');

        $loanId = $acceptanceResponse->json('data.loan.id');

        $this->assertDatabaseHas('loan_applications', ['user_id' => $customer->id, 'status' => 'Disbursed']);
        $this->assertDatabaseHas('loans', ['id' => $loanId, 'loan_application_id' => $applicationResponse->json('data.application.id')]);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'direction' => MobileMoneyTransaction::DIRECTION_DISBURSEMENT,
            'status' => MobileMoneyTransaction::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'demo.repayment_schedule.generated']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'demo.ledger_entries.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'demo.disbursement.recorded']);
        $this->assertGreaterThan(0, Loan::findOrFail($loanId)->schedules()->count());
        $this->assertGreaterThan(0, JournalEntry::count());
        $this->assertGreaterThanOrEqual(5, AuditLog::where('event', 'like', 'demo.%')->count());
    }

    public function test_credit_application_requires_demo_consent(): void
    {
        [$customer, $product, $term] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->postJson('/api/demo/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $customer->institution_id,
            'amount' => 250000,
            'reason' => 'Investor demo school fees',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Credit processing consent is required for the investor demo.');
    }

    public function test_consent_revocation_blocks_future_demo_credit_processing(): void
    {
        [$customer, $product, $term] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->postJson('/api/demo/consent', ['purpose' => 'credit_processing'])->assertOk();
        $this->deleteJson('/api/demo/consent')->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->postJson('/api/demo/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $customer->institution_id,
            'amount' => 250000,
            'reason' => 'Investor demo school fees',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Credit processing consent is required for the investor demo.');
    }

    public function test_customer_cannot_access_demo_admin_snapshot(): void
    {
        [$customer] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->getJson('/api/demo/admin/investor-snapshot')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Admin access is required for the investor demo snapshot.');
    }

    public function test_demo_offer_acceptance_cannot_be_replayed(): void
    {
        [$customer, $product, $term] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->postJson('/api/demo/consent', ['purpose' => 'credit_processing'])->assertOk();
        $application = $this->postJson('/api/demo/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $customer->institution_id,
            'amount' => 250000,
            'reason' => 'Investor demo school fees',
        ])->json('data');

        $this->postJson("/api/demo/loan-offers/{$application['offer']['id']}/accept")
            ->assertOk();
        $this->postJson("/api/demo/loan-offers/{$application['offer']['id']}/accept")
            ->assertStatus(400)
            ->assertJsonPath('message', 'This investor demo offer is not pending acceptance.');

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('mobile_money_transactions', 1);
    }

    public function test_admin_can_view_complete_investor_demo_snapshot(): void
    {
        [$customer, $product, $term] = $this->seedDemoBasics();
        Sanctum::actingAs($customer);

        $this->postJson('/api/demo/consent', ['purpose' => 'credit_processing']);
        $application = $this->postJson('/api/demo/loan-applications', [
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $customer->institution_id,
            'amount' => 250000,
            'reason' => 'Investor demo school fees',
        ])->json('data');
        $this->postJson("/api/demo/loan-offers/{$application['offer']['id']}/accept");

        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'institution_id' => $customer->institution_id,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/demo/admin/investor-snapshot')
            ->assertOk()
            ->assertJsonCount(1, 'data.customers')
            ->assertJsonPath('data.applications.0.id', $application['application']['id'])
            ->assertJsonCount(1, 'data.decisions')
            ->assertJsonCount(1, 'data.offers')
            ->assertJsonCount(1, 'data.loans')
            ->assertJsonPath('data.mobile_money.0.provider', 'mock')
            ->assertJsonFragment(['event' => 'demo.loan_offer.accepted']);
    }

    private function seedDemoBasics(): array
    {
        $institution = Institution::create([
            'name' => 'OpFin Demo Institution',
            'address' => 'Kampala',
            'phone' => '256700000100',
            'email' => 'demo-institution@opfin.test',
        ]);

        $customer = User::factory()->create([
            'name' => 'Investor Demo Customer',
            'phone' => '256700000001',
            'email' => 'customer.demo@opfin.test',
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'national_id' => 'CM000000000001',
            'date_of_birth' => '1994-04-12',
            'nin_status' => 'VALID',
        ]);

        $product = LoanProduct::create([
            'name' => 'Investor Demo Salary Advance',
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

        Account::create(['name' => 'Airtel Disbursement', 'balance' => 1000000]);
        Account::create(['name' => 'Investor Demo Salary Advance Portfolio', 'loan_product_id' => $product->id, 'balance' => 0]);

        return [$customer, $product, $term];
    }
}
