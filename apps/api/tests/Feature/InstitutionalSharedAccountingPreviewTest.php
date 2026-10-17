<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InstitutionalSharedAccountingPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class InstitutionalSharedAccountingPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('opfin.accounting.defer_institutional_evidence_for_nonproduction', true);
    }

    private function example(string $form = 'direct_joint_creditor'): array
    {
        return [
            'contract_form' => $form, 'currency' => 'UGX',
            'principal_amount_minor' => 100000,
            'cash_disbursement_minor' => 100000,
            'opfin_share_bps' => 4000,
        ];
    }

    public function test_provisional_direct_joint_creditor_splits_minor_units_exactly_and_never_posts(): void
    {
        $service = app(InstitutionalSharedAccountingPreviewService::class);
        $before = DB::table('ledger_transactions')->count();
        $result = $service->preview($this->example());
        $this->assertSame('provisional_direct_joint_creditor', $result['status']);
        $this->assertSame(40000, $result['illustrative_opfin_principal_minor']);
        $this->assertSame(60000, $result['illustrative_partner_principal_minor']);
        $this->assertSame(2, count($result['journals']));
        $this->assertFalse($result['ledger_posted']);
        $this->assertFalse($result['release_approved']);
        $this->assertFalse($result['shared_origination_enabled']);
        foreach ($result['journals'] as $journal) {
            $this->assertSame($journal['debit']['amount_minor'], $journal['credit']['amount_minor']);
        }
        $this->assertSame($before, DB::table('ledger_transactions')->count());
    }

    public function test_minor_unit_remainder_never_leaks_or_creates_fractional_money(): void
    {
        $result = app(InstitutionalSharedAccountingPreviewService::class)->preview([
            'contract_form' => 'direct_joint_creditor', 'currency' => 'UGX',
            'principal_amount_minor' => 1001, 'cash_disbursement_minor' => 1001,
            'opfin_share_bps' => 3333,
        ]);
        $this->assertSame(333, $result['illustrative_opfin_principal_minor']);
        $this->assertSame(668, $result['illustrative_partner_principal_minor']);
        $this->assertSame(1001, $result['illustrative_opfin_principal_minor'] + $result['illustrative_partner_principal_minor']);
    }

    public function test_non_joint_forms_are_not_misclassified_as_percent_owned_loan_assets(): void
    {
        $service = app(InstitutionalSharedAccountingPreviewService::class);
        foreach (['funded_participation', 'financial_guarantee', 'first_loss'] as $form) {
            $result = $service->preview($this->example($form));
            $this->assertSame('contract_classification_required', $result['status']);
            $this->assertSame([], $result['journals']);
            $this->assertFalse($result['release_approved']);
        }
    }

    public function test_unapproved_fee_recognition_prevents_a_fake_balanced_disbursement(): void
    {
        $input = $this->example();
        $input['cash_disbursement_minor'] = 97000;
        $result = app(InstitutionalSharedAccountingPreviewService::class)->preview($input);
        $this->assertSame('fee_policy_required', $result['status']);
        $this->assertSame([], $result['journals']);
    }

    public function test_documentary_deferral_is_reversible_without_ever_allowing_live_accounting(): void
    {
        config()->set('opfin.accounting.defer_institutional_evidence_for_nonproduction', false);
        $this->expectException(InvalidArgumentException::class);
        app(InstitutionalSharedAccountingPreviewService::class)->preview($this->example());
    }

    public function test_production_environment_is_forbidden_even_with_deferred_qa_setting(): void
    {
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->expectException(InvalidArgumentException::class);
            app(InstitutionalSharedAccountingPreviewService::class)->preview($this->example());
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_non_admin_cannot_ask_api_to_preview_contractual_postings(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $this->actingAs($user)
            ->postJson('/api/admin/institutional-assurance/shared-accounting-preview', $this->example())
            ->assertForbidden();
    }

    public function test_admin_can_preview_without_any_external_source_or_human_signature(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $this->actingAs($admin)
            ->postJson('/api/admin/institutional-assurance/shared-accounting-preview', $this->example())
            ->assertOk()
            ->assertJsonPath('data.mode', 'synthetic_read_only')
            ->assertJsonPath('data.shared_origination_enabled', false)
            ->assertJsonPath('data.illustrative_partner_principal_minor', 60000);
    }
}
