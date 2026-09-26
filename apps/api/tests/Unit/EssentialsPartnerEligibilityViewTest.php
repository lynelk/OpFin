<?php

namespace Tests\Unit;

use App\Services\EssentialsPartnerEligibilityView;
use PHPUnit\Framework\TestCase;

class EssentialsPartnerEligibilityViewTest extends TestCase
{
    public function test_only_the_authorised_spaces_minimal_eligibility_is_returned(): void
    {
        $view = new EssentialsPartnerEligibilityView;
        $response = $view->forSpace([
            'lines' => [
                ['id' => 1, 'financial_space_id' => 91, 'currency' => 'UGX', 'available_limit_minor' => 100000,
                    'decision_snapshot' => ['private' => 'personal-evidence'], 'outstanding_minor' => 45000],
                ['id' => 2, 'financial_space_id' => 92, 'currency' => 'UGX', 'available_limit_minor' => 900000],
            ],
            'overall' => ['overall_available_limit_minor' => 70000, 'accounts' => [['private' => 'personal-account']],
                'advances' => [['private' => 'personal-debt']], 'profile' => ['private' => 'credit-evidence']],
        ], 91);
        $this->assertSame(91, $response['financial_space_id']);
        $this->assertSame([1], array_column($response['lines'], 'id'));
        $this->assertSame(70000, $response['overall']['overall_available_limit_minor']);
        $this->assertArrayNotHasKey('decision_snapshot', $response['lines'][0]);
        $this->assertArrayNotHasKey('outstanding_minor', $response['lines'][0]);
        $this->assertArrayNotHasKey('accounts', $response['overall']);
        $this->assertArrayNotHasKey('advances', $response['overall']);
        $this->assertArrayNotHasKey('profile', $response['overall']);
        $this->assertStringNotContainsString('private', json_encode($response, JSON_THROW_ON_ERROR));
    }

    public function test_lender_limits_are_not_added_and_mixed_currencies_have_no_invented_total(): void
    {
        $view = new EssentialsPartnerEligibilityView;
        $data = ['lines' => [
            ['id' => 1, 'financial_space_id' => 91, 'currency' => 'UGX', 'available_limit_minor' => 100],
            ['id' => 2, 'financial_space_id' => 91, 'currency' => 'UGX', 'available_limit_minor' => 120],
        ], 'overall' => ['overall_available_limit_minor' => 500]];
        $this->assertSame(120, $view->forSpace($data, 91)['overall']['overall_available_limit_minor']);
        $data['lines'][1]['currency'] = 'USD';
        $mixed = $view->forSpace($data, 91);
        $this->assertSame(0, $mixed['overall']['overall_available_limit_minor']);
        $this->assertNull($mixed['overall']['currency']);
    }

    public function test_an_empty_target_does_not_return_other_spaces_capacity(): void
    {
        $result = (new EssentialsPartnerEligibilityView)->forSpace(['lines' => [
            ['financial_space_id' => 92, 'currency' => 'UGX', 'available_limit_minor' => 100000],
        ], 'overall' => ['overall_available_limit_minor' => 100000]], 91);
        $this->assertSame([], $result['lines']);
        $this->assertSame(0, $result['overall']['overall_available_limit_minor']);
    }
}
