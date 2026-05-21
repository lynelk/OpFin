<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackendCheckpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_update_credit_application_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $application = $this->createLoanApplication();

        $this->postJson("/api/loan-applications/{$application->id}/status", [
            'status' => 'Approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'loan_application.status_updated',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_operations_user_can_approve_pending_transaction(): void
    {
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operations);

        $transaction = Transaction::create([
            'user_id' => $operations->id,
            'institution_id' => Institution::create([
                'name' => 'Checkpoint Institution',
                'address' => 'Kampala',
                'phone' => '256700000099',
                'email' => 'checkpoint@example.test',
            ])->id,
            'loan_application_id' => $this->createLoanApplication()->id,
            'loan_id' => null,
            'type' => 'Disbursement',
            'amount' => 100000,
            'phone' => '256700000099',
            'reference' => 'checkpoint-reference',
            'status' => 'Pending',
        ]);

        $this->mock(LoanService::class)
            ->shouldReceive('processSuccessfulTransaction')
            ->once();

        $this->patchJson("/api/transactions/{$transaction->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'transaction.approved',
            'actor_id' => $operations->id,
        ]);
    }

    public function test_successful_disbursement_processing_is_idempotent(): void
    {
        $application = $this->createLoanApplication();
        Account::create([
            'name' => 'Airtel Disbursement',
            'balance' => 200000,
        ]);

        $transaction = Transaction::create([
            'user_id' => $application->user_id,
            'institution_id' => $application->institution_id,
            'loan_application_id' => $application->id,
            'loan_id' => null,
            'type' => 'Disbursement',
            'amount' => 100000,
            'phone' => '256700000099',
            'reference' => 'disbursement-idempotency-key',
            'status' => 'SUCCESSFUL',
        ]);

        $loanService = app(LoanService::class);
        $loanService->processSuccessfulTransaction($transaction);
        $loanService->processSuccessfulTransaction($transaction->fresh());

        $this->assertDatabaseCount('loans', 1);
        $this->assertSame(Loan::firstOrFail()->id, $transaction->fresh()->loan_id);
    }

    private function createLoanApplication(): LoanApplication
    {
        $institution = Institution::create([
            'name' => 'Checkpoint Institution',
            'address' => 'Kampala',
            'phone' => '256700000001',
            'email' => fake()->unique()->safeEmail(),
        ]);

        $user = User::factory()->create([
            'institution_id' => $institution->id,
            'nin_status' => 'VALID',
        ]);

        $product = LoanProduct::create([
            'name' => 'Checkpoint Loan',
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

        return LoanApplication::create([
            'user_id' => $user->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 100000,
            'status' => 'Pending',
            'reason' => 'Checkpoint test',
        ]);
    }
}
