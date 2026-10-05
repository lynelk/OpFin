<?php

namespace Tests\Feature;

use App\Jobs\AnalyseStatementDocument;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use App\Services\FinancialIntelligence\StatementDocumentAnalyser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatementPdfPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const STATEMENT = [
        'Synthetic Bank Uganda Limited - account statement (test fixture)',
        'Account SYN-0001 Period 01/09/2026 to 25/09/2026',
        'Date Description Amount Balance',
        'Opening balance 100,000',
        '02/09/2026 Salary from Synthetic Employer 250,000 350,000',
        '05/09/2026 Rent payment to landlord 120,000 230,000',
        '10/09/2026 Airtime purchase 5,000 225,000',
        '20/09/2026 Transfer received 40,000 265,000',
        'Closing balance 265,000',
    ];

    private User $owner;

    private User $checker;

    private FinancialSpace $space;

    private string $base;

    private int $issuer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 08:00:00');
        config(['financial_intelligence.enabled' => true]);
        Storage::fake('local');
        Queue::fake();
        $this->owner = User::factory()->create();
        $this->checker = User::factory()->create();
        $this->space = $this->space([$this->owner, $this->checker]);
        $this->base = '/api/financial-spaces/'.$this->space->id.'/intelligence';
        $this->issuer = $this->issuer();
        $this->actingAs($this->owner, 'sanctum');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pdf_is_queued_then_read_checked_and_labelled_without_authenticity(): void
    {
        $id = $this->upload($this->pdf([self::STATEMENT]))->assertCreated()
            ->assertJsonPath('data.status', 'queued_for_analysis')
            ->assertJsonPath('data.assurance.extraction', 'pending')
            ->json('data.id');
        Queue::assertPushed(AnalyseStatementDocument::class, fn (AnalyseStatementDocument $job): bool => $job->statementId === $id);

        $this->assertSame('analysed_unconfirmed', $this->analyse($id));
        $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.statement.status', 'analysed_unconfirmed')
            ->assertJsonPath('data.assurance.extraction', 'complete')
            ->assertJsonPath('data.assurance.financial_consistency', 'consistent')
            ->assertJsonPath('data.assurance.source_authenticity', 'unconfirmed')
            ->assertJsonPath('data.assurance.account_authority', 'declared_by_uploader')
            ->assertJsonPath('data.assurance.review', 'no_issues_detected_by_executed_checks')
            ->assertJsonPath('data.analysis.extraction.adapter', 'generic_running_balance')
            ->assertJsonPath('data.analysis.extraction.layout_validated', false)
            ->assertJsonPath('data.analysis.opening_balance_minor', 100000)
            ->assertJsonPath('data.analysis.closing_balance_minor', 265000)
            ->assertJsonPath('data.analysis.credits_minor', 290000)
            ->assertJsonPath('data.analysis.debits_minor', 125000)
            ->assertJsonPath('data.analysis.transactions.1.direction', 'debit')
            ->assertJsonPath('data.analysis.transactions.1.balance_minor', 230000)
            ->assertJsonPath('data.credit_decision_eligible', false);
    }

    public function test_changed_amount_with_unchanged_running_balances_is_flagged_on_its_line(): void
    {
        $lines = self::STATEMENT;
        $lines[5] = '05/09/2026 Rent payment to landlord 20,000 230,000';
        $id = $this->upload($this->pdf([$lines]))->assertCreated()->json('data.id');
        $this->analyse($id);

        $response = $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.financial_consistency', 'discrepancies')
            ->assertJsonPath('data.assurance.review', 'needs_review')
            ->assertJsonPath('data.assurance.source_authenticity', 'unconfirmed');
        $findings = collect($response->json('data.analysis.findings'));
        $this->assertSame([6], $findings->where('code', 'amount_balance_discrepancy')->pluck('source_line')->all());
        $this->assertTrue($findings->contains('code', 'closing_balance_mismatch'));
        $this->assertSame(0, $findings->where('code', 'running_balance_mismatch')->count(), 'One altered row must not cascade.');
    }

    public function test_fully_rebalanced_alteration_passes_arithmetic_but_stays_source_unconfirmed(): void
    {
        $lines = self::STATEMENT;
        $lines[5] = '05/09/2026 Rent payment to landlord 20,000 330,000';
        $lines[6] = '10/09/2026 Airtime purchase 5,000 325,000';
        $lines[7] = '20/09/2026 Transfer received 40,000 365,000';
        $lines[8] = 'Closing balance 365,000';
        $id = $this->upload($this->pdf([$lines]))->assertCreated()->json('data.id');
        $this->analyse($id);

        $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.financial_consistency', 'consistent')
            ->assertJsonPath('data.assurance.source_authenticity', 'unconfirmed')
            ->assertJsonPath('data.source_authenticity', 'unconfirmed')
            ->assertJsonPath('data.credit_decision_eligible', false);
    }

    public function test_newest_first_statements_are_read_in_date_order(): void
    {
        $lines = [...array_slice(self::STATEMENT, 0, 4), ...array_reverse(array_slice(self::STATEMENT, 4, 4)), self::STATEMENT[8]];
        $id = $this->upload($this->pdf([$lines]))->assertCreated()->json('data.id');
        $this->analyse($id);

        $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.financial_consistency', 'consistent')
            ->assertJsonPath('data.analysis.extraction.source_order', 'reverse_chronological')
            ->assertJsonPath('data.analysis.transactions.0.date', '2026-09-02');
    }

    public function test_active_content_is_rejected_before_parsing_even_with_obfuscated_names(): void
    {
        foreach ([' /OpenAction << /S /JavaScript /JS (app.alert(1)) >>', ' /OpenAction << /S /Java#53cript /J#53 (app.alert(1)) >>'] as $index => $action) {
            $id = $this->upload($this->pdf([self::STATEMENT], ['catalog' => $action]), 'active-'.$index)->assertCreated()->json('data.id');
            $this->assertSame('rejected_active_content', $this->analyse($id));
            $this->getJson($this->base.'/statements/'.$id)->assertOk()
                ->assertJsonPath('data.analysis', null)
                ->assertJsonPath('data.assurance.extraction', 'not_processed')
                ->assertJsonPath('data.assurance.review', 'not_applicable')
                ->assertJsonPath('data.status_explanation', 'This PDF contains scripts or embedded files, so it was not opened. Upload the statement exactly as your provider issues it.');
        }
    }

    public function test_password_protected_pdf_asks_for_an_export_and_never_for_a_password(): void
    {
        $id = $this->upload($this->pdf([self::STATEMENT], ['trailer' => ' /Encrypt 99 0 R']))->assertCreated()->json('data.id');
        $this->assertSame('export_required_password_protected', $this->analyse($id));
        $explanation = $this->getJson($this->base.'/statements/'.$id)->assertOk()->json('data.status_explanation');
        $this->assertStringContainsString('never asks for statement passwords, PINs or OTPs', $explanation);
    }

    public function test_unrecognised_layout_is_not_yet_supported_and_never_called_fraud(): void
    {
        $id = $this->upload($this->pdf([['Thank you for banking with us.', 'Your statement summary is attached.']]))->assertCreated()->json('data.id');
        $this->assertSame('layout_not_supported', $this->analyse($id));
        $response = $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.extraction', 'not_yet_supported')
            ->assertJsonPath('data.assurance.review', 'not_applicable');
        $this->assertStringNotContainsStringIgnoringCase('fraud', $response->getContent());
    }

    public function test_editing_metadata_and_incremental_updates_are_review_signals_not_findings(): void
    {
        $pdf = $this->pdf([self::STATEMENT], ['producer' => 'iLovePDF', 'modified' => 'D:20260927090000Z', 'append' => "\n%%EOF\n"]);
        $id = $this->upload($pdf)->assertCreated()->json('data.id');
        $this->analyse($id);

        $assurance = $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.assurance.financial_consistency', 'consistent')
            ->assertJsonPath('data.assurance.review', 'needs_review')
            ->json('data.assurance');
        $this->assertEqualsCanonicalizing(['incremental_updates_present', 'editing_software_metadata', 'modified_after_creation'], $assurance['document_signals']);
    }

    public function test_listing_reports_assurance_without_decrypting_evidence(): void
    {
        $id = $this->upload($this->pdf([self::STATEMENT]))->assertCreated()->json('data.id');
        $this->analyse($id);
        DB::table('fi_statements')->where('id', $id)->update(['analysis_cipher' => 'not-decryptable']);

        $this->getJson($this->base.'/statements')->assertOk()
            ->assertJsonPath('data.items.0.assurance.financial_consistency', 'consistent');
    }

    public function test_pdf_size_limit_is_enforced_at_upload(): void
    {
        config(['financial_intelligence.statement_pdf_max_bytes' => 100]);
        $this->upload($this->pdf([self::STATEMENT]))->assertUnprocessable();
    }

    public function test_analysis_runs_once_per_queued_statement(): void
    {
        $id = $this->upload($this->pdf([self::STATEMENT]))->assertCreated()->json('data.id');
        $this->assertSame('analysed_unconfirmed', $this->analyse($id));
        $this->assertNull($this->analyse($id));
        $this->assertSame(1, (int) DB::table('fi_statements')->where('id', $id)->value('analysis_attempts'));
    }

    public function test_interrupted_analysis_is_requeued_then_closed_after_bounded_attempts(): void
    {
        $id = $this->upload($this->pdf([self::STATEMENT]))->assertCreated()->json('data.id');
        DB::table('fi_statements')->where('id', $id)->update(['status' => 'analysing', 'analysis_attempts' => 1, 'updated_at' => now()->subMinutes(20)]);
        Queue::fake();
        Artisan::call('opfin:statements:analyse-pending');
        $this->assertSame('queued_for_analysis', DB::table('fi_statements')->where('id', $id)->value('status'));
        Queue::assertPushed(AnalyseStatementDocument::class, fn (AnalyseStatementDocument $job): bool => $job->statementId === $id);

        DB::table('fi_statements')->where('id', $id)->update(['status' => 'analysing', 'analysis_attempts' => 2, 'updated_at' => now()->subMinutes(20)]);
        Artisan::call('opfin:statements:analyse-pending');
        $this->getJson($this->base.'/statements/'.$id)->assertOk()
            ->assertJsonPath('data.statement.status', 'unreadable')
            ->assertJsonPath('data.assurance.extraction', 'unreadable');
    }

    public function test_retention_purges_originals_and_analysis_but_respects_legal_hold(): void
    {
        $expired = $this->upload($this->pdf([self::STATEMENT]), 'purge-1')->assertCreated()->json('data.id');
        $held = $this->upload($this->pdf([self::STATEMENT]), 'purge-2')->assertCreated()->json('data.id');
        $recent = $this->upload($this->pdf([self::STATEMENT]), 'purge-3', ['authority_expires_at' => '2026-12-31'])->assertCreated()->json('data.id');
        foreach ([$expired, $held, $recent] as $id) {
            $this->analyse($id);
        }
        DB::table('fi_statements')->where('id', $held)->update(['legal_hold_until' => '2027-06-30']);
        $paths = DB::table('fi_statements')->pluck('storage_path', 'id');

        Carbon::setTestNow('2027-01-15 03:00:00');
        Artisan::call('opfin:statements:purge-originals');

        Storage::disk('local')->assertMissing($paths[$expired]);
        Storage::disk('local')->assertExists($paths[$held]);
        Storage::disk('local')->assertExists($paths[$recent]);
        $purged = DB::table('fi_statements')->find($expired);
        $this->assertNotNull($purged->original_purged_at);
        $this->assertNull($purged->analysis_cipher);
        $this->assertNull($purged->pipeline_cipher);
        $this->assertNotNull($purged->file_hash);
        $this->assertNull(DB::table('fi_statements')->where('id', $held)->value('original_purged_at'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'intelligence.statement_original_purged']);
    }

    private function analyse(int $id): ?string
    {
        return app(StatementDocumentAnalyser::class)->analyse($id);
    }

    private function upload(string $pdf, string $key = 'statement-pdf', array $overrides = [])
    {
        return $this->post($this->base.'/statements', [
            'statement_file' => UploadedFile::fake()->createWithContent('statement.pdf', $pdf),
            'issuer_version_id' => $this->issuer, 'currency' => 'UGX', 'period_start' => '2026-09-01', 'period_end' => '2026-09-25',
            'account_reference' => 'SYN-ACCOUNT-1', 'authority_reference' => 'synthetic-authority', 'authority_confirmed' => '1',
            'authority_expires_at' => '2026-10-01', 'purpose' => 'financial_analysis', ...$overrides,
        ], ['Accept' => 'application/json', 'Idempotency-Key' => $key]);
    }

    /** A minimal synthetic PDF: one Helvetica text line per BT/ET block, with optional extra catalog/trailer entries. */
    private function pdf(array $pages, array $options = []): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R'.($options['catalog'] ?? '').' >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        $next = 4;
        foreach ($pages as $lines) {
            [$page, $content] = [$next++, $next++];
            $stream = '';
            foreach (array_values($lines) as $index => $line) {
                $stream .= sprintf("BT /F1 9 Tf 40 %d Td (%s) Tj ET\n", 800 - 12 * $index, strtr($line, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']));
            }
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$content} 0 R >>";
            $objects[$content] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
            $kids[] = "{$page} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $info = $next;
        $objects[$info] = '<< /Producer ('.($options['producer'] ?? 'Synthetic Issuer Statement Service').') /CreationDate (D:20260926080000Z) /ModDate ('.($options['modified'] ?? 'D:20260926080000Z').') >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = $info + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R /Info {$info} 0 R".($options['trailer'] ?? '')." >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf.($options['append'] ?? '');
    }

    private function space(array $users): FinancialSpace
    {
        $space = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => 'sacco', 'name' => 'Synthetic statement test space',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        foreach ($users as $user) {
            FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        }
        $plan = DB::table('opfin_plans')->insertGetId(['code' => 'fi-pdf-'.Str::random(12), 'name' => 'Synthetic statement test plan', 'price_minor' => 0,
            'currency' => 'UGX', 'billing_period' => 'monthly', 'status' => 'active', 'metadata' => json_encode(['entitlements' => ['financial_intelligence']]),
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('financial_space_entitlements')->insert(['financial_space_id' => $space->id, 'opfin_plan_id' => $plan, 'entitlement_key' => 'financial_intelligence',
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        return $space;
    }

    private function issuer(): int
    {
        return DB::table('fi_issuer_versions')->insertGetId(['public_id' => (string) Str::uuid(), 'issuer_code' => 'SYNTHETIC_ONLY',
            'legal_name' => 'Synthetic test issuer, not a real licence', 'country' => 'UG', 'product_type' => 'bank', 'regulator' => 'Synthetic authority',
            'licence_reference' => 'TEST', 'evidence_reference' => 'fixture-only', 'valid_from' => '2020-01-01', 'valid_until' => '2026-12-31',
            'review_due_on' => '2026-12-31', 'proposed_by' => $this->owner->id, 'approved_by' => $this->checker->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
    }
}
