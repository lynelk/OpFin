<?php

namespace Tests\Feature;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatementReviewTest extends TestCase
{
    use RefreshDatabase;

    /** Opening 1,000; the second row's balance should be 1,300, so the chain needs review. */
    private const DISCREPANT_CSV = "date,reference,description,direction,amount_minor,balance_minor\n2026-09-02,R1,Receipt,credit,500,1500\n2026-09-05,R2,Payment,debit,200,1400\n";

    private User $admin;

    private User $uploader;

    private User $reviewer;

    private User $secondReviewer;

    private FinancialSpace $space;

    private string $base;

    private int $issuer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 08:00:00');
        config(['financial_intelligence.enabled' => true]);
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->uploader = User::factory()->create(['phone' => '256772004321']);
        $this->reviewer = User::factory()->create();
        $this->secondReviewer = User::factory()->create();
        $this->space = $this->institution();
        $this->grant($this->uploader, 'analyst');
        $this->grant($this->reviewer, 'reviewer');
        $this->grant($this->secondReviewer, 'reviewer');
        $this->base = '/api/financial-spaces/'.$this->space->id.'/intelligence';
        $this->issuer = $this->issuer();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reviewers_see_needs_review_statements_with_masked_identity_and_source_lines(): void
    {
        $id = $this->upload();
        Sanctum::actingAs($this->reviewer);
        $response = $this->getJson($this->base.'/statement-reviews')->assertOk()
            ->assertJsonPath('data.items.0.statement_id', $id)
            ->assertJsonPath('data.items.0.uploader', 'Member with phone ending 4321')
            ->assertJsonPath('data.items.0.findings.0.code', 'running_balance_mismatch')
            ->assertJsonPath('data.items.0.assurance.review', 'needs_review');
        $this->assertStringNotContainsString('256772004321', $response->getContent());
        $this->assertNotEmpty($response->json('data.items.0.permitted_decisions'));

        Sanctum::actingAs($this->uploader);
        $this->getJson($this->base.'/statement-reviews')->assertForbidden();
    }

