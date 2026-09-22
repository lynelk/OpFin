<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('outcome_domain')->index();
            $table->string('default_measurement_stage')->default('check_in')->index();
            $table->string('consent_classification')->default('programme_measurement')->index();
            $table->json('channels');
            $table->string('default_locale', 16)->default('en');
            $table->json('supported_locales')->nullable();
            $table->json('schedule_config')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('version')->default('1.0');
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['programme_id', 'code'], 'programme_instrument_code_unique');
        });

        Schema::create('programme_instrument_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('programme_instruments')->cascadeOnDelete();
            $table->foreignId('indicator_definition_id')->nullable()->constrained('impact_indicator_definitions')->nullOnDelete();
            $table->string('code');
            $table->string('answer_type')->index();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->string('verification_source')->default('self_reported');
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamps();

            $table->unique(['instrument_id', 'code'], 'programme_question_code_unique');
        });

        Schema::create('programme_question_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('programme_instrument_questions')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'locale'], 'programme_question_locale_unique');
        });

        Schema::create('programme_follow_up_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrolment_id')->constrained('inclusive_finance_enrolments')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained('programme_instruments')->cascadeOnDelete();
            $table->string('measurement_stage')->index();
            $table->timestamp('due_at')->index();
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['enrolment_id', 'instrument_id', 'measurement_stage', 'due_at'],
                'programme_followup_unique'
            );
        });

        Schema::create('programme_instrument_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('programme_instruments')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('programme_follow_up_schedules')->nullOnDelete();
            $table->foreignId('enrolment_id')->constrained('inclusive_finance_enrolments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('measurement_stage')->index();
            $table->string('channel')->index();
            $table->string('locale', 16)->default('en');
            $table->string('status')->default('completed')->index();
            $table->json('provenance')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('programme_instrument_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('programme_instrument_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('programme_instrument_questions')->restrictOnDelete();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->json('json_value')->nullable();
            $table->string('unit')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->timestamps();

            $table->unique(['response_id', 'question_id'], 'programme_response_question_unique');
        });

        Schema::create('programme_partner_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('invited_name');
            $table->string('invited_phone')->nullable();
            $table->string('invited_email')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->text('delivery_token_encrypted')->nullable();
            $table->string('access_level')->default('read_only')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_acquisition_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('acquisition_channel')->index();
            $table->string('source')->nullable()->index();
            $table->string('campaign')->nullable()->index();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->timestamp('acquired_at')->index();
            $table->json('metadata')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commercial_cost_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('cost_type')->index();
            $table->string('channel')->nullable()->index();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('source_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cost_type', 'source_reference'], 'commercial_cost_source_unique');
        });

        Schema::create('programme_commercial_graduations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('enrolment_id')->unique()->constrained('inclusive_finance_enrolments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('not_ready')->index();
            $table->json('criteria');
            $table->json('reason_codes')->nullable();
            $table->timestamp('evaluated_at')->index();
            $table->timestamp('graduated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['programme_id', 'status'], 'programme_graduation_status');
        });

        Schema::create('programme_provider_adapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('adapter_type')->index();
            $table->string('status')->default('draft')->index();
            $table->string('purpose')->default('programme_measurement')->index();
            $table->json('allowed_signal_keys')->nullable();
            $table->json('signal_mapping')->nullable();
            $table->boolean('requires_credit_processing_consent')->default(false);
            $table->boolean('credentials_configured')->default(false);
            $table->boolean('legal_basis_confirmed')->default(false);
            $table->text('activation_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('programme_provider_ingestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adapter_id')->constrained('programme_provider_adapters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_reference');
            $table->string('payload_hash', 64);
            $table->json('mapped_signals')->nullable();
            $table->string('status')->default('processed')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['adapter_id', 'provider_reference'], 'programme_provider_reference_unique');
        });

        Schema::table('financial_health_snapshots', function (Blueprint $table) {
            $table->string('source_type')->default('customer_check_in')->index();
            $table->json('automated_inputs')->nullable();
            $table->string('enrichment_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('financial_health_snapshots', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'automated_inputs', 'enrichment_version']);
        });

        Schema::dropIfExists('programme_provider_ingestions');
        Schema::dropIfExists('programme_provider_adapters');
        Schema::dropIfExists('programme_commercial_graduations');
        Schema::dropIfExists('commercial_cost_events');
        Schema::dropIfExists('customer_acquisition_attributions');
        Schema::dropIfExists('programme_partner_invitations');
        Schema::dropIfExists('programme_instrument_answers');
        Schema::dropIfExists('programme_instrument_responses');
        Schema::dropIfExists('programme_follow_up_schedules');
        Schema::dropIfExists('programme_question_translations');
        Schema::dropIfExists('programme_instrument_questions');
        Schema::dropIfExists('programme_instruments');
    }
};
