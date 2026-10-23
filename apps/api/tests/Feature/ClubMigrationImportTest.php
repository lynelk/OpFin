<?php

namespace Tests\Feature;

use App\Models\ClubBook;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubMigrationImportTest extends TestCase
{
    use RefreshDatabase;

    private function setupBook(): array
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $checker = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($owner);
        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Historical Investment Club',
            'country' => 'UG',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.space.id');
        FinancialSpaceMembership::create([
            'financial_space_id' => $space,
            'user_id' => $checker->id,
            'role' => 'treasurer',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
        ]);
        $book = $this->postJson('/api/financial-spaces/'.$space.'/accounting/books', [
            'currency' => 'UGX',
            'ownership_model' => 'capital_accounts',
            'cutover_date' => now()->toDateString(),
            'valuation_max_age_days' => 30,
        ])->assertCreated()->json('data.book.id');

        return [$owner, $checker, '/api/financial-spaces/'.$space.'/accounting/books/'.$book, $book];
    }

    private function file(array $rows): UploadedFile
    {
        $headers = [
            'record_type', 'user_id', 'capital_minor', 'units_micro',
            'account_code', 'direction', 'amount_minor', 'name',
            'asset_class', 'quantity_micro', 'cost_minor', 'carrying_value_minor',
        ];

        return UploadedFile::fake()->createWithContent(
            'opening.csv',
            implode(',', $headers)."\n".implode("\n", array_map(
                static fn (array $row): string => implode(',', $row),
                $rows
            ))."\n"
        );
    }

    public function test_opening_import_does_not_post_until_existing_distinct_checker_approves(): void
    {
        [$owner, $checker, $base, $bookId] = $this->setupBook();
        $rows = [
            ['member', $owner->id, 1000, '', '', '', '', '', '', '', '', ''],
            ['balance', '', '', '', 'OTHER_ASSETS', 'debit', 1000, '', '', '', '', ''],
        ];

        $preview = $this->postJson($base.'/migration/preview', [
            'file' => $this->file($rows),
        ])->assertOk()
            ->assertJsonPath('data.member_count', 1)
            ->assertJsonPath('data.member_capital_minor', 1000)
            ->assertJsonPath('data.balance_debits_minor', 1000)
            ->assertJsonPath('data.posting_enabled', false);
        $this->assertSame(64, strlen($preview->json('data.source_hash')));
        $this->assertSame('draft', ClubBook::findOrFail($bookId)->status);

        $submitted = $this->postJson($base.'/migration/submit', [
            'file' => $this->file($rows),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_independent_checker')
            ->assertJsonPath('data.posted', false);
        $instruction = $submitted->json('data.instruction');
        $this->assertSame('pending', $instruction['status']);
        $this->assertSame('draft', ClubBook::findOrFail($bookId)->status);
        $this->postJson($base.'/instructions/'.$instruction['id'].'/approve', [
            'payload_hash' => $instruction['payload_hash'],
        ])->assertForbidden();

        Sanctum::actingAs($checker);
        $this->postJson($base.'/instructions/'.$instruction['id'].'/approve', [
            'payload_hash' => $instruction['payload_hash'],
        ])->assertOk()
            ->assertJsonPath('data.instruction.status', 'approved');
        $this->assertSame('active', ClubBook::findOrFail($bookId)->status);
        $this->assertDatabaseHas('club_members', [
            'book_id' => $bookId,
            'user_id' => $owner->id,
            'capital_minor' => 1000,
        ]);
    }

    public function test_duplicate_or_foreign_members_are_rejected_before_import_submission(): void
    {
        [$owner, $checker, $base, $bookId] = $this->setupBook();
        $foreign = User::factory()->create();

        $this->postJson($base.'/migration/preview', [
            'file' => $this->file([
                ['member', $foreign->id, 1000, '', '', '', '', '', '', '', '', ''],
            ]),
        ])->assertUnprocessable();

        $this->postJson($base.'/migration/preview', [
            'file' => $this->file([
                ['member', $owner->id, 1000, '', '', '', '', '', '', '', '', ''],
                ['member', $owner->id, 2000, '', '', '', '', '', '', '', '', ''],
            ]),
        ])->assertUnprocessable();
        $this->assertSame('draft', ClubBook::findOrFail($bookId)->status);
    }
}
