<?php

namespace Tests\Unit;

use App\Services\CommunityFinance\CommunityFinanceReadinessService;
use RuntimeException;
use Tests\TestCase;

class CommunityFinanceReadinessServiceTest extends TestCase
{
    public function test_catalogue_exposes_member_friendly_terms_and_dormant_modules(): void
    {
        $catalogue = (new CommunityFinanceReadinessService)->catalogue();

        $this->assertSame('Community Growth Circles', $catalogue['language']['regulated_community_capital_pools']['public_name']);
        $this->assertSame('Protection & Asset Plans', $catalogue['language']['insurance_and_asset_finance']['public_name']);
        $this->assertSame('Workplace Support Finance', $catalogue['language']['employer_backed_lending']['public_name']);
        $this->assertSame('Member Growth Score', $catalogue['language']['behaviour_scoring']['public_name']);
        $this->assertSame('Member Cooperative Core', $catalogue['language']['sacco_core']['public_name']);
        $this->assertSame('DORMANT_READY', $catalogue['modules']['member_cooperative_core']['status']);
    }

    public function test_readiness_summary_keeps_live_actions_blocked_by_default(): void
    {
        $summary = (new CommunityFinanceReadinessService)->readinessSummary();

        $this->assertSame('DORMANT_READY', $summary['overall_status']);
        $this->assertTrue($summary['live_actions_blocked']);
        $this->assertContains('licence_or_regulated_partner_confirmation', $summary['required_signoffs']);
        $this->assertContains('member_savings_collection', $summary['modules']['member_cooperative_core']['blocked_before_activation']);
    }

    public function test_activation_requires_explicit_live_flags(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Community finance remains dormant until OPFIN_COMMUNITY_FINANCE_MODE=live is deliberately approved.');

        (new CommunityFinanceReadinessService)->assertCanActivate();
    }

    public function test_even_live_mode_requires_live_and_sacco_flags(): void
    {
        config()->set('community_finance.activation.mode', 'live');
        config()->set('community_finance.activation.live_enabled', false);
        config()->set('community_finance.activation.sacco_core_enabled', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Community finance live activation requires OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED=true.');

        (new CommunityFinanceReadinessService)->assertCanActivate();
    }

    public function test_full_internal_flag_set_allows_readiness_service_activation_check(): void
    {
        config()->set('community_finance.activation.mode', 'live');
        config()->set('community_finance.activation.live_enabled', true);
        config()->set('community_finance.activation.sacco_core_enabled', true);

        (new CommunityFinanceReadinessService)->assertCanActivate();

        $this->assertTrue((new CommunityFinanceReadinessService)->isLiveEnabled());
    }
}
