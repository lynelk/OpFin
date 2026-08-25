<?php

namespace Tests\Feature;

use App\Models\SupportCase;
use App\Models\SupportCaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_list_only_their_support_cases(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        SupportCase::create([
            'customer_id' => $other->id,
            'created_by' => $other->id,
            'case_number' => 'CASE-OTHER',
            'category' => 'payment',
            'status' => SupportCase::STATUS_OPEN,
            'priority' => 'normal',
            'subject' => 'Other customer case',
            'description' => 'Must not be visible.',
        ]);

        $created = $this->postJson('/api/support-cases', [
            'category' => 'repayment',
            'subject' => 'Repayment not reflected',
            'description' => 'My repayment is not yet visible in the loan account.',
            'related_type' => 'loan',
            'related_reference' => 'LN-123',
        ])->assertCreated();

        $caseId = $created->json('data.support_case.id');
        SupportCaseNote::create([
            'support_case_id' => $caseId,
            'created_by' => $customer->id,
            'note' => 'Internal investigation detail.',
            'is_internal' => true,
        ]);
        SupportCaseNote::create([
            'support_case_id' => $caseId,
            'created_by' => $customer->id,
            'note' => 'Customer-visible update.',
            'is_internal' => false,
        ]);

        $response = $this->getJson('/api/support-cases')
            ->assertOk()
            ->assertJsonCount(1, 'data.support_cases')
            ->assertJsonPath('data.support_cases.0.customer_id', $customer->id)
            ->assertJsonPath('data.support_cases.0.notes.0.note', 'Customer-visible update.');

        $this->assertStringNotContainsString('Internal investigation detail.', $response->getContent());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'support.case.customer_created',
            'actor_id' => $customer->id,
        ]);
    }
}
