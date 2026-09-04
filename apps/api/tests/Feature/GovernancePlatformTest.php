<?php

namespace Tests\Feature;

use App\Models\SmsMessage;
use App\Models\User;
use App\Services\FinancialIntegrityService;
use App\Services\RegulatoryReportingService;
use App\Services\WhatsAppJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GovernancePlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_balanced_ledger_passes_continuous_integrity_audit(): void
    {
        $accountA = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST-A', 'name' => 'Test A', 'type' => 'asset', 'currency' => 'UGX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $accountB = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST-B', 'name' => 'Test B', 'type' => 'liability', 'currency' => 'UGX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tx = DB::table('ledger_transactions')->insertGetId(['reference' => 'TEST-BALANCED-1', 'event_type' => 'test', 'currency' => 'UGX', 'source_type' => 'test', 'source_id' => 1, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ledger_entries')->insert([
            ['ledger_transaction_id' => $tx, 'ledger_account_id' => $accountA, 'direction' => 'debit', 'amount_minor' => 1000, 'currency' => 'UGX', 'created_at' => now(), 'updated_at' => now()],
            ['ledger_transaction_id' => $tx, 'ledger_account_id' => $accountB, 'direction' => 'credit', 'amount_minor' => 1000, 'currency' => 'UGX', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $run = app(FinancialIntegrityService::class)->run('test');

        $this->assertSame('balanced', $run->status);
        $this->assertSame(0, $run->unbalanced_transactions);
        $this->assertSame(0, $run->net_ledger_imbalance_minor);
    }

    public function test_unbalanced_ledger_creates_critical_integrity_alert(): void
    {
        $account = DB::table('ledger_accounts')->insertGetId(['code' => 'TEST-C', 'name' => 'Test C', 'type' => 'asset', 'currency' => 'UGX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tx = DB::table('ledger_transactions')->insertGetId(['reference' => 'TEST-BROKEN-1', 'event_type' => 'test', 'currency' => 'UGX', 'source_type' => 'test', 'source_id' => 2, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ledger_entries')->insert(['ledger_transaction_id' => $tx, 'ledger_account_id' => $account, 'direction' => 'debit', 'amount_minor' => 1000, 'currency' => 'UGX', 'created_at' => now(), 'updated_at' => now()]);

        $run = app(FinancialIntegrityService::class)->run('test');

        $this->assertSame('critical', $run->status);
        $this->assertDatabaseHas('financial_integrity_alerts', ['type' => 'ledger_unbalanced', 'severity' => 'critical', 'reference' => 'TEST-BROKEN-1']);
    }

    public function test_regulatory_reports_are_hashed_and_validated(): void
    {
        $report = app(RegulatoryReportingService::class)->generate('umra_digital_credit_supervision', now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame('UMRA', $report->regulator);
        $this->assertSame('validated', $report->status);
        $this->assertSame(64, strlen($report->payload_hash));
        $this->assertStringContainsString('external_submission_requires_human_authorization', $report->validation_results);
    }

    public function test_whatsapp_uses_channel_specific_challenge_then_allows_support_but_blocks_money_action(): void
    {
        Queue::fake();
        User::factory()->create(['phone' => '256700111222', 'phone_verified_at' => now()]);
        $service = app(WhatsAppJourneyService::class);

        $start = $service->handle('256700111222', 'START', 'wamid.start');
        $this->assertSame('challenge_sent', $start['state']);

        $sms = SmsMessage::latest('id')->firstOrFail();
        preg_match('/(\d{6})/', $sms->message, $matches);
        $this->assertArrayHasKey(1, $matches);

        $service->handle('256700111222', 'VERIFY '.$matches[1], 'wamid.verify');
        $support = $service->handle('256700111222', 'SUPPORT I need help understanding my repayment date', 'wamid.support');
        $money = $service->handle('256700111222', 'PAY 50000', 'wamid.pay');

        $this->assertStringContainsString('Support case', $support['reply']);
        $this->assertSame('step_up_required', $money['state']);
        $this->assertDatabaseCount('support_cases', 1);
        $this->assertDatabaseHas('whatsapp_messages', ['provider_message_id' => 'wamid.support', 'direction' => 'inbound']);
    }
}
