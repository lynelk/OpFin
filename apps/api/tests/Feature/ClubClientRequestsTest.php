<?php

namespace Tests\Feature;

use App\Models\ClubClientRequest;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubClientRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private int $space;
    private int $book;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 26)->startOfDay());
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($this->owner);
        $this->space = (int) $this->postJson('/api/financial-spaces', ['type' => 'investment_club',
            'name' => 'Synthetic Recovery Club', 'country' => 'UG', 'currency' => 'UGX'])->assertCreated()->json('data.space.id');
        $this->book = (int) $this->postJson('/api/financial-spaces/'.$this->space.'/accounting/books', [
            'currency' => 'UGX', 'ownership_model' => 'unitised', 'cutover_date' => '2026-09-26',
            'initial_unit_price_minor' => 1000, 'valuation_max_age_days' => 30])->assertCreated()->json('data.book.id');
        $this->base = '/api/financial-spaces/'.$this->space.'/accounting/books/'.$this->book.'/client-requests/';
    }

    private function envelope(string $key = 'synthetic-opening-request'): array
    {
        return ['type' => 'opening', 'business_date' => '2026-09-26', 'idempotency_key' => $key,
            'payload' => ['members' => [], 'balances' => [], 'evidence_reference' => 'SYNTHETIC-EMPTY-OPENING']];
    }

    private function prepare(string $key = 'synthetic-opening-request'): array
    {
        return $this->postJson($this->base.'prepare/instruction', $this->envelope($key))
            ->assertCreated()->json('data.client_request');
    }

    private function identity(array $saved): array
    {
        return ['reference' => $saved['reference'], 'content_hash' => $saved['content_hash']];
    }

    public function test_preparation_is_durable_encrypted_and_does_not_submit_or_approve(): void
    {
        $saved = $this->prepare();
        $raw = DB::table('club_client_requests')->first();
        $this->assertStringNotContainsString('SYNTHETIC-EMPTY-OPENING', $raw->envelope);
        $this->assertSame('SYNTHETIC-EMPTY-OPENING', ClubClientRequest::firstOrFail()->envelope['payload']['evidence_reference']);
        $this->assertDatabaseCount('club_instructions', 0);
        $this->getJson('/api/accounting/saved-requests')->assertOk()
            ->assertJsonPath('data.requests.0.reference', $saved['reference']);
        Http::assertNothingSent();
    }

    public function test_lost_submission_result_is_recovered_without_duplicate_instruction(): void
    {
        $saved = $this->prepare();
        $one = $this->postJson($this->base.'submit', $this->identity($saved))->assertOk()->json('data.instruction');
        $this->getJson('/api/accounting/saved-requests')->assertOk()->assertJsonPath('data.requests.0.status', 'submitted');
        $two = $this->postJson($this->base.'submit', $this->identity($saved))->assertOk()->json('data.instruction');
        $this->assertSame($one['id'], $two['id']);
        $this->assertSame('pending', $two['status']);
        $this->assertDatabaseCount('club_instructions', 1);
        $this->assertDatabaseCount('club_journals', 0);
    }

    public function test_preparation_replay_returns_same_saved_identity(): void
    {
        $one = $this->prepare();
        $two = $this->prepare();
        $this->assertSame($one, $two);
        $this->assertDatabaseCount('club_client_requests', 1);
    }

    public function test_different_meaning_cannot_replace_the_saved_request_key(): void
    {
        $this->prepare();
        $changed = $this->envelope();
        $changed['payload']['description'] = 'A different opening meaning';
        $this->postJson($this->base.'prepare/instruction', $changed)->assertUnprocessable();
        $this->assertDatabaseCount('club_client_requests', 1);
    }

    public function test_second_unresolved_request_is_not_silently_substituted(): void
    {
        $this->prepare();
        $this->postJson($this->base.'prepare/instruction', $this->envelope('another-key'))->assertUnprocessable();
        $this->assertDatabaseCount('club_client_requests', 1);
    }

    public function test_acknowledgement_releases_the_slot_but_does_not_approve(): void
    {
        $saved = $this->prepare();
        $this->postJson($this->base.'submit', $this->identity($saved))->assertOk();
        $this->postJson($this->base.'acknowledge', $this->identity($saved))->assertOk()->assertJsonPath('data.active', false);
        $this->getJson('/api/accounting/saved-requests')->assertOk()->assertJsonCount(0, 'data.requests');
        $this->prepare('another-opening-key');
        $this->assertDatabaseHas('club_instructions', ['status' => 'pending']);
        $this->assertDatabaseCount('club_journals', 0);
    }

    public function test_unsubmitted_request_can_be_cancelled_but_never_submitted_afterwards(): void
    {
        $saved = $this->prepare();
        $this->postJson($this->base.'cancel', $this->identity($saved))->assertOk();
        $this->postJson($this->base.'submit', $this->identity($saved))->assertUnprocessable();
        $this->assertDatabaseCount('club_instructions', 0);
    }

    public function test_a_submitted_request_cannot_be_cancelled_as_if_unsent(): void
    {
        $saved = $this->prepare();
        $this->postJson($this->base.'submit', $this->identity($saved))->assertOk();
        $this->postJson($this->base.'cancel', $this->identity($saved))->assertUnprocessable();
        $this->assertDatabaseCount('club_instructions', 1);
    }

    public function test_another_member_cannot_recover_the_makers_saved_payload(): void
    {
        $saved = $this->prepare();
        $other = User::factory()->create(['role' => 'customer']);
        FinancialSpaceMembership::create(['financial_space_id' => $this->space, 'user_id' => $other->id,
            'role' => 'treasurer', 'status' => 'active', 'joined_at' => now(), 'approved_at' => now()]);
        Sanctum::actingAs($other);
        $this->getJson('/api/accounting/saved-requests')->assertOk()->assertJsonCount(0, 'data.requests');
        $this->postJson($this->base.'submit', $this->identity($saved))->assertNotFound();
    }

    public function test_revoked_membership_cannot_read_saved_club_instructions(): void
    {
        $saved = $this->prepare();
        DB::table('financial_space_memberships')->where('financial_space_id', $this->space)
            ->where('user_id', $this->owner->id)->update(['status' => 'inactive']);
        $this->getJson('/api/accounting/saved-requests')->assertOk()->assertJsonCount(0, 'data.requests');
        $this->postJson($this->base.'submit', $this->identity($saved))->assertForbidden();
    }

    public function test_hash_mismatch_does_not_create_an_accounting_instruction(): void
    {
        $saved = $this->prepare();
        $saved['content_hash'] = str_repeat('0', 64);
        $this->postJson($this->base.'submit', $this->identity($saved))->assertStatus(409);
        $this->assertDatabaseCount('club_instructions', 0);
    }

    public function test_unknown_envelope_fields_do_not_enter_saved_requests(): void
    {
        $this->postJson($this->base.'prepare/instruction', $this->envelope() + ['status' => 'approved'])->assertUnprocessable();
        $this->assertDatabaseCount('club_client_requests', 0);
    }
}
