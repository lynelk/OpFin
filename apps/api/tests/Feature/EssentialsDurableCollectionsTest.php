<?php

namespace Tests\Feature;

use App\Domain\Essentials\AccountingRules;
use App\Models\CreditProfile;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsBiller;
use App\Models\EssentialsCollectionInstruction;
use App\Models\EssentialsRepayment;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\CpayEssentialsClient;
use App\Services\EssentialsCollectionRecovery;
use App\Services\EssentialsDurableCollections;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class EssentialsDurableCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private CustomerWallet $wallet;
    private EssentialsAdvance $advance;
    private int $poolId;
    private array $collectionResponses = [];
    private array $statusResponses = [];
    private int $providerCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 26)->startOfDay());
        Http::preventStrayRequests();
        config(['services.cpay.base_url' => 'https://synthetic-cpay.test', 'services.cpay.environment' => 'SANDBOX',
            'services.cpay.merchant_number' => 'SYNTHETIC-MERCHANT',
            'services.cpay.lender_repayment_path' => '/collections', 'services.cpay.transaction_status_path' => '/status']);
        $this->customer = User::factory()->create(['role' => 'customer', 'password' => Hash::make('123456')]);
        $space = app(PersonalFinancialSpaceService::class)->ensure($this->customer);
        $phone = CustomerPhoneNumber::create(['user_id' => $this->customer->id, 'phone' => $this->customer->phone,
            'kind' => 'primary', 'verified_at' => now()]);
        $this->wallet = CustomerWallet::create(['user_id' => $this->customer->id, 'phone_number_id' => $phone->id,
            'provider' => 'mobile_money', 'msisdn' => $this->customer->phone, 'status' => 'active',
            'is_default_repayment' => true, 'verified_at' => now()]);
        CreditProfile::create(['user_id' => $this->customer->id, 'status' => CreditProfile::STATUS_READY,
            'composite_score' => 80, 'band' => 'Strong', 'coverage_percent' => 100, 'credit_limit_minor' => 1000,
            'current_exposure_minor' => 115, 'available_to_borrow_minor' => 885, 'amount_due_minor' => 0,
            'total_outstanding_minor' => 115, 'component_breakdown' => [], 'reason_codes' => [],
            'customer_explanations' => [], 'model_version' => 'synthetic', 'scored_at' => now(), 'expires_at' => now()->addMonth()]);
        $partner = DB::table('partners')->insertGetId(['code' => 'SYNTHETIC-COLLECTION-LENDER',
            'name' => 'Synthetic Collection Lender', 'partner_type' => 'financial_institution', 'country' => 'UG',
            'status' => 'active', 'adapter_key' => 'capital_mandate', 'created_at' => now(), 'updated_at' => now()]);
        $this->poolId = DB::table('capital_mandates')->insertGetId(['reference' => (string) Str::uuid(),
            'owner_user_id' => $this->customer->id, 'partner_id' => $partner, 'mandate_type' => 'synthetic_fixture',
            'name' => 'Synthetic servicing test pool', 'committed_capital_minor' => 1000,
            'reserved_capital_minor' => 0, 'deployed_capital_minor' => 100, 'status' => 'approved',
            'investment_policy' => '{}', 'approved_by' => $this->customer->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        $product = DB::table('partner_products')->insertGetId(['partner_id' => $partner, 'code' => 'SYNTHETIC-COLLECTION-PRODUCT',
            'name' => 'Synthetic collection fixture', 'product_type' => 'essentials_credit', 'status' => 'active',
            'country' => 'UG', 'currency' => 'UGX', 'eligibility_rules' => '{}', 'pricing' => '{}',
            'disclosures' => '{}', 'integration_config' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $line = DB::table('essentials_credit_lines')->insertGetId(['reference' => (string) Str::uuid(),
            'user_id' => $this->customer->id, 'financial_space_id' => $space->id, 'lender_partner_id' => $partner,
            'partner_product_id' => $product, 'funding_pool_id' => $this->poolId, 'approved_limit_minor' => 1000,
            'available_limit_minor' => 900, 'outstanding_minor' => 100, 'currency' => 'UGX', 'status' => 'active',
            'decision_route' => 'capital_mandate', 'expires_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
        $account = EssentialsAccount::create(['public_id' => (string) Str::uuid(), 'user_id' => $this->customer->id,
            'financial_space_id' => $space->id, 'biller_id' => EssentialsBiller::where('code', 'UEDCL')->firstOrFail()->id,
            'account_reference' => 'SYNTHETIC-ACCOUNT', 'account_reference_hash' => hash('sha256', 'SYNTHETIC-ACCOUNT'),
            'verification_status' => 'verified', 'verified_at' => now()]);
        $quote = DB::table('essentials_quotes')->insertGetId(['reference' => (string) Str::uuid(),
            'user_id' => $this->customer->id, 'financial_space_id' => $space->id,
            'essentials_account_id' => $account->id, 'credit_line_id' => $line, 'purpose_category' => 'electricity',
            'amount_minor' => 100, 'interest_minor' => 10, 'fees_minor' => 5, 'total_repayment_minor' => 115,
            'term_days' => 90, 'currency' => 'UGX', 'lender_partner_id' => $partner, 'partner_product_id' => $product,
            'funding_pool_id' => $this->poolId, 'status' => 'accepted', 'disclosure_snapshot' => '{}',
            'disclosure_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay(), 'accepted_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        $this->advance = EssentialsAdvance::create(['reference' => (string) Str::uuid(), 'quote_id' => $quote,
            'user_id' => $this->customer->id, 'financial_space_id' => $space->id, 'essentials_account_id' => $account->id,
            'lender_partner_id' => $partner, 'partner_product_id' => $product, 'funding_pool_id' => $this->poolId,
            'principal_minor' => 100, 'principal_outstanding_minor' => 100, 'total_repayment_minor' => 115,
            'outstanding_minor' => 115, 'repaid_minor' => 0, 'currency' => 'UGX', 'status' => 'active',
            'lender_contract_reference' => 'SYNTHETIC-CONTRACT', 'activated_at' => now()]);
        DB::table('essentials_repayment_schedule_items')->insert(['advance_id' => $this->advance->id,
            'installment_number' => 1, 'due_date' => '2026-10-01', 'principal_original_minor' => 100,
            'principal_outstanding_minor' => 100, 'interest_original_minor' => 10, 'interest_outstanding_minor' => 10,
            'fees_original_minor' => 5, 'fees_outstanding_minor' => 5, 'total_original_minor' => 115,
            'total_outstanding_minor' => 115, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);
        $this->mock(CpayEssentialsClient::class, function ($mock): void {
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('collectRepayment')->andReturnUsing(function (): array {
                $this->providerCalls++;
                $response = array_shift($this->collectionResponses);
                if ($response instanceof \Throwable) { throw $response; }
                return $response ?? ['status' => 'PENDING'];
            });
            $mock->shouldReceive('status')->andReturnUsing(function (): array {
                $response = array_shift($this->statusResponses);
                if ($response instanceof \Throwable) { throw $response; }
                return $response ?? ['status' => 'PENDING'];
            });
        });
    }

    private function pay(int $amount, string $key = 'SYNTHETIC-KEY-001'): EssentialsRepayment
    {
        return app(EssentialsDurableCollections::class)->request($this->customer, $this->advance->id, $amount, $key, null);
    }

    public function test_success_is_applied_with_exact_original_allocation_and_separate_journal(): void
    {
        $corporate = DB::table('ledger_transactions')->count();
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-REF-1'];
        $repayment = $this->pay(25);
        $this->assertSame('successful', $repayment->status);
        $this->assertSame(10, $repayment->principal_applied_minor);
        $this->assertSame(AccountingRules::ALLOCATION_VERSION, $repayment->metadata['allocation_policy']);
        $this->assertSame(90, $this->advance->fresh()->outstanding_minor);
        $this->assertDatabaseHas('essentials_collection_allocations', ['principal_minor' => 10, 'interest_minor' => 10, 'fees_minor' => 5]);
        $this->assertDatabaseHas('essentials_servicing_journals', ['repayment_id' => $repayment->id,
            'debits_minor' => 25, 'credits_minor' => 25, 'event_type' => 'collection_applied']);
        $this->assertSame($corporate, DB::table('ledger_transactions')->count());
        $this->assertSame(90, (int) DB::table('capital_mandates')->where('id', $this->poolId)->value('deployed_capital_minor'));
    }

    public function test_identical_request_replay_does_not_send_or_post_again(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-REF-1'];
        $one = $this->pay(25);
        $two = $this->pay(25);
        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, $this->providerCalls);
        $this->assertDatabaseCount('essentials_servicing_journals', 1);
    }

    public function test_changed_wallet_semantics_cannot_reuse_the_same_key(): void
    {
        $this->collectionResponses[] = ['status' => 'PENDING', 'providerReference' => 'SYNTHETIC-PENDING'];
        $this->pay(25);
        $this->expectException(InvalidArgumentException::class);
        app(EssentialsDurableCollections::class)->request($this->customer, $this->advance->id, 25, 'SYNTHETIC-KEY-001', $this->wallet->id);
    }

    public function test_timeout_reservation_survives_and_reconciliation_does_not_resend(): void
    {
        $this->collectionResponses[] = new \RuntimeException('Synthetic lost response');
        $repayment = $this->pay(100);
        $this->assertSame('pending_provider_confirmation', $repayment->status);
        try { $this->pay(16, 'SYNTHETIC-KEY-002'); $this->fail('Overlapping collection must not be accepted.'); }
        catch (InvalidArgumentException) { $this->assertSame(1, $this->providerCalls); }
        $this->statusResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-RECOVERED'];
        $result = app(EssentialsDurableCollections::class)->reconcile($repayment);
        $this->assertSame('successful', $result->status);
        $this->assertSame(1, $this->providerCalls);
        $this->assertSame(15, $this->advance->fresh()->outstanding_minor);
    }

    public function test_unattributed_success_is_retained_for_review_without_debt_reduction(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS'];
        $repayment = $this->pay(25);
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseHas('essentials_collection_instructions', ['repayment_id' => $repayment->id, 'status' => 'exception']);
        $this->assertSame(115, $this->advance->fresh()->outstanding_minor);
        $this->assertDatabaseCount('essentials_servicing_journals', 0);
    }

    public function test_reversal_restores_original_schedule_and_lender_principal_once(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-REF-1'];
        $repayment = $this->pay(25);
        $this->statusResponses = [['status' => 'REVERSED', 'providerReference' => 'SYNTHETIC-REF-1'],
            ['status' => 'REVERSED', 'providerReference' => 'SYNTHETIC-REF-1']];
        app(EssentialsDurableCollections::class)->reconcile($repayment);
        app(EssentialsDurableCollections::class)->reconcile($repayment->fresh());
        $this->assertSame('reversed', $repayment->fresh()->status);
        $this->assertSame(115, $this->advance->fresh()->outstanding_minor);
        $this->assertSame(100, (int) DB::table('capital_mandates')->where('id', $this->poolId)->value('deployed_capital_minor'));
        $this->assertDatabaseCount('essentials_collection_allocations', 1);
        $this->assertDatabaseCount('essentials_servicing_journals', 2);
    }

    public function test_duplicate_provider_reference_is_not_applied_to_another_collection(): void
    {
        $this->collectionResponses = [['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-SAME-REF'],
            ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-SAME-REF']];
        $this->pay(25);
        $two = $this->pay(10, 'SYNTHETIC-KEY-002');
        $this->assertDatabaseHas('essentials_collection_instructions', ['repayment_id' => $two->id,
            'status' => 'exception', 'exception_code' => 'provider_reference_reused']);
        $this->assertSame(90, $this->advance->fresh()->outstanding_minor);
        $this->assertDatabaseCount('essentials_servicing_journals', 1);
    }

    public function test_new_route_does_not_receive_an_old_pending_instruction(): void
    {
        $this->collectionResponses[] = ['status' => 'PENDING', 'providerReference' => 'SYNTHETIC-PENDING'];
        $repayment = $this->pay(25);
        config(['services.cpay.base_url' => 'https://different-provider.test']);
        app(EssentialsDurableCollections::class)->reconcile($repayment);
        $this->assertSame(1, $this->providerCalls);
        $this->assertSame(115, $this->advance->fresh()->outstanding_minor);
    }

    public function test_failed_schedule_application_rolls_back_money_updates_but_retains_provider_finality(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-REF-1'];
        DB::table('capital_mandates')->where('id', $this->poolId)->update(['deployed_capital_minor' => 0]);
        try { $this->pay(25); $this->fail('The inconsistent funding position must prevent financial application.'); }
        catch (\RuntimeException) {}
        $instruction = EssentialsCollectionInstruction::firstOrFail();
        $this->assertSame('confirmed_unapplied', $instruction->status);
        $this->assertSame(115, $this->advance->fresh()->outstanding_minor);
        $this->assertDatabaseCount('essentials_collection_allocations', 0);
        $this->assertDatabaseCount('essentials_servicing_journals', 0);
        DB::table('capital_mandates')->where('id', $this->poolId)->update(['deployed_capital_minor' => 100]);
        $result = app(EssentialsDurableCollections::class)->reconcile(EssentialsRepayment::findOrFail($instruction->repayment_id));
        $this->assertSame('successful', $result->status);
        $this->assertSame(1, $this->providerCalls);
    }

    public function test_submitted_collection_cannot_be_locally_cancelled(): void
    {
        $repayment = $this->pay(25);
        $this->expectException(InvalidArgumentException::class);
        app(EssentialsCollectionRecovery::class)->cancelUnsubmitted($this->customer, $repayment->id, 'Customer requests cancellation');
    }

    public function test_account_closure_preserves_an_unresolved_collection_exception(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS'];
        $this->pay(25);
        $this->advance->update(['status' => 'settled', 'outstanding_minor' => 0, 'principal_outstanding_minor' => 0]);
        $result = app(AccountDeletionService::class)->deleteOrRequest($this->customer, '123456', Request::create('/api/account', 'DELETE'));
        $this->assertSame('pending_obligations', $result['deletion_status']);
        $this->assertContains('essentials_collection_reconciliation', $result['active_obligations']);
        $this->assertNull($this->customer->fresh()->deleted_at);
    }

    public function test_settled_wallet_deletion_does_not_delete_frozen_collection_evidence(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-REF-1'];
        $this->pay(115);
        $instruction = EssentialsCollectionInstruction::firstOrFail();
        $walletId = $this->wallet->id;
        $this->wallet->delete();
        $this->assertNull($instruction->fresh()->wallet_id);
        $this->assertSame($walletId, $instruction->fresh()->snapshot['wallet_id']);
        $this->assertDatabaseCount('essentials_servicing_journals', 1);
    }

    public function test_closed_login_does_not_prevent_a_verified_late_collection_reversal(): void
    {
        $this->collectionResponses[] = ['status' => 'SUCCESS', 'providerReference' => 'SYNTHETIC-LATE-REF'];
        $repayment = $this->pay(115);
        $this->customer->delete();
        $this->statusResponses[] = ['status' => 'REVERSED', 'providerReference' => 'SYNTHETIC-LATE-REF'];
        app(EssentialsDurableCollections::class)->reconcile($repayment);
        $this->assertSame('reversed', $repayment->fresh()->status);
        $this->assertSame(115, $this->advance->fresh()->outstanding_minor);
        $this->assertNotNull(User::withTrashed()->findOrFail($this->customer->id)->deleted_at);
        $this->assertSame(1, $this->providerCalls);
    }
}
