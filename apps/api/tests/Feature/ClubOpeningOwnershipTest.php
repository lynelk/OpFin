<?php

namespace Tests\Feature;

use App\Models\ClubBook;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubOpeningOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $checker;

    private int $bookId;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['role' => 'customer']);
        $this->checker = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($this->owner);
        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club', 'name' => 'Synthetic ownership test club',
            'country' => 'UG', 'currency' => 'UGX',
        ])->assertCreated()->json('data.space.id');
        FinancialSpaceMembership::create([
            'financial_space_id' => $space, 'user_id' => $this->checker->id,
            'role' => 'treasurer', 'status' => 'active', 'joined_at' => now(), 'approved_at' => now(),
        ]);
        $this->bookId = $this->postJson('/api/financial-spaces/'.$space.'/accounting/books', [
            'currency' => 'UGX', 'ownership_model' => 'capital_accounts',
            'cutover_date' => now()->toDateString(), 'valuation_max_age_days' => 30,
        ])->assertCreated()->json('data.book.id');
        $this->base = '/api/financial-spaces/'.$space.'/accounting/books/'.$this->bookId;
    }

    private function opening(array $members, array $balances): array
    {
        Sanctum::actingAs($this->owner);

        return $this->postJson($this->base.'/instructions', [
            'type' => 'opening', 'business_date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'payload' => ['members' => $members, 'balances' => $balances,
                'evidence_reference' => 'SYNTHETIC-OPENING-EVIDENCE'],
        ])->assertCreated()->json('data.instruction');
    }

    public function test_positive_ownerless_assets_cannot_activate_a_capital_account_book(): void
    {
        $item = $this->opening([], [
            ['account_code' => 'OTHER_ASSETS', 'direction' => 'debit', 'amount_minor' => 1000],
            ['account_code' => 'RETAINED_EARNINGS', 'direction' => 'credit', 'amount_minor' => 1000],
        ]);
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', [
            'payload_hash' => $item['payload_hash'],
        ])->assertStatus(409);
        $this->assertSame('draft', ClubBook::findOrFail($this->bookId)->status);
        $this->assertDatabaseHas('club_instructions', ['id' => $item['id'], 'status' => 'pending']);
        $this->assertDatabaseMissing('club_journals', ['book_id' => $this->bookId]);
        $this->assertDatabaseMissing('club_nav_snapshots', ['book_id' => $this->bookId]);
        Http::assertNothingSent();
    }

    public function test_positive_assets_with_explicit_member_capital_remain_valid(): void
    {
        $item = $this->opening([
            ['user_id' => $this->owner->id, 'capital_minor' => 1000],
        ], [
            ['account_code' => 'OTHER_ASSETS', 'direction' => 'debit', 'amount_minor' => 1000],
        ]);
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', [
            'payload_hash' => $item['payload_hash'],
        ])->assertOk()->assertJsonPath('data.instruction.status', 'approved');
        $this->assertSame('active', ClubBook::findOrFail($this->bookId)->status);
        $this->assertDatabaseHas('club_members', [
            'book_id' => $this->bookId, 'user_id' => $this->owner->id, 'capital_minor' => 1000,
        ]);
        Http::assertNothingSent();
    }

    public function test_genuinely_empty_opening_does_not_require_invented_member_capital(): void
    {
        $item = $this->opening([], []);
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', [
            'payload_hash' => $item['payload_hash'],
        ])->assertOk()->assertJsonPath('data.instruction.status', 'approved');
        $this->assertDatabaseMissing('club_members', ['book_id' => $this->bookId]);
        $this->assertDatabaseMissing('club_journals', ['book_id' => $this->bookId]);
        Http::assertNothingSent();
    }
}
