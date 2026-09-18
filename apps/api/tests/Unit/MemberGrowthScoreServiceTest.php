<?php

namespace Tests\Unit;

use App\Services\CommunityFinance\MemberGrowthScoreService;
use Tests\TestCase;

class MemberGrowthScoreServiceTest extends TestCase
{
    public function test_calculates_composite_score_with_component_and_factor_breakdown(): void
    {
        $result = (new MemberGrowthScoreService)->calculate([
            'financial' => [
                'savings_consistency' => 90,
                'repayment_discipline' => 84,
                'affordability_buffer' => 78,
                'income_or_cashflow_stability' => 74,
                'existing_obligation_pressure' => 80,
            ],
            'platform' => [
                'verified_identity_depth' => 100,
                'consent_reliability' => 95,
                'account_security' => 88,
                'support_and_dispute_conduct' => 90,
                'data_completeness' => 86,
            ],
            'community' => [
                'group_savings_participation' => 82,
                'guarantor_reliability' => 75,
                'membership_duration' => 68,
                'workplace_or_group_standing' => 80,
                'community_obligation_performance' => 78,
            ],
            'protection_asset' => [
                'cover_continuity' => 70,
                'asset_deposit_discipline' => 75,
                'asset_care_or_usage' => 65,
                'supplier_or_partner_confirmation' => 72,
            ],
            'conduct' => [
                'fraud_risk_absence' => 100,
                'complaints_resolution' => 85,
                'terms_adherence' => 90,
                'financial_education_progress' => 76,
            ],
        ]);

        $this->assertSame('Member Growth Score', $result['score_name']);
        $this->assertSame('OpFin Growth Passport', $result['composite_name']);
        $this->assertIsInt($result['composite_score']);
        $this->assertGreaterThanOrEqual(0, $result['composite_score']);
        $this->assertLessThanOrEqual(100, $result['composite_score']);
        $this->assertSame(100, $result['confidence_percent']);
        $this->assertArrayHasKey('financial', $result['component_scores']);
        $this->assertArrayHasKey('platform', $result['component_scores']);
        $this->assertArrayHasKey('community', $result['component_scores']);
        $this->assertArrayHasKey('protection_asset', $result['component_scores']);
        $this->assertArrayHasKey('conduct', $result['component_scores']);
        $this->assertArrayHasKey('repayment_discipline', $result['factor_breakdown']['financial']);
    }

    public function test_missing_factors_reduce_confidence_without_becoming_silent_failures(): void
    {
        $result = (new MemberGrowthScoreService)->calculate([
            'financial' => [
                'savings_consistency' => 90,
            ],
            'platform' => [
                'verified_identity_depth' => 80,
            ],
        ]);

        $this->assertIsInt($result['composite_score']);
        $this->assertLessThan(100, $result['confidence_percent']);
        $this->assertContains('repayment_discipline', $result['component_scores']['financial']['missing_factors']);
        $this->assertSame('missing', $result['factor_breakdown']['financial']['repayment_discipline']['status']);
        $this->assertNull($result['factor_breakdown']['financial']['repayment_discipline']['score']);
        $this->assertSame(
            'Missing factors are not silently converted into failures; they reduce confidence and remain visible in the breakdown.',
            $result['explanation']['missing_data_rule']
        );
    }

    public function test_scores_are_clamped_to_zero_to_one_hundred(): void
    {
        $result = (new MemberGrowthScoreService)->calculate([
            'financial' => [
                'savings_consistency' => 150,
                'repayment_discipline' => -20,
            ],
        ]);

        $this->assertSame(100, $result['factor_breakdown']['financial']['savings_consistency']['score']);
        $this->assertSame(0, $result['factor_breakdown']['financial']['repayment_discipline']['score']);
    }
}
