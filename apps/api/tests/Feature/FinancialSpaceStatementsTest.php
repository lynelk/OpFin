<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpaceStatementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_club_can_import_reconcile_and_generate_bank_style_statement(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Horizon Investment Club',
            'country' => 'UG',
            'currency' => 'UGX',
        ])->assertCreated();

        $spaceId = (int) $space->json('data.space.id');

        $account = $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Operating Bank Account',
            'account_type' => 'bank',
            'institution_name' => 'Example Bank',
            'account_reference' => '012345678901',
            'currency' => 'UGX',
            'opening_balance_minor' => 1000000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()
            ->assertJsonPath('data.account.account_reference_masked', '••••••••8901');

        $accountId = (int) $account->json('data.account.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'transaction_reference' => 'DEP-001',
            'transaction_type' => 'member_contribution',
            'direction' => 'credit',
            'amount_minor' => 250000,
            'description' => 'September member contributions',
            'transaction_date' => '2026-09-05',
        ])->assertCreated();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'transaction_reference' => 'INV-001',
            'transaction_type' => 'investment_purchase',
            'direction' => 'debit',
            'amount_minor' => 100000,
            'description' => 'Investment purchase',
            'transaction_date' => '2026-09-10',
        ])->assertCreated();

        $csv = implode("\n", [
            'Date,Value Date,Description,Reference,Debit,Credit,Balance',
            '2026-09-05,2026-09-05,September member contributions,DEP-001,,250000,1250000',
            '2026-09-10,2026-09-10,Investment purchase,INV-001,100000,,1150000',
        ]);

        $file = UploadedFile::fake()->createWithContent('example-bank-september.csv', $csv);

        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [
                'statement_file' => $file,
                'mapping' => json_encode([
                    'date' => 'Date',
                    'value_date' => 'Value Date',
                    'description' => 'Description',
                    'reference' => 'Reference',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                    'balance' => 'Balance',
                ]),
                'minor_unit_exponent' => 0,
                'opening_balance_minor' => 1000000,
                'closing_balance_minor' => 1150000,
            ],
            ['Accept' => 'application/json']
        )->assertCreated()
            ->assertJsonPath('data.import.row_count', 2);

        $importId = (int) $import->json('data.import.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.import.status', 'reconciled')
            ->assertJsonPath('data.import.matched_count', 2)
            ->assertJsonPath('data.import.exception_count', 0)
            ->assertJsonPath('data.import.summary.closing_balance_variance_minor', 0);

        $statement = $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statements",
            ['from' => '2026-09-01', 'to' => '2026-09-30']
        )->assertCreated()
            ->assertJsonPath('data.statement.opening_balance_minor', 1000000)
            ->assertJsonPath('data.statement.total_credits_minor', 250000)
            ->assertJsonPath('data.statement.total_debits_minor', 100000)
            ->assertJsonPath('data.statement.closing_balance_minor', 1150000)
            ->assertJsonPath('data.statement.transaction_count', 2)
            ->assertJsonPath('data.statement.reconciliation_status', 'reconciled')
            ->assertJsonCount(2, 'data.rows');

        $statementNumber = (string) $statement->json('data.statement.statement_number');
        $statementId = (int) $statement->json('data.statement.id');
        $statementHash = (string) $statement->json('data.statement.content_hash');
        $this->assertStringStartsWith('OFS-', $statementNumber);

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'transaction_reference' => 'LATE-001',
            'transaction_type' => 'late_correction',
            'direction' => 'credit',
            'amount_minor' => 50000,
            'description' => 'Later book entry after statement issue',
            'transaction_date' => '2026-09-20',
        ])->assertCreated();

        $this->getJson("/api/financial-spaces/{$spaceId}/statements/{$statementId}")
            ->assertOk()
            ->assertJsonPath('data.statement.content_hash', $statementHash)
            ->assertJsonCount(2, 'data.rows');

        $html = $this->get(
            "/api/financial-spaces/{$spaceId}/statements/{$statementId}/html",
            ['Accept' => 'text/html']
        )->assertOk();

        $html->assertSee('OpFin', false)
            ->assertSee('Financial Space Statement', false)
            ->assertSee('Horizon Investment Club', false)
            ->assertSee('Opening balance', false)
            ->assertSee('Closing balance', false)
            ->assertSee('not a statement issued by Example Bank', false);

        $this->get(
            "/api/financial-spaces/{$spaceId}/statements/{$statementId}/csv"
        )->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_statement_import_is_idempotent_and_duplicate_external_file_is_not_reprocessed(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Steady Growth Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Bank',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $csv = "Date,Description,Reference,Debit,Credit\n2026-09-01,Contribution,C1,,50000\n";
        $payload = [
            'mapping' => json_encode([
                'date' => 'Date',
                'description' => 'Description',
                'reference' => 'Reference',
                'debit' => 'Debit',
                'credit' => 'Credit',
            ]),
        ];

        $first = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [...$payload, 'statement_file' => UploadedFile::fake()->createWithContent('bank.csv', $csv)],
            ['Accept' => 'application/json']
        )->assertCreated();

        $second = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [...$payload, 'statement_file' => UploadedFile::fake()->createWithContent('bank-copy.csv', $csv)],
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->assertSame($first->json('data.import.id'), $second->json('data.import.id'));
        $this->assertDatabaseCount('financial_space_statement_imports', 1);
        $this->assertDatabaseCount('financial_space_statement_rows', 1);
    }

    public function test_member_can_read_and_generate_statement_but_cannot_administer_treasury(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Transparent Wealth Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Club Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
            'opening_balance_minor' => 300000,
        ])->assertCreated()->json('data.account.id');

        $member = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/financial-spaces/{$spaceId}/treasury/accounts")
            ->assertOk()
            ->assertJsonCount(1, 'data.accounts');

        $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statements",
            ['from' => '2026-09-01', 'to' => '2026-09-30']
        )->assertCreated()
            ->assertJsonPath('data.statement.opening_balance_minor', 300000)
            ->assertJsonPath('data.statement.closing_balance_minor', 300000);

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Forbidden Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertForbidden();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'direction' => 'credit',
            'amount_minor' => 50000,
            'description' => 'Should fail',
            'transaction_date' => '2026-09-05',
        ])->assertForbidden();
    }

    public function test_personal_space_cannot_be_turned_into_investment_club_treasury(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $spaceId = (int) $this->getJson('/api/financial-spaces')
            ->assertOk()
            ->json('data.spaces.0.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Wrong boundary',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertStatus(422);
    }
}
