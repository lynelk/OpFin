<?php

namespace Tests\Feature;

use App\Services\AssetFinance\AssetFinanceEvidencePolicy;
use Tests\TestCase;

class AssetFinanceEvidencePolicyTest extends TestCase
{
    public function test_solar_machinery_and_agricultural_asset_cases_have_distinct_immutable_evidence_profiles(): void
    {
        $solar = AssetFinanceEvidencePolicy::for('productive', 'solar_system');
        $machine = AssetFinanceEvidencePolicy::for('productive', 'machinery');
        $agriculture = AssetFinanceEvidencePolicy::for('productive', 'agricultural_equipment');

        $this->assertContains('site_suitability', $solar['pre_approval']);
        $this->assertContains('installation_commissioning', $solar['activation']);
        $this->assertContains('equipment_condition_report', $machine['pre_approval']);
        $this->assertContains('operator_handover', $machine['activation']);
        $this->assertContains('seasonal_use_assessment', $agriculture['pre_approval']);
        $this->assertContains('field_delivery_confirmation', $agriculture['activation']);
        $this->assertContains('protection_confirmation', $solar['activation']);
        $this->assertFalse((bool) config('asset_finance.remote_device_controls_enabled'));
    }
}
