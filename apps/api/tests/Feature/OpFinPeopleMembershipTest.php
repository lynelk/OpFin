<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpFinPeopleMembershipTest extends TestCase
{
    public function test_business_space_discovery_requires_authentication(): void
    {
        $this->getJson('/api/people/business-spaces')->assertUnauthorized();
    }

    public function test_exact_business_space_lookup_requires_authentication(): void
    {
        $this->getJson('/api/people/business-spaces/not-a-space')->assertUnauthorized();
    }
}
