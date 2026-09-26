<?php

namespace Tests\Feature;

use App\Models\CreditProfile;
use App\Models\EssentialsAccount;
use App\Models\EssentialsBiller;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EssentialsSpaceAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $partner;

    private FinancialSpace $personal;

    private FinancialSpace $household;

    private int $partnerAccount;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->customer = User::factory()->create(['role' => 'customer', 'password' => Hash::make('123456')]);
        $this->partner = User::factory()->create(['role' => 'partner_api']);
        $this->personal = app(PersonalFinancialSpaceService::class)->ensure($this->customer);
        $this->household = FinancialSpace::create([
            'public_id' => (string) Str::uuid(), 'type' => 'household', 'name' => 'Synthetic household',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active',
        ]);
        FinancialSpaceMembership::create([
            'financial_space_id' => $this->household->id, 'user_id' => $this->customer->id,
            'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'approved_at' => now(),
        ]);
        CreditProfile::create([
            'user_id' => $this->customer->id, 'status' => CreditProfile::STATUS_READY,
            'composite_score' => 80, 'band' => 'Strong', 'coverage_percent' => 100,
            'credit_limit_minor' => 100000, 'current_exposure_minor' => 0,
            'available_to_borrow_minor' => 100000, 'amount_due_minor' => 0, 'total_outstanding_minor' => 0,
            'model_version' => 'synthetic', 'component_breakdown' => [], 'reason_codes' => [],
            'customer_explanations' => [], 'scored_at' => now(), 'expires_at' => now()->addDay(),
        ]);
        $this->partnerAccount = DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => (string) Str::uuid(), 'created_by' => $this->partner->id,
            'partner_name' => 'Synthetic embedded platform', 'partner_type' => 'platform', 'status' => 'active',
            'allowed_products' => json_encode(['essentials']), 'commercial_terms' => '{}',
            'approved_by' => $this->partner->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function grant(FinancialSpace $space): void
    {
        DB::table('essentials_partner_authorisations')->insert([
            'reference' => (string) Str::uuid(), 'user_id' => $this->customer->id,
            'partner_account_id' => $this->partnerAccount, 'financial_space_id' => $space->id,
            'scopes' => json_encode(['eligibility', 'status_read', 'account_write', 'quote_create']),
            'status' => 'active', 'granted_at' => now(), 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function account(FinancialSpace $space, string $reference): EssentialsAccount
    {
        return EssentialsAccount::create([
            'public_id' => (string) Str::uuid(), 'user_id' => $this->customer->id,
            'financial_space_id' => $space->id,
            'biller_id' => EssentialsBiller::where('code', 'UEDCL')->firstOrFail()->id,
            'account_reference' => $reference, 'account_reference_hash' => hash('sha256', strtoupper($reference)),
            'verification_status' => 'verified', 'verified_at' => now(), 'metadata' => [],
        ]);
    }

    private function advance(FinancialSpace $space, string $status = 'active'): int
    {
        $reference = Str::upper(Str::random(10));
        $account = $this->account($space, $reference);
        $lender = DB::table('partners')->insertGetId([
            'code' => $reference, 'name' => 'Synthetic third-party lender', 'partner_type' => 'financial_institution',
            'country' => 'UG', 'status' => 'active', 'adapter_key' => 'capital_mandate',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = DB::table('partner_products')->insertGetId([
            'partner_id' => $lender, 'code' => $reference, 'name' => 'Synthetic Essentials',
            'product_type' => 'essentials_credit', 'status' => 'active', 'country' => 'UG', 'currency' => 'UGX',
            'eligibility_rules' => '{}', 'pricing' => '{}', 'disclosures' => '{}', 'integration_config' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = DB::table('essentials_credit_lines')->insertGetId([
            'reference' => (string) Str::uuid(), 'user_id' => $this->customer->id,
            'financial_space_id' => $space->id, 'lender_partner_id' => $lender, 'partner_product_id' => $product,
            'approved_limit_minor' => 100000, 'available_limit_minor' => 50000, 'outstanding_minor' => 50000,
            'currency' => 'UGX', 'status' => 'active', 'decision_route' => 'capital_mandate',
            'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $quote = DB::table('essentials_quotes')->insertGetId([
            'reference' => (string) Str::uuid(), 'user_id' => $this->customer->id,
            'financial_space_id' => $space->id, 'essentials_account_id' => $account->id, 'credit_line_id' => $line,
            'purpose_category' => 'electricity', 'amount_minor' => 50000, 'total_repayment_minor' => 50000,
            'term_days' => 90, 'currency' => 'UGX', 'lender_partner_id' => $lender, 'partner_product_id' => $product,
            'status' => 'accepted', 'disclosure_hash' => hash('sha256', $reference), 'disclosure_snapshot' => '{}',
            'expires_at' => now()->addDay(), 'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $outstanding = $status === 'settled' ? 0 : 50000;

        return DB::table('essentials_advances')->insertGetId([
            'reference' => (string) Str::uuid(), 'quote_id' => $quote, 'user_id' => $this->customer->id,
            'financial_space_id' => $space->id, 'essentials_account_id' => $account->id,
            'lender_partner_id' => $lender, 'partner_product_id' => $product,
            'principal_minor' => 50000, 'principal_outstanding_minor' => $outstanding,
            'total_repayment_minor' => 50000, 'outstanding_minor' => $outstanding, 'currency' => 'UGX',
            'status' => $status, 'activated_at' => in_array($status, ['active', 'overdue', 'settled'], true) ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_household_permission_cannot_authorise_an_omitted_personal_context(): void
    {
        $this->grant($this->household);
        Sanctum::actingAs($this->partner);
        $this->getJson('/api/partner/essentials/customers/'.$this->customer->id.'/status?partner_account_id='.$this->partnerAccount)
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_omitted_context_returns_only_personal_advances_even_when_other_spaces_are_authorised(): void
    {
        $this->grant($this->personal);
        $this->grant($this->household);
        $personalAdvance = $this->advance($this->personal);
        $this->advance($this->household);
        Sanctum::actingAs($this->partner);
        $this->getJson('/api/partner/essentials/customers/'.$this->customer->id.'/status?partner_account_id='.$this->partnerAccount)
            ->assertOk()->assertJsonPath('data.financial_space_id', $this->personal->id)
            ->assertJsonCount(1, 'data.advances')->assertJsonPath('data.advances.0.id', $personalAdvance);
        Http::assertNothingSent();
    }

    public function test_removed_membership_invalidates_a_still_active_partner_grant(): void
    {
        $this->grant($this->household);
        DB::table('financial_space_memberships')->where('user_id', $this->customer->id)
            ->where('financial_space_id', $this->household->id)->update(['status' => 'inactive']);
        Sanctum::actingAs($this->partner);
        $this->getJson('/api/partner/essentials/customers/'.$this->customer->id.'/status?partner_account_id='.$this->partnerAccount.'&financial_space_id='.$this->household->id)
            ->assertForbidden();
    }

    public function test_partner_cannot_relocate_a_service_account_from_another_space(): void
    {
        $this->grant($this->household);
        $account = $this->account($this->personal, 'SYNTHETIC-METER-1');
        Sanctum::actingAs($this->partner);
        $this->postJson('/api/partner/essentials/customers/'.$this->customer->id.'/accounts', [
            'partner_account_id' => $this->partnerAccount, 'biller_id' => $account->biller_id,
            'financial_space_id' => $this->household->id, 'account_reference' => 'SYNTHETIC-METER-1',
            'source_platform' => 'Synthetic platform', 'customer_consent_reference' => 'synthetic-consent',
        ])->assertUnprocessable();
        $this->assertSame($this->personal->id, $account->fresh()->financial_space_id);
    }

    public function test_partner_cannot_access_a_soft_deleted_customer(): void
    {
        $this->grant($this->personal);
        $this->customer->delete();
        Sanctum::actingAs($this->partner);
        $this->getJson('/api/partner/essentials/customers/'.$this->customer->id.'/status?partner_account_id='.$this->partnerAccount)
            ->assertNotFound();
    }

    public function test_each_reservation_or_active_state_blocks_deletion_and_reuses_the_support_case(): void
    {
        $id = $this->advance($this->personal, 'funding_reserved');
        foreach (['funding_reserved', 'lender_funding_pending', 'lender_reversal_pending', 'fulfilment_pending', 'active', 'overdue', 'future_unknown_state'] as $status) {
            DB::table('essentials_advances')->where('id', $id)->update(['status' => $status]);
            $result = app(AccountDeletionService::class)->deleteOrRequest($this->customer, '123456', Request::create('/api/account', 'DELETE'));
            $this->assertSame('pending_obligations', $result['deletion_status'], $status);
            $this->assertContains('essentials_financing', $result['active_obligations']);
            $this->assertNull($this->customer->fresh()->deleted_at);
            $this->assertDatabaseHas('financial_spaces', ['id' => $this->personal->id]);
            $this->assertDatabaseHas('credit_profiles', ['user_id' => $this->customer->id]);
        }
        $this->assertSame(1, DB::table('support_cases')->where('customer_id', $this->customer->id)->where('category', 'account_deletion')->count());
        Http::assertNothingSent();
    }

    public function test_settled_zero_balance_does_not_permanently_block_account_deletion(): void
    {
        $id = $this->advance($this->personal, 'settled');
        $result = app(AccountDeletionService::class)->deleteOrRequest($this->customer, '123456', Request::create('/api/account', 'DELETE'));
        $this->assertSame('completed', $result['deletion_status']);
        $this->assertNotNull(User::withTrashed()->findOrFail($this->customer->id)->deleted_at);
        $this->assertDatabaseHas('essentials_advances', ['id' => $id, 'status' => 'settled']);
    }

    public function test_pending_collection_blocks_deletion_even_if_the_advance_is_marked_settled(): void
    {
        $id = $this->advance($this->personal, 'settled');
        DB::table('essentials_repayments')->insert([
            'reference' => (string) Str::uuid(), 'advance_id' => $id, 'user_id' => $this->customer->id,
            'amount_minor' => 1, 'currency' => 'UGX', 'status' => 'pending_provider_confirmation',
            'idempotency_key' => 'synthetic-pending-repayment', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $result = app(AccountDeletionService::class)->deleteOrRequest($this->customer, '123456', Request::create('/api/account', 'DELETE'));
        $this->assertSame('pending_obligations', $result['deletion_status']);
        $this->assertContains('essentials_repayment_pending', $result['active_obligations']);
    }

    public function test_inconsistent_settled_balance_requires_review_instead_of_deletion(): void
    {
        $id = $this->advance($this->personal, 'settled');
        DB::table('essentials_advances')->where('id', $id)->update(['outstanding_minor' => 1]);
        $result = app(AccountDeletionService::class)->deleteOrRequest($this->customer, '123456', Request::create('/api/account', 'DELETE'));
        $this->assertSame('pending_obligations', $result['deletion_status']);
        $this->assertContains('essentials_financing', $result['active_obligations']);
    }
}
