<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inclusive_finance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('programme_measurement_consent')->default(false)->index();
            $table->json('measurement_attributes')->nullable();
            $table->json('service_preferences')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_capability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('intervention_code')->nullable()->index();
            $table->json('context')->nullable();
            $table->json('outcome')->nullable();
            $table->string('guidance_version')->default('inclusive-finance-v1');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('alternative_data_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type')->index();
            $table->string('signal_key')->index();
            $table->json('signal_value')->nullable();
            $table->string('purpose')->index();
            $table->foreignId('consent_record_id')->nullable()->constrained('consent_records')->nullOnDelete();
            $table->boolean('risk_eligible')->default(false)->index();
            $table->boolean('verified')->default(false)->index();
            $table->string('provider_reference')->nullable()->index();
            $table->json('provenance')->nullable();
            $table->timestamp('observed_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'source_type', 'signal_key'], 'alt_signal_user_source_key');
        });

        Schema::create('inclusive_finance_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('sponsor_space_id')->nullable()->constrained('financial_spaces')->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->json('target_population')->nullable();
            $table->json('eligibility_rules')->nullable();
            $table->json('product_config')->nullable();
            $table->json('reporting_config')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('inclusive_finance_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('enrolled')->index();
            $table->json('eligibility_evidence')->nullable();
            // Sequence boundaries make impact attribution deterministic even when
            // enrolment/exit and customer events share the same DB timestamp second.
            $table->unsignedBigInteger('loan_application_floor_id')->default(0);
            $table->unsignedBigInteger('loan_application_ceiling_id')->nullable();
            $table->unsignedBigInteger('capability_event_floor_id')->default(0);
            $table->unsignedBigInteger('capability_event_ceiling_id')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->unique(['programme_id', 'user_id'], 'inclusive_programme_user_unique');
        });

        Schema::create('credit_support_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->string('instrument_type')->index();
            $table->string('provider_name')->nullable();
            $table->string('external_reference')->nullable()->index();
            $table->unsignedBigInteger('value_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->string('verification_status')->default('pending')->index();
            $table->json('evidence')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fair_treatment_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->foreignId('credit_decision_id')->nullable()->constrained('credit_decisions')->nullOnDelete();
            $table->string('assessment_type')->default('decision_review')->index();
            $table->string('status')->index();
            $table->json('reason_codes')->nullable();
            $table->json('metrics')->nullable();
            $table->string('policy_version')->default('fair-treatment-v1');
            $table->timestamp('assessed_at')->index();
            $table->timestamps();
        });

        Schema::create('impact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('inclusive_finance_enrolments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('outcome_code')->nullable()->index();
            $table->bigInteger('numeric_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['programme_id', 'event_type', 'occurred_at'], 'impact_programme_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_events');
        Schema::dropIfExists('fair_treatment_assessments');
        Schema::dropIfExists('credit_support_instruments');
        Schema::dropIfExists('inclusive_finance_enrolments');
        Schema::dropIfExists('inclusive_finance_programmes');
        Schema::dropIfExists('alternative_data_signals');
        Schema::dropIfExists('financial_capability_events');
        Schema::dropIfExists('inclusive_finance_profiles');
    }
};
