<?php

namespace Tests\Feature;

use App\Models\FinancialSpaceTransaction;
use App\Models\FinancialSpaceTreasuryAccount;
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
            'idempotency_key' => 'treasury-test-001',
            'transaction_reference' => 'DEP-001',
            'transaction_type' => 'member_contribution',
            'direction' => 'credit',
            'amount_minor' => 250000,
            'description' => 'September member contributions',
            'transaction_date' => '2026-09-05',
        ])->assertCreated();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-002',
            'transaction_reference' => 'INV-001',
            'transaction_type' => 'investment_purchase',
            'direction' => 'debit',
            'amount_minor' => 100000,
            'description' => 'Investment purchase',
            'transaction_date' => '2026-09-10',
        ])->assertCreated();

        $accountState = FinancialSpaceTreasuryAccount::query()->findOrFail($accountId);
        $this->assertSame('2026-09-01', $accountState->balance_as_of?->toDateString());
        $this->assertSame(1150000, (int) $accountState->current_balance_minor);
        $this->assertNotNull($accountState->current_balance_as_of);

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
                'balance_as_of' => '2026-09-01',
                'closing_balance_minor' => 1150000,
            ],
            ['Accept' => 'application/json']
        )->assertCreated()
            ->assertJsonPath('data.import.row_count', 2);

        $importId = (int) $import->json('data.import.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.import.status', 'ready_for_confirmation')
            ->assertJsonPath('data.import.confirmation_status', 'ready')
            ->assertJsonPath('data.import.matched_count', 2)
            ->assertJsonPath('data.import.exception_count', 0)
            ->assertJsonPath('data.import.summary.closing_balance_variance_minor', 0)
            ->assertJsonCount(0, 'data.import.review_todos');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm", [
            'note' => 'Reviewed against September bank statement.',
        ])->assertOk()
            ->assertJsonPath('data.import.confirmation_status', 'confirmed');

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

        DB::table('financial_spaces')->where('id', $spaceId)->update(['name' => 'Renamed Club']);
        DB::table('financial_space_treasury_accounts')->where('id', $accountId)->update([
            'account_name' => 'Renamed Account',
            'institution_name' => 'Renamed Bank',
        ]);

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-003',
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
            ->assertDontSee('Renamed Club', false)
            ->assertDontSee('Renamed Bank', false)
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

    public function test_member_can_read_issued_statement_but_cannot_administer_or_issue_treasury_records(): void
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
            'balance_as_of' => '2026-09-01',
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

        Sanctum::actingAs($owner);
        $issued = $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statements",
            ['from' => '2026-09-01', 'to' => '2026-09-30']
        )->assertCreated();
        $statementId = (int) $issued->json('data.statement.id');

        Sanctum::actingAs($member);

        $this->getJson("/api/financial-spaces/{$spaceId}/treasury/accounts")
            ->assertOk()
            ->assertJsonCount(1, 'data.accounts');

        $this->getJson("/api/financial-spaces/{$spaceId}/statements/{$statementId}")
            ->assertOk()
            ->assertJsonPath('data.statement.opening_balance_minor', 300000)
            ->assertJsonPath('data.statement.closing_balance_minor', 300000);

        $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statements",
            ['from' => '2026-09-01', 'to' => '2026-09-30']
        )->assertForbidden();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Forbidden Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertForbidden();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-004',
            'direction' => 'credit',
            'amount_minor' => 50000,
            'description' => 'Should fail',
            'transaction_date' => '2026-09-05',
        ])->assertForbidden();
    }

    public function test_authorised_finance_role_can_manually_match_exception_without_forcing_amount_mismatch(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Reconcile Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Club Bank',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $transactionId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-005',
            'transaction_reference' => 'BOOK-900',
            'direction' => 'credit',
            'amount_minor' => 90000,
            'description' => 'Contribution recorded earlier',
            'transaction_date' => '2026-09-01',
        ])->assertCreated()->json('data.transaction.id');

        $csv = "Date,Description,Reference,Debit,Credit\n2026-09-20,Contribution,BANK-XYZ,,90000\n";
        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('late.csv', $csv),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'reference' => 'Reference',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                ]),
            ],
            ['Accept' => 'application/json']
        )->assertCreated();

        $importId = (int) $import->json('data.import.id');
        $rowId = (int) $import->json('data.import.rows.0.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.import.status', 'review_required');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-rows/{$rowId}/match", [
            'transaction_id' => $transactionId,
        ])->assertOk()
            ->assertJsonPath('data.row.reconciliation_status', 'matched')
            ->assertJsonPath('data.row.match_method', 'manual');

        $this->assertDatabaseHas('financial_space_statement_imports', [
            'id' => $importId,
            'status' => 'ready_for_confirmation',
            'confirmation_status' => 'ready',
            'matched_count' => 1,
            'exception_count' => 0,
        ]);

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.import.confirmation_status', 'confirmed');

        $wrongTransactionId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-006',
            'transaction_reference' => 'WRONG-1',
            'direction' => 'debit',
            'amount_minor' => 1000,
            'description' => 'Wrong match candidate',
            'transaction_date' => '2026-09-20',
        ])->assertCreated()->json('data.transaction.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-rows/{$rowId}/match", [
            'transaction_id' => $wrongTransactionId,
        ])->assertUnprocessable();
    }

    public function test_smart_reconciliation_auto_matches_high_confidence_and_returns_only_review_todos(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Smart Match Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Main Bank',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $autoId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-007',
            'transaction_reference' => 'AUTO-001',
            'direction' => 'credit',
            'amount_minor' => 120000,
            'description' => 'Member capital contribution',
            'transaction_date' => '2026-09-05',
        ])->assertCreated()->json('data.transaction.id');

        $suggestedId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-008',
            'transaction_reference' => 'BOOK-002',
            'direction' => 'debit',
            'amount_minor' => 45000,
            'description' => 'Broker custody charge',
            'transaction_date' => '2026-09-08',
        ])->assertCreated()->json('data.transaction.id');

        $csv = implode("\n", [
            'Date,Description,Reference,Debit,Credit',
            '2026-09-05,Member capital contribution,AUTO-001,,120000',
            '2026-09-08,Custody service fee,BANK-X,45000,',
            '2026-09-09,External bank charge,BANK-FEE,5000,',
        ]);

        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('smart.csv', $csv),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'reference' => 'Reference',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                ]),
            ],
            ['Accept' => 'application/json']
        )->assertCreated();

        $importId = (int) $import->json('data.import.id');

        $review = $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.import.matched_count', 1)
            ->assertJsonPath('data.import.confirmation_status', 'not_ready');

        $todos = $review->json('data.import.review_todos');
        $this->assertCount(2, $todos);
        $this->assertSame('statement_row', $todos[0]['type']);
        $this->assertNotEmpty($todos[0]['suggested_matches']);
        $this->assertSame($suggestedId, $todos[0]['suggested_matches'][0]['transaction_id']);

        $this->assertDatabaseHas('financial_space_transactions', [
            'id' => $autoId,
            'reconciliation_status' => 'matched',
        ]);

        $suggestedRowId = (int) $todos[0]['row_id'];
        $this->postJson("/api/financial-spaces/{$spaceId}/statement-rows/{$suggestedRowId}/resolve", [
            'action' => 'match_transaction',
            'transaction_id' => $suggestedId,
        ])->assertOk()
            ->assertJsonPath('data.row.reconciliation_status', 'matched');

        $refreshed = $this->getJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}")
            ->assertOk();
        $remaining = $refreshed->json('data.import.review_todos') ?? [];
        $this->assertCount(1, $remaining);

        $missingRowId = (int) $remaining[0]['row_id'];
        $this->postJson("/api/financial-spaces/{$spaceId}/statement-rows/{$missingRowId}/resolve", [
            'action' => 'create_book_entry',
            'reason' => 'Bank fee was genuine and had not yet been entered in the club cashbook.',
        ])->assertOk()
            ->assertJsonPath('data.row.reconciliation_status', 'matched');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm", [
            'note' => 'All suggested and missing items reviewed.',
        ])->assertOk()
            ->assertJsonPath('data.import.confirmation_status', 'confirmed');

        $this->assertDatabaseHas('financial_space_transactions', [
            'financial_space_id' => $spaceId,
            'source_type' => 'statement_user_request',
            'amount_minor' => 5000,
            'direction' => 'debit',
            'reconciliation_status' => 'matched',
        ]);
    }

    public function test_exception_resolver_cannot_self_confirm_reconciliation(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Exception Review Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Main Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
            'opening_balance_minor' => 100000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        $checker = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $checker->id,
            'role' => 'treasurer',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookOnlyId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-009',
            'transaction_reference' => 'BOOK-ONLY',
            'direction' => 'credit',
            'amount_minor' => 20000,
            'description' => 'Pending deposit not yet on bank statement',
            'transaction_date' => '2026-09-03',
        ])->assertCreated()->json('data.transaction.id');

        $csv = "Date,Description,Reference,Debit,Credit,Balance\n2026-09-03,External only fee,EXT-1,1000,,99000\n";
        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('exceptions.csv', $csv),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'reference' => 'Reference',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                    'balance' => 'Balance',
                ]),
                'closing_balance_minor' => 99000,
            ],
            ['Accept' => 'application/json']
        )->assertCreated();
        $importId = (int) $import->json('data.import.id');

        $review = $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk();
        $todos = $review->json('data.import.review_todos');
        $this->assertNotEmpty($todos);

        $statementTodo = collect($todos)->firstWhere('type', 'statement_row');
        $bookTodo = collect($todos)->firstWhere('type', 'book_transaction');
        $varianceTodo = collect($todos)->firstWhere('type', 'balance_variance');

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-rows/{$statementTodo['row_id']}/resolve", [
            'action' => 'mark_external_only',
            'reason' => 'Confirmed as a bank-originated fee not separately posted in the club cashbook.',
        ])->assertOk();

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/book-transactions/{$bookOnlyId}/accept", [
            'reason' => 'Deposit was recorded in OpFin before bank cut-off and will appear on the next statement.',
        ])->assertOk();

        if ($varianceTodo) {
            $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/balance-variance", [
                'reason' => 'Variance accepted because of statement cut-off timing documented above.',
            ])->assertOk();
        }

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm", [
            'note' => 'Resolver must not approve their own exception.',
        ])->assertUnprocessable();

        Sanctum::actingAs($checker);
        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm", [
            'note' => 'Independently confirmed with documented timing and bank-fee exceptions.',
        ])->assertOk()
            ->assertJsonPath('data.import.confirmation_status', 'confirmed_with_exceptions');

        Sanctum::actingAs($owner);
        if ($varianceTodo) {
            $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/balance-variance", [
                'reason' => 'Confirmed evidence must now be immutable.',
            ])->assertStatus(409);
        }
    }

    public function test_consolidated_statement_covers_all_accounts_and_keeps_currencies_separate(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Multi Asset Club',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.space.id');

        $ugxAccountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'UGX Operating',
            'account_type' => 'bank',
            'institution_name' => 'Uganda Bank',
            'currency' => 'UGX',
            'opening_balance_minor' => 1000000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        $usdAccountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'USD Custody',
            'account_type' => 'custodian',
            'institution_name' => 'Global Custodian',
            'currency' => 'USD',
            'opening_balance_minor' => 5000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$ugxAccountId}/transactions", [
            'idempotency_key' => 'treasury-test-010',
            'direction' => 'credit',
            'amount_minor' => 250000,
            'description' => 'Member contributions',
            'transaction_date' => '2026-09-05',
        ])->assertCreated();

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$usdAccountId}/transactions", [
            'idempotency_key' => 'treasury-test-011',
            'direction' => 'debit',
            'amount_minor' => 500,
            'description' => 'Custody fee',
            'transaction_date' => '2026-09-06',
        ])->assertCreated();

        $this->postJson("/api/financial-spaces/{$spaceId}/assets", [
            'asset_type' => 'property',
            'name' => 'Club property',
            'value_minor' => 5000000,
            'currency' => 'UGX',
        ])->assertCreated();

        $this->postJson("/api/financial-spaces/{$spaceId}/obligations", [
            'kind' => 'payable',
            'direction' => 'i_owe',
            'counterparty_name' => 'Property manager',
            'amount_minor' => 300000,
            'currency' => 'UGX',
        ])->assertCreated();

        $response = $this->postJson("/api/financial-spaces/{$spaceId}/statements/consolidated", [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ])->assertCreated()
            ->assertJsonPath('data.statement.statement_scope', 'consolidated')
            ->assertJsonPath('data.statement.opening_balance_minor', null)
            ->assertJsonPath('data.totals_by_currency.UGX.opening_balance_minor', 1000000)
            ->assertJsonPath('data.totals_by_currency.UGX.closing_balance_minor', 1250000)
            ->assertJsonPath('data.totals_by_currency.USD.opening_balance_minor', 5000)
            ->assertJsonPath('data.totals_by_currency.USD.closing_balance_minor', 4500)
            ->assertJsonPath('data.position_by_currency.UGX.recorded_assets_minor', 5000000)
            ->assertJsonPath('data.position_by_currency.UGX.amount_owed_minor', 300000)
            ->assertJsonCount(2, 'data.sections');

        $statementId = (int) $response->json('data.statement.id');

        $this->get("/api/financial-spaces/{$spaceId}/statements/{$statementId}/html", [
            'Accept' => 'text/html',
        ])->assertOk()
            ->assertSee('Consolidated Financial Space Statement', false)
            ->assertSee('Summary by currency', false)
            ->assertSee('UGX Operating', false)
            ->assertSee('USD Custody', false)
            ->assertSee('Currencies are reported separately', false);

        $this->get("/api/financial-spaces/{$spaceId}/statements/{$statementId}/csv")
            ->assertOk()
            ->assertSee('Account,Currency,Date', false);
    }

    public function test_manual_treasury_posting_idempotency_is_canonical_and_conflict_safe(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Idempotent Treasury Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Idempotent Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $payload = [
            'idempotency_key' => 'treasury-idempotency-001',
            'transaction_reference' => 'IDEMP-001',
            'direction' => 'credit',
            'amount_minor' => 25000,
            'description' => 'Member contribution',
            'transaction_date' => '2026-09-04',
        ];

        $first = $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions",
            $payload,
        )->assertCreated();
        $second = $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions",
            $payload,
        )->assertCreated();

        $this->assertSame($first->json('data.transaction.id'), $second->json('data.transaction.id'));
        $this->assertDatabaseCount('financial_space_transactions', 1);
        $this->assertSame(
            25000,
            (int) FinancialSpaceTreasuryAccount::query()->findOrFail($accountId)->current_balance_minor,
        );

        $conflicting = $payload;
        $conflicting['amount_minor'] = 30000;

        $this->postJson(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions",
            $conflicting,
        )->assertStatus(409);
        $this->assertDatabaseCount('financial_space_transactions', 1);
        $this->assertSame(
            25000,
            (int) FinancialSpaceTreasuryAccount::query()->findOrFail($accountId)->current_balance_minor,
        );
    }

    public function test_opening_balance_variance_blocks_confirmation_even_when_closing_balance_matches(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Opening Control Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Controlled Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
            'opening_balance_minor' => 100000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-012',
            'transaction_reference' => 'FEE-001',
            'direction' => 'debit',
            'amount_minor' => 1000,
            'description' => 'Bank fee',
            'transaction_date' => '2026-09-03',
        ])->assertCreated();

        $csv = "Date,Description,Reference,Debit,Credit\n2026-09-03,Bank fee,FEE-001,1000,\n";
        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('opening-mismatch.csv', $csv),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'reference' => 'Reference',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                ]),
                'opening_balance_minor' => 90000,
                'closing_balance_minor' => 99000,
            ],
            ['Accept' => 'application/json']
        )->assertCreated();
        $importId = (int) $import->json('data.import.id');

        $review = $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.import.summary.opening_balance_variance_minor', 10000)
            ->assertJsonPath('data.import.summary.closing_balance_variance_minor', 0)
            ->assertJsonPath('data.import.confirmation_status', 'not_ready');

        $varianceTodo = collect($review->json('data.import.review_todos'))
            ->firstWhere('type', 'balance_variance');
        $this->assertSame(10000, (int) $varianceTodo['opening_balance_variance_minor']);
        $this->assertSame(0, (int) $varianceTodo['closing_balance_variance_minor']);

        $this->postJson("/api/financial-spaces/{$spaceId}/statement-imports/{$importId}/confirm")
            ->assertUnprocessable();
    }

    public function test_treasury_economic_entries_and_opening_baseline_are_append_only(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Append Only Club',
        ])->assertCreated()->json('data.space.id');

        $accountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Append Only Account',
            'account_type' => 'bank',
            'currency' => 'UGX',
            'opening_balance_minor' => 50000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        $transactionId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts/{$accountId}/transactions", [
            'idempotency_key' => 'treasury-test-013',
            'transaction_reference' => 'LOCK-001',
            'direction' => 'credit',
            'amount_minor' => 10000,
            'description' => 'Locked cashbook event',
            'transaction_date' => '2026-09-02',
        ])->assertCreated()->json('data.transaction.id');

        try {
            FinancialSpaceTransaction::query()->findOrFail($transactionId)->update(['amount_minor' => 99999]);
            $this->fail('Treasury transaction economics must be append-only.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        try {
            FinancialSpaceTreasuryAccount::query()->findOrFail($accountId)->update([
                'opening_balance_minor' => 40000,
            ]);
            $this->fail('Opening balance must lock after the first cashbook transaction.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('locked', $exception->getMessage());
        }

        $this->assertSame(10000, (int) FinancialSpaceTransaction::query()->findOrFail($transactionId)->amount_minor);
        $account = FinancialSpaceTreasuryAccount::query()->findOrFail($accountId);
        $this->assertSame(50000, (int) $account->opening_balance_minor);
        $this->assertSame('2026-09-01', $account->balance_as_of?->toDateString());
    }

    public function test_statement_money_import_uses_exact_minor_unit_precision(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Precision Club',
        ])->assertCreated()->json('data.space.id');

        $ugxAccountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'UGX Precision',
            'account_type' => 'bank',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $fractionalUgx = "Date,Description,Debit,Credit\n2026-09-03,Fractional UGX,10.50,\n";
        $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$ugxAccountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('fractional-ugx.csv', $fractionalUgx),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                ]),
                'minor_unit_exponent' => 0,
            ],
            ['Accept' => 'application/json']
        )->assertUnprocessable();

        $usdAccountId = (int) $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'USD Precision',
            'account_type' => 'custodian',
            'currency' => 'USD',
        ])->assertCreated()->json('data.account.id');

        $usdCsv = "Date,Description,Debit,Credit,Balance\n2026-09-03,Custody fee,10.50,,89.50\n";
        $import = $this->post(
            "/api/financial-spaces/{$spaceId}/treasury/accounts/{$usdAccountId}/statement-imports",
            [
                'statement_file' => UploadedFile::fake()->createWithContent('usd.csv', $usdCsv),
                'mapping' => json_encode([
                    'date' => 'Date',
                    'description' => 'Description',
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                    'balance' => 'Balance',
                ]),
                'minor_unit_exponent' => 2,
            ],
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->assertSame(1050, (int) $import->json('data.import.rows.0.amount_minor'));
        $this->assertSame(8950, (int) $import->json('data.import.rows.0.running_balance_minor'));
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
