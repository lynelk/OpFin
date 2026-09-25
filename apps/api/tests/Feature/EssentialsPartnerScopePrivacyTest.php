<?php

namespace Tests\Feature;

use App\Models\EssentialsPartnerAuthorisation;
use App\Models\User;
use App\Services\EssentialsOrchestrationService;
use App\Services\EssentialsPartnerScopeService;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class EssentialsPartnerScopePrivacyTest extends TestCase
{
    private const DENIED = 'The customer has not authorised this operation in an active Financial Space.';

    public function test_missing_grant_is_rejected_before_any_membership_query(): void
    {
        $customer = new User;
        $customer->forceFill(['id' => 12]);
        $personal = Mockery::mock(PersonalFinancialSpaceService::class);
        $personal->shouldNotReceive('find');
        $essentials = Mockery::mock(EssentialsOrchestrationService::class);
        $essentials->shouldReceive('assertPartnerCustomerAuthorised')->once()
            ->with($customer, 5, 'status_read', 91)
            ->andThrow(new InvalidArgumentException('Internal grant detail must not be disclosed.'));
        DB::shouldReceive('table')->never();
        $service = new EssentialsPartnerScopeService($personal, $essentials);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::DENIED);
        $service->resolve($customer, 5, 'status_read', 91);
    }

    public function test_removed_membership_has_the_same_denial_as_an_absent_grant(): void
    {
        $customer = new User;
        $customer->forceFill(['id' => 12]);
        $personal = Mockery::mock(PersonalFinancialSpaceService::class);
        $personal->shouldNotReceive('find');
        $essentials = Mockery::mock(EssentialsOrchestrationService::class);
        $essentials->shouldReceive('assertPartnerCustomerAuthorised')->once()
            ->with($customer, 5, 'status_read', 91)
            ->andReturn(new EssentialsPartnerAuthorisation);
        $query = Mockery::mock();
        $query->shouldReceive('join')->once()->andReturnSelf();
        $query->shouldReceive('where')->times(4)->andReturnSelf();
        $query->shouldReceive('whereNull')->twice()->andReturnSelf();
        $query->shouldReceive('exists')->once()->andReturn(false);
        DB::shouldReceive('table')->once()->with('financial_space_memberships as membership')->andReturn($query);
        $service = new EssentialsPartnerScopeService($personal, $essentials);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::DENIED);
        $service->resolve($customer, 5, 'status_read', 91);
    }
}
