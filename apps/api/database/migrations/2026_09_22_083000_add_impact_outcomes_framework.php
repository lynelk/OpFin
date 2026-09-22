<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_indicator_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('outcome_domain')->index();
            $table->string('value_type')->index();
            $table->string('unit')->nullable();
            $table->text('calculation_methodology')->nullable();
            $table->string('collection_method')->default('system_or_authorised_capture');
            $table->string('source_type')->default('mixed');
            $table->boolean('verification_required')->default(false);
            $table->string('frequency')->nullable();
            $table->boolean('baseline_required')->default(false);
            $table->string('privacy_classification')->default('programme_measurement');
            $table->boolean('credit_decision_eligible')->default(false);
            $table->string('framework')->default('opfin_impact');
            $table->string('framework_version')->default('1.0');
            $table->json('disaggregation_dimensions')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('programme_theories_of_change', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->unique()->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->text('problem_statement')->nullable();
            $table->json('inputs')->nullable();
            $table->json('interventions')->nullable();
            $table->json('outputs')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('impact')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('risks')->nullable();
            $table->json('evidence_sources')->nullable();
            $table->string('version')->default('1.0');
            $table->string('status')->default('draft')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inclusive_finance_programme_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('indicator_definition_id')->constrained('impact_indicator_definitions')->cascadeOnDelete();
            $table->decimal('target_numeric', 18, 4)->nullable();
            $table->string('target_text')->nullable();
            $table->string('reporting_frequency')->nullable();
            $table->boolean('baseline_required')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['programme_id', 'indicator_definition_id'], 'programme_indicator_unique');
        });

        Schema::create('programme_outcome_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('inclusive_finance_enrolments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('indicator_definition_id')->constrained('impact_indicator_definitions')->restrictOnDelete();
            $table->string('measurement_stage')->index();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('unit')->nullable();
            $table->string('source_type')->index();
            $table->string('source_reference')->nullable()->index();
            $table->json('provenance')->nullable();
            $table->string('verification_status')->default('self_reported')->index();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['programme_id', 'indicator_definition_id', 'measurement_stage'],
                'programme_indicator_stage'
            );
        });

        Schema::create('financial_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('inclusive_finance_enrolments')->nullOnDelete();
            $table->string('measurement_stage')->default('check_in')->index();
            $table->string('income_stability')->nullable();
            $table->unsignedInteger('essential_expense_coverage_days')->nullable();
            $table->unsignedBigInteger('emergency_savings_minor')->nullable();
            $table->unsignedBigInteger('monthly_income_minor')->nullable();
            $table->unsignedBigInteger('total_debt_minor')->nullable();
            $table->unsignedBigInteger('scheduled_debt_service_minor')->nullable();
            $table->boolean('repayment_stress')->nullable();
            $table->boolean('insurance_protection')->nullable();
            $table->boolean('shock_in_last_90_days')->nullable();
            $table->boolean('recovered_from_shock')->nullable();
            $table->string('savings_direction')->nullable();
            $table->string('financial_health_status')->index();
            $table->json('status_reasons')->nullable();
            $table->json('provenance')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->timestamps();
        });

        Schema::create('livelihood_outcome_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('inclusive_finance_enrolments')->nullOnDelete();
            $table->string('measurement_stage')->index();
            $table->string('employment_status')->nullable();
            $table->string('business_activity')->nullable();
            $table->unsignedBigInteger('monthly_income_minor')->nullable();
            $table->unsignedBigInteger('monthly_revenue_minor')->nullable();
            $table->boolean('enterprise_operating')->nullable();
            $table->unsignedBigInteger('productive_assets_minor')->nullable();
            $table->unsignedInteger('workers_total')->nullable();
            $table->unsignedInteger('jobs_created')->nullable();
            $table->unsignedInteger('jobs_retained')->nullable();
            $table->string('income_reliability')->nullable();
            $table->unsignedInteger('weekly_work_hours')->nullable();
            $table->unsignedTinyInteger('work_satisfaction')->nullable();
            $table->unsignedTinyInteger('work_dignity')->nullable();
            $table->unsignedTinyInteger('sense_of_purpose')->nullable();
            $table->json('provenance')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->timestamps();
        });

        Schema::create('empowerment_outcome_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('enrolment_id')->constrained('inclusive_finance_enrolments')->cascadeOnDelete();
            $table->string('measurement_stage')->index();
            $table->boolean('controls_income')->nullable();
            $table->boolean('controls_savings')->nullable();
            $table->string('financial_decision_role')->nullable();
            $table->boolean('controls_productive_assets')->nullable();
            $table->boolean('can_use_financial_services_independently')->nullable();
            $table->boolean('has_personal_device_or_account_access')->nullable();
            $table->unsignedTinyInteger('financial_confidence')->nullable();
            $table->boolean('group_participation')->nullable();
            $table->json('provenance')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->timestamps();
        });

        Schema::create('community_finance_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('inclusive_finance_programmes')->nullOnDelete();
            $table->foreignId('enrolment_id')->nullable()->constrained('inclusive_finance_enrolments')->nullOnDelete();
            $table->string('source_type')->default('self_reported')->index();
            $table->string('group_reference')->nullable()->index();
            $table->string('provider_name')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('membership_status')->nullable();
            $table->date('membership_since')->nullable();
            $table->unsignedBigInteger('savings_balance_minor')->nullable();
            $table->unsignedInteger('contribution_streak')->nullable();
            $table->unsignedInteger('completed_group_loans')->nullable();
            $table->string('repayment_history')->nullable();
            $table->string('leadership_role')->nullable();
            $table->unsignedBigInteger('guarantee_capacity_minor')->nullable();
            $table->string('verification_status')->default('self_reported')->index();
            $table->json('provenance')->nullable();
            $table->boolean('credit_decision_eligible')->default(false);
            $table->timestamp('observed_at')->index();
            $table->timestamps();
        });

        Schema::create('programme_partner_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('inclusive_finance_programmes')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('read_only')->index();
            $table->boolean('can_view_individual_records')->default(false);
            $table->string('status')->default('active')->index();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['programme_id', 'user_id'], 'programme_partner_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_partner_access');
        Schema::dropIfExists('community_finance_evidence');
        Schema::dropIfExists('empowerment_outcome_snapshots');
        Schema::dropIfExists('livelihood_outcome_snapshots');
        Schema::dropIfExists('financial_health_snapshots');
        Schema::dropIfExists('programme_outcome_observations');
        Schema::dropIfExists('inclusive_finance_programme_indicators');
        Schema::dropIfExists('programme_theories_of_change');
        Schema::dropIfExists('impact_indicator_definitions');
    }
};