    public function test_reviewers_cannot_decide_their_own_uploads(): void
    {
        $id = $this->upload($this->reviewer);
        $this->decide($id, $this->reviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'explained_by_customer', 'notes' => 'Explained'])
            ->assertForbidden();
    }

    public function test_resolution_needs_reasons_and_never_confirms_authenticity(): void
    {
        $id = $this->upload();
        $this->decide($id, $this->reviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'explained_by_customer'], 'r1')->assertUnprocessable();
        $this->decide($id, $this->reviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'issuer_confirmed_alteration', 'notes' => 'x'], 'r2')->assertUnprocessable();
        $this->decide($id, $this->reviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'explained_by_customer', 'notes' => 'Reversal explained by the member'], 'r3')
            ->assertCreated()->assertJsonPath('data.review', 'resolved_with_reasons')->assertJsonPath('data.source_authenticity', 'unconfirmed');
        $this->decide($id, $this->reviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'explained_by_customer', 'notes' => 'Reversal explained by the member'], 'r3')
            ->assertCreated();
        $this->decide($id, $this->reviewer, ['decision' => 'escalated', 'reason_code' => 'needs_senior_review'], 'r3')->assertStatus(409);

        Sanctum::actingAs($this->uploader);
        $detail = $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.review', 'resolved_with_reasons')
            ->assertJsonPath('data.assurance.source_authenticity', 'unconfirmed')
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->assertJsonPath('data.reviews.0.decision', 'resolved_no_concern')
            ->json('data');
        $this->assertArrayNotHasKey('notes', $detail['reviews'][0], 'Reviewer notes stay internal.');
    }

    public function test_rejection_needs_issuer_evidence_and_an_appeal_goes_to_another_reviewer_once(): void
    {
        $id = $this->upload();
        $this->decide($id, $this->reviewer, ['decision' => 'rejected', 'reason_code' => 'issuer_confirmed_alteration'], 'x1')->assertUnprocessable();
        $this->decide($id, $this->reviewer, ['decision' => 'rejected', 'reason_code' => 'issuer_confirmed_alteration', 'evidence_reference' => 'ISSUER-LETTER-SYN-1'], 'x2')
            ->assertCreated()->assertJsonPath('data.review', 'rejected_with_reasons');

        Sanctum::actingAs($this->uploader);
        $this->getJson($this->base.'/statements/'.$id)->assertOk()->assertJsonPath('data.can_appeal', true)
            ->assertJsonPath('data.next_action', 'This statement cannot be used. If you think this is wrong, you can appeal once.');
        $this->postJson($this->base.'/statements/'.$id.'/appeal', ['reason' => 'The issuer letter refers to another account'], ['Idempotency-Key' => 'appeal-1'])
            ->assertCreated()->assertJsonPath('data.review', 'appealed');

        $this->decide($id, $this->reviewer, ['decision' => 'rejected', 'reason_code' => 'not_the_declared_account'], 'x3')->assertForbidden();
        $this->decide($id, $this->secondReviewer, ['decision' => 'rejected', 'reason_code' => 'not_the_declared_account'], 'x4')->assertCreated();
        Sanctum::actingAs($this->uploader);
        $this->postJson($this->base.'/statements/'.$id.'/appeal', ['reason' => 'Again'], ['Idempotency-Key' => 'appeal-2'])->assertStatus(409);
    }

    public function test_escalated_reviews_need_a_space_administrator(): void
    {
        $id = $this->upload();
        $this->decide($id, $this->reviewer, ['decision' => 'escalated', 'reason_code' => 'needs_senior_review'], 'e1')->assertCreated();
        $this->decide($id, $this->secondReviewer, ['decision' => 'resolved_no_concern', 'reason_code' => 'other_documented', 'notes' => 'ok'], 'e2')->assertForbidden();
        $this->decide($id, $this->admin, ['decision' => 'resolved_no_concern', 'reason_code' => 'other_documented', 'notes' => 'Senior review complete'], 'e3')
            ->assertCreated()->assertJsonPath('data.review', 'resolved_with_reasons');
    }

    public function test_a_resubmission_replaces_only_the_uploaders_own_statement_and_keeps_history(): void
    {
        $id = $this->upload();
        $this->decide($id, $this->reviewer, ['decision' => 'resubmission_requested', 'reason_code' => 'balances_do_not_reconcile'], 's1')->assertCreated();

        $replacement = $this->upload($this->uploader, 'replacement', ['supersedes_statement_id' => $id]);
        $this->assertSame($id, (int) DB::table('fi_statements')->where('id', $replacement)->value('supersedes_statement_id'));
        $this->assertSame('superseded_by_resubmission', json_decode(DB::table('fi_statements')->where('id', $id)->value('assurance'), true)['review']);
        $this->assertSame(['resubmission_requested', 'superseded'], DB::table('fi_statement_reviews')->where('fi_statement_id', $id)->orderBy('id')->pluck('decision')->all());

        $other = $this->upload($this->reviewer, 'reviewer-own');
        $this->uploadResponse($this->uploader, 'hijack', ['supersedes_statement_id' => $other])->assertUnprocessable();
    }

    public function test_personal_spaces_have_no_review_queue(): void
    {
        $owner = User::factory()->create();
        $personal = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => 'personal', 'name' => 'Synthetic personal space',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        FinancialSpaceMembership::query()->create(['financial_space_id' => $personal->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/financial-spaces/'.$personal->id.'/intelligence/statement-reviews')->assertForbidden();
    }

    public function test_review_decisions_are_append_only(): void
    {
        $id = $this->upload();
        $this->decide($id, $this->reviewer, ['decision' => 'escalated', 'reason_code' => 'needs_senior_review'], 'a1')->assertCreated();
        $this->expectException(QueryException::class);
        DB::table('fi_statement_reviews')->update(['decision' => 'resolved_no_concern']);
    }

    private function decide(int $id, User $actor, array $body, string $key = 'review')
    {
        Sanctum::actingAs($actor);

        return $this->postJson($this->base.'/statements/'.$id.'/reviews', $body, ['Idempotency-Key' => $key]);
    }

    private function upload(?User $actor = null, string $key = 'statement', array $extra = []): int
    {
        return (int) $this->uploadResponse($actor ?? $this->uploader, $key, $extra)->assertCreated()->json('data.id');
    }

    private function uploadResponse(User $actor, string $key, array $extra = [])
    {
        Sanctum::actingAs($actor);

        return $this->post($this->base.'/statements', [
            'statement_file' => UploadedFile::fake()->createWithContent('statement.csv', self::DISCREPANT_CSV),
            'issuer_version_id' => $this->issuer, 'currency' => 'UGX', 'period_start' => '2026-09-01', 'period_end' => '2026-09-25',
            'opening_balance_minor' => 1000, 'closing_balance_minor' => 1300, 'account_reference' => 'SYN-ACCOUNT-1',
            'authority_reference' => 'synthetic-authority', 'authority_confirmed' => '1', 'authority_expires_at' => '2026-10-01',
            'purpose' => 'financial_analysis', ...$extra,
        ], ['Accept' => 'application/json', 'Idempotency-Key' => $key]);
    }

    private function institution(): FinancialSpace
    {
        $space = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => 'sacco', 'name' => 'Synthetic review SACCO',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $this->admin->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        foreach ([$this->uploader, $this->reviewer, $this->secondReviewer] as $member) {
            FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $member->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()]);
        }
        $plan = DB::table('opfin_plans')->insertGetId(['code' => 'fi-review-'.Str::random(12), 'name' => 'Synthetic review test plan', 'price_minor' => 0,
            'currency' => 'UGX', 'billing_period' => 'monthly', 'status' => 'active', 'metadata' => json_encode(['entitlements' => ['financial_intelligence']]),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('financial_space_entitlements')->insert(['financial_space_id' => $space->id, 'opfin_plan_id' => $plan, 'entitlement_key' => 'financial_intelligence',
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        return $space;
    }

    private function grant(User $user, string $role): void
    {
        DB::table('fi_grants')->insert(['financial_space_id' => $this->space->id, 'user_id' => $user->id, 'role' => $role,
            'expires_at' => now()->addMonth(), 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function issuer(): int
    {
        return DB::table('fi_issuer_versions')->insertGetId(['public_id' => (string) Str::uuid(), 'issuer_code' => 'SYNTHETIC_ONLY',
            'legal_name' => 'Synthetic test issuer, not a real licence', 'country' => 'UG', 'product_type' => 'bank', 'regulator' => 'Synthetic authority',
            'licence_reference' => 'TEST', 'evidence_reference' => 'fixture-only', 'valid_from' => '2020-01-01', 'valid_until' => '2026-12-31',
            'review_due_on' => '2026-12-31', 'proposed_by' => $this->admin->id, 'approved_by' => $this->reviewer->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
    }
}
