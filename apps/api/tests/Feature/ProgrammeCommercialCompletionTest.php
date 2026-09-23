<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\ProgrammeDeliveryService;
use App\Services\WhatsAppJourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgrammeCommercialCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_programme_instrument_requires_consent_and_records_non_credit_outcome(): void
    {
        [$admin, $programmeId] = $this->programme();
        Sanctum::actingAs($admin);

        $indicatorId = $this->postJson('/api/admin/inclusive-finance/indicators', [
            'code' => 'P0-RESILIENCE-DAYS',
            'name' => 'Essential expense coverage',
            'outcome_domain' => 'financial_health_resilience',
            'value_type' => 'integer',
            'unit' => 'days',
        ])->assertCreated()->json('data.id');

        $instrumentId = $this->createInstrument($programmeId, $admin, [
            'code' => 'P0-CHECKIN',
            'name' => 'Resilience follow-up',
            'channels' => ['app', 'web', 'whatsapp', 'ussd', 'assisted'],
            'supported_locales' => ['en', 'lg'],
        ]);

        $questionId = $this->postJson('/api/admin/inclusive-finance/instruments/'.$instrumentId.'/questions', [
            'code' => 'COVERAGE_DAYS',
            'prompt' => 'How many days could your available money cover essentials?',
            'indicator_definition_id' => $indicatorId,
            'answer_type' => 'integer',
            'required' => true,
            'validation_rules' => ['min' => 0, 'max' => 3650, 'unit' => 'days'],
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->json('data.id');

        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'preferred_language' => 'lg',
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated();

        $this->getJson('/api/inclusive-finance/programme-check-ins?channel=app&locale=lg')
            ->assertOk()
            ->assertJsonPath('data.instruments', []);

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
        ])->assertOk();

        $this->getJson('/api/inclusive-finance/programme-check-ins?channel=app&locale=lg')
            ->assertOk()
            ->assertJsonPath('data.instruments.0.id', $instrumentId)
            ->assertJsonPath('data.instruments.0.questions.0.requested_locale', 'lg')
            ->assertJsonPath('data.instruments.0.questions.0.translation_fallback', true);

        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/inclusive-finance/questions/'.$questionId.'/translations', [
            'locale' => 'lg',
            'prompt' => 'Ensimbi zo ziyinza okukuyamba ennaku mmeka ku byetaago eby omusingi?',
        ])->assertOk();

        Sanctum::actingAs($customer);
        $due = $this->getJson('/api/inclusive-finance/programme-check-ins?channel=web&locale=lg')
            ->assertOk()
            ->assertJsonPath('data.instruments.0.questions.0.translation_fallback', false)
            ->json('data.instruments.0');

        $this->postJson('/api/inclusive-finance/programme-check-ins/'.$instrumentId.'/responses', [
            'schedule_id' => $due['schedule']['id'],
            'channel' => 'web',
            'locale' => 'lg',
            'answers' => [
                ['question_id' => $questionId, 'value' => 45],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.credit_decision_eligible', false);

        $this->assertDatabaseHas('programme_instrument_responses', [
            'instrument_id' => $instrumentId,
            'user_id' => $customer->id,
            'channel' => 'web',
            'credit_decision_eligible' => false,
        ]);
        $this->assertDatabaseHas('programme_outcome_observations', [
            'programme_id' => $programmeId,
            'user_id' => $customer->id,
            'indicator_definition_id' => $indicatorId,
            'numeric_value' => 45,
            'credit_decision_eligible' => false,
        ]);
    }

    public function test_consent_withdrawal_stops_programme_prompts_but_preserves_operations_exception(): void
    {
        [$admin, $programmeId] = $this->programme();
        $instrumentId = $this->createInstrument($programmeId, $admin, [
            'code' => 'CONSENT-CHECK',
            'name' => 'Consent check',
            'channels' => ['app'],
        ]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/inclusive-finance/instruments/'.$instrumentId.'/questions', [
            'code' => 'YESNO',
            'prompt' => 'Can we measure this outcome?',
            'answer_type' => 'boolean',
            'required' => true,
        ])->assertCreated();

        $customer = User::factory()->create();
        Sanctum::actingAs($customer);
        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
        ])->assertOk();
        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])->assertCreated();

        $this->getJson('/api/inclusive-finance/programme-check-ins?channel=app')
            ->assertJsonPath('data.instruments.0.id', $instrumentId);

        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => false,
        ])->assertOk();

        $this->getJson('/api/inclusive-finance/programme-check-ins?channel=app')
            ->assertOk()
            ->assertJsonPath('data.instruments', []);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/inclusive-finance/operations?programme_id='.$programmeId)
            ->assertOk()
            ->assertJsonPath('data.consent_exceptions', 1);
    }

    public function test_ussd_and_whatsapp_use_the_same_programme_response_model(): void
    {
        [$admin, $programmeId] = $this->programme();
        $instrumentId = $this->createInstrument($programmeId, $admin, [
            'code' => 'CHANNEL-CHECK',
            'name' => 'Channel check',
            'channels' => ['ussd', 'whatsapp'],
        ]);
        Sanctum::actingAs($admin);
        $questionId = $this->postJson('/api/admin/inclusive-finance/instruments/'.$instrumentId.'/questions', [
            'code' => 'READY',
            'prompt' => 'Are you financially prepared for an unexpected expense?',
            'answer_type' => 'boolean',
            'required' => true,
        ])->assertCreated()->json('data.id');

        $ussdUser = $this->enrolledCustomer($programmeId, '256700440001');
        $this->post('/api/ussd', [
            'sessionId' => 'programme-ussd-1',
            'phoneNumber' => $ussdUser->phone,
            'text' => '7',
        ])->assertOk()->assertSee('Choose check-in');

        $this->post('/api/ussd', [
            'sessionId' => 'programme-ussd-1',
            'phoneNumber' => $ussdUser->phone,
            'text' => '7*1',
        ])->assertOk()->assertSee('Are you financially');

        $this->post('/api/ussd', [
            'sessionId' => 'programme-ussd-1',
            'phoneNumber' => $ussdUser->phone,
            'text' => '7*1*1',
        ])->assertOk()->assertSee('Programme check-in saved');

        $this->assertDatabaseHas('programme_instrument_responses', [
            'user_id' => $ussdUser->id,
            'instrument_id' => $instrumentId,
            'channel' => 'ussd',
        ]);

        $whatsappUser = $this->enrolledCustomer($programmeId, '256700440002');
        DB::table('whatsapp_conversations')->insert([
            'user_id' => $whatsappUser->id,
            'wa_phone' => $whatsappUser->phone,
            'state' => 'verified',
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $journey = app(WhatsAppJourneyService::class);
        $start = $journey->handle($whatsappUser->phone, 'CHECKIN', 'wa-programme-1');
        $this->assertStringContainsString('Are you financially', $start['reply']);

        $finish = $journey->handle($whatsappUser->phone, 'YES', 'wa-programme-2');
        $this->assertStringContainsString('check-in saved', strtolower($finish['reply']));

        $this->assertDatabaseHas('programme_instrument_responses', [
            'user_id' => $whatsappUser->id,
            'instrument_id' => $instrumentId,
            'channel' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('programme_instrument_answers', [
            'question_id' => $questionId,
            'boolean_value' => true,
            'credit_decision_eligible' => false,
        ]);
    }

    public function test_programme_response_rejects_question_from_another_instrument(): void
    {
        [$admin, $programmeId] = $this->programme();
        $instrumentA = $this->createInstrument($programmeId, $admin, [
            'code' => 'INSTRUMENT-A',
            'name' => 'Instrument A',
        ]);
        $instrumentB = $this->createInstrument($programmeId, $admin, [
            'code' => 'INSTRUMENT-B',
            'name' => 'Instrument B',
        ]);

        Sanctum::actingAs($admin);
        $questionA = $this->postJson('/api/admin/inclusive-finance/instruments/'.$instrumentA.'/questions', [
            'code' => 'QUESTION-A',
            'prompt' => 'Question A?',
            'answer_type' => 'boolean',
            'required' => false,
        ])->assertCreated()->json('data.id');
        $questionB = $this->postJson('/api/admin/inclusive-finance/instruments/'.$instrumentB.'/questions', [
            'code' => 'QUESTION-B',
            'prompt' => 'Question B?',
            'answer_type' => 'boolean',
            'required' => false,
        ])->assertCreated()->json('data.id');

        $customer = $this->enrolledCustomer($programmeId, '256700440007');
        Sanctum::actingAs($customer);

        $this->postJson('/api/inclusive-finance/programme-check-ins/'.$instrumentA.'/responses', [
            'channel' => 'app',
            'answers' => [
                ['question_id' => $questionB, 'value' => true],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Programme response contains a question that does not belong to this instrument.');

        $this->assertDatabaseMissing('programme_instrument_answers', [
            'question_id' => $questionA,
        ]);
    }

    public function test_credit_purpose_provider_adapter_forces_credit_consent_and_blocks_protected_signals(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/inclusive-finance/provider-adapters', [
            'code' => 'CREDIT-PROTECTED',
            'name' => 'Protected credit adapter',
            'adapter_type' => 'other',
            'status' => 'draft',
            'purpose' => 'credit_assessment',
            'allowed_signal_keys' => ['gender'],
            'credentials_configured' => true,
            'legal_basis_confirmed' => true,
        ])->assertStatus(422);

        $this->postJson('/api/admin/inclusive-finance/provider-adapters', [
            'code' => 'CREDIT-INCOME',
            'name' => 'Verified income adapter',
            'adapter_type' => 'other',
            'status' => 'draft',
            'purpose' => 'affordability',
            'allowed_signal_keys' => ['verified_monthly_income_minor'],
            'requires_credit_processing_consent' => false,
            'credentials_configured' => true,
            'legal_basis_confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.requires_credit_processing_consent', true);
    }

    public function test_templates_create_editable_draft_delivery_not_fake_partner_activation(): void
    {
        [$admin, $programmeId] = $this->programme();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/templates', [
            'template_code' => 'youth_women_resilience',
        ])->assertCreated()
            ->assertJsonPath('data.template_code', 'youth_women_resilience')
            ->assertJsonPath('data.activation_required', true)
            ->assertJsonPath('data.instruments.0.status', 'draft');

        $this->assertDatabaseHas('programme_instruments', [
            'programme_id' => $programmeId,
            'code' => 'YWR-FH',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('programme_theories_of_change', [
            'programme_id' => $programmeId,
            'status' => 'draft',
        ]);
    }

    public function test_commercial_dashboard_uses_recorded_cost_and_revenue_truth(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/commercial/customers/'.$customer->id.'/attribution', [
            'acquisition_channel' => 'app',
            'source' => 'launch',
            'acquired_at' => now()->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/admin/commercial/costs', [
            'cost_type' => 'acquisition',
            'channel' => 'app',
            'amount_minor' => 20000,
            'currency' => 'UGX',
            'source_reference' => 'CAC-TEST-001',
        ])->assertCreated();

        DB::table('revenue_events')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $customer->id,
            'event_type' => 'servicing_fee',
            'source_type' => 'test',
            'source_reference' => 'REV-TEST-001',
            'gross_amount_minor' => 70000,
            'opfin_amount_minor' => 70000,
            'partner_amount_minor' => 0,
            'tax_amount_minor' => 0,
            'currency' => 'UGX',
            'status' => 'accrued',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/admin/commercial/dashboard?channel=app')
            ->assertOk()
            ->assertJsonPath('data.acquisition.customers', 1)
            ->assertJsonPath('data.acquisition.cac_minor', 20000)
            ->assertJsonPath('data.economics.opfin_revenue_minor', 70000)
            ->assertJsonPath('data.economics.recorded_cost_minor', 20000)
            ->assertJsonPath('data.economics.contribution_after_npl_exposure_minor', 50000);
    }

    public function test_programme_dashboard_keeps_active_cohort_when_acquisition_predates_reporting_window(): void
    {
        [$admin, $programmeId] = $this->programme();
        $customer = $this->enrolledCustomer($programmeId, '256700440006');

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/commercial/customers/'.$customer->id.'/attribution', [
            'acquisition_channel' => 'programme',
            'programme_id' => $programmeId,
            'source' => 'historic-programme-acquisition',
            'acquired_at' => now()->subDays(180)->toIso8601String(),
        ])->assertOk();

        DB::table('revenue_events')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $customer->id,
            'event_type' => 'servicing_fee',
            'source_type' => 'test',
            'source_reference' => 'REV-OLD-ACQ-001',
            'gross_amount_minor' => 50000,
            'opfin_amount_minor' => 50000,
            'partner_amount_minor' => 0,
            'tax_amount_minor' => 0,
            'currency' => 'UGX',
            'status' => 'accrued',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/admin/commercial/dashboard?programme_id='.$programmeId)
            ->assertOk()
            ->assertJsonPath('data.acquisition.customers', 0)
            ->assertJsonPath('data.economics.opfin_revenue_minor', 50000);
    }

    public function test_graduation_is_analytics_only_and_does_not_promote_unready_participant(): void
    {
        [$admin, $programmeId] = $this->programme();
        $customer = $this->enrolledCustomer($programmeId, '256700440003');

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/commercial/graduations/evaluate', [
            'programme_id' => $programmeId,
        ])->assertOk()
            ->assertJsonPath('data.evaluated', 1)
            ->assertJsonPath('data.graduated', 0);

        $this->getJson('/api/admin/commercial/graduations?programme_id='.$programmeId)
            ->assertOk()
            ->assertJsonPath('data.graduated_participants', 0)
            ->assertJsonPath('data.boundary', 'Commercial graduation is an analytics outcome. It does not approve credit, change pricing or replace underwriting.');

        $this->assertDatabaseHas('programme_commercial_graduations', [
            'programme_id' => $programmeId,
            'user_id' => $customer->id,
            'status' => 'not_ready',
        ]);
    }

    public function test_partner_invitation_requires_phone_otp_and_creates_scoped_identity(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'PARTNER-P0P2',
            'name' => 'Programme Partner',
            'partner_type' => 'development_programme',
            'country' => 'UG',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $programmeId = $this->postJson('/api/admin/inclusive-finance/programmes', [
            'code' => 'P0P2-PARTNER',
            'name' => 'Partner Programme',
            'partner_id' => $partnerId,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $phone = '256700440004';
        $inviteToken = $this->postJson('/api/admin/inclusive-finance/partner-invitations', [
            'programme_id' => $programmeId,
            'partner_id' => $partnerId,
            'invited_name' => 'Programme Officer',
            'invited_phone' => $phone,
            'access_level' => 'mel_officer',
        ])->assertCreated()
            ->assertJsonPath('data.delivery_status', 'not_sent')
            ->json('data.activation_token');

        $this->postJson('/api/programme-partner/invitations/accept', [
            'token' => $inviteToken,
            'phone' => $phone,
            'verification_token' => str_repeat('x', 64),
            'name' => 'Programme Officer',
            'pin' => '482951',
            'pin_confirmation' => '482951',
            'terms_accepted' => true,
        ])->assertStatus(422);

        $verificationToken = str_repeat('c', 64);
        Otp::create([
            'phone' => $phone,
            'otp' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
        ]);

        $response = $this->postJson('/api/programme-partner/invitations/accept', [
            'token' => $inviteToken,
            'phone' => $phone,
            'verification_token' => $verificationToken,
            'name' => 'Programme Officer',
            'pin' => '482951',
            'pin_confirmation' => '482951',
            'terms_accepted' => true,
        ])->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_PROGRAMME_PARTNER)
            ->assertJsonPath('data.individual_records_exposed', false);

        $partnerUserId = $response->json('data.user_id');
        $this->assertDatabaseHas('programme_partner_access', [
            'programme_id' => $programmeId,
            'user_id' => $partnerUserId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('programme_partner_invitations', [
            'programme_id' => $programmeId,
            'status' => 'accepted',
            'delivery_token_encrypted' => null,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/inclusive-finance/programmes/'.$programmeId.'/partner-users')
            ->assertOk()
            ->assertJsonPath('data.users.0.user_id', $partnerUserId)
            ->assertJsonPath('data.users.0.individual_records_exposed', false);
    }

    public function test_provider_adapter_activation_and_ingestion_never_bypass_underwriting(): void
    {
        [$admin, $programmeId] = $this->programme();
        $customer = $this->enrolledCustomer($programmeId, '256700440005');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/inclusive-finance/provider-adapters', [
            'programme_id' => $programmeId,
            'code' => 'GNUGRID-TEST',
            'name' => 'gnuGrid programme evidence',
            'adapter_type' => 'gnugrid_crb',
            'status' => 'active',
            'purpose' => 'programme_measurement',
            'allowed_signal_keys' => ['verified_monthly_income_minor'],
        ])->assertStatus(422);

        $adapterId = $this->postJson('/api/admin/inclusive-finance/provider-adapters', [
            'programme_id' => $programmeId,
            'code' => 'GNUGRID-TEST',
            'name' => 'gnuGrid programme evidence',
            'adapter_type' => 'gnugrid_crb',
            'status' => 'active',
            'purpose' => 'programme_measurement',
            'allowed_signal_keys' => ['verified_monthly_income_minor'],
            'credentials_configured' => true,
            'legal_basis_confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.external_credentials_stored_here', false)
            ->json('data.id');

        $this->postJson('/api/admin/inclusive-finance/provider-adapters/'.$adapterId.'/ingestions', [
            'user_id' => $customer->id,
            'provider_reference' => 'GNUGRID-EVIDENCE-001',
            'signals' => ['forbidden_key' => 123],
        ])->assertStatus(422);

        $this->postJson('/api/admin/inclusive-finance/provider-adapters/'.$adapterId.'/ingestions', [
            'user_id' => $customer->id,
            'provider_reference' => 'GNUGRID-EVIDENCE-002',
            'signals' => ['verified_monthly_income_minor' => 1500000],
        ])->assertCreated()
            ->assertJsonPath('data.risk_eligible', false)
            ->assertJsonPath('data.verified', true);

        $this->assertDatabaseHas('alternative_data_signals', [
            'user_id' => $customer->id,
            'signal_key' => 'verified_monthly_income_minor',
            'purpose' => 'programme_measurement',
            'verified' => true,
            'risk_eligible' => false,
        ]);
    }

    public function test_financial_health_enrichment_uses_recorded_truth_and_stays_non_credit(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer);

        $this->getJson('/api/inclusive-finance/impact/financial-health/enrichment')
            ->assertOk()
            ->assertJsonPath('data.inputs.income_stability', 'none')
            ->assertJsonPath('data.credit_decision_eligible', false);

        $this->postJson('/api/inclusive-finance/impact/financial-health/enrichment', [])
            ->assertCreated()
            ->assertJsonPath('data.source_type', 'system_enriched')
            ->assertJsonPath('data.credit_decision_eligible', false);

        $this->assertDatabaseHas('financial_health_snapshots', [
            'user_id' => $customer->id,
            'source_type' => 'system_enriched',
            'enrichment_version' => 'financial-health-enrichment-v1',
            'credit_decision_eligible' => false,
        ]);
    }

    public function test_programme_csv_export_is_aggregate_and_privacy_safe(): void
    {
        [$admin, $programmeId] = $this->programme();
        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/inclusive-finance/programmes/'.$programmeId.'/exports/csv');
        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('indicator_code', $content);
        $this->assertStringNotContainsString('user_id', $content);
    }

    private function programme(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $programmeId = $this->postJson('/api/admin/inclusive-finance/programmes', [
            'code' => 'P0P2-'.$suffix,
            'name' => 'P0 P2 '.$suffix,
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        return [$admin, $programmeId];
    }

    private function createInstrument(int $programmeId, User $admin, array $overrides = []): int
    {
        Sanctum::actingAs($admin);

        return $this->postJson('/api/admin/inclusive-finance/instruments', array_merge([
            'programme_id' => $programmeId,
            'code' => 'INSTR-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'name' => 'Programme check-in',
            'outcome_domain' => 'financial_health_resilience',
            'default_measurement_stage' => 'baseline',
            'consent_classification' => 'programme_measurement',
            'channels' => ['app', 'web'],
            'default_locale' => 'en',
            'supported_locales' => ['en'],
            'schedule_config' => [
                ['stage' => 'baseline', 'offset_days' => 0],
            ],
            'status' => 'active',
        ], $overrides))->assertCreated()->json('data.id');
    }

    private function enrolledCustomer(int $programmeId, string $phone): User
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $this->patchJson('/api/inclusive-finance/profile', [
            'programme_measurement_consent' => true,
        ])->assertOk();
        $this->postJson('/api/inclusive-finance/programmes/'.$programmeId.'/enrol', [])
            ->assertCreated();

        return $customer;
    }
}
