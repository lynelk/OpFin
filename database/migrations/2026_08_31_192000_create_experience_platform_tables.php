<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_activation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_financial_goal')->nullable()->index();
            $table->string('preferred_language')->default('en');
            $table->boolean('notifications_enabled')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('money_autopilot_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type')->index();
            $table->string('status')->default('draft')->index();
            $table->json('trigger_config');
            $table->json('action_config');
            $table->unsignedBigInteger('max_amount_minor')->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('next_evaluation_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('money_autopilot_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('money_autopilot_rules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('evaluated')->index();
            $table->string('action_type');
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->json('evidence')->nullable();
            $table->string('external_reference')->nullable()->index();
            $table->timestamp('evaluated_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->string('provider_name');
            $table->string('provider_reference')->nullable()->index();
            $table->string('product_type')->index();
            $table->string('risk_level')->index();
            $table->unsignedBigInteger('minimum_investment_minor')->default(0);
            $table->string('currency', 3)->default('UGX');
            $table->string('status')->default('draft')->index();
            $table->json('suitability_requirements')->nullable();
            $table->json('disclosures')->nullable();
            $table->string('created_by');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_suitability_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('risk_tolerance');
            $table->string('investment_horizon');
            $table->string('liquidity_need');
            $table->string('experience_level');
            $table->string('status')->default('assessed')->index();
            $table->json('answers')->nullable();
            $table->timestamp('assessed_at');
            $table->timestamps();
        });

        Schema::create('investment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_product_id')->constrained('investment_products');
            $table->uuid('idempotency_key')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->string('status')->default('pending_provider')->index();
            $table->json('suitability_snapshot');
            $table->json('disclosure_snapshot');
            $table->timestamp('disclosure_acknowledged_at');
            $table->string('provider_reference')->nullable()->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->nullable()->unique();
            $table->string('status')->default('pilot')->index();
            $table->string('country', 2)->default('UG');
            $table->string('payroll_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('employer_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_role')->default('employee')->index();
            $table->string('employee_reference')->nullable()->index();
            $table->string('employment_status')->default('active')->index();
            $table->string('employment_type')->nullable();
            $table->unsignedBigInteger('verified_monthly_income_minor')->nullable();
            $table->string('currency', 3)->default('UGX');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['employer_id', 'user_id']);
        });

        Schema::create('employer_benefit_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('employers')->cascadeOnDelete();
            $table->string('name');
            $table->string('benefit_type')->index();
            $table->string('status')->default('draft')->index();
            $table->json('eligibility_rules')->nullable();
            $table->json('configuration')->nullable();
            $table->string('created_by');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employer_benefit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('employer_benefit_programs')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('employer_memberships')->cascadeOnDelete();
            $table->string('status')->default('eligible')->index();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_benefit_enrollments');
        Schema::dropIfExists('employer_benefit_programs');
        Schema::dropIfExists('employer_memberships');
        Schema::dropIfExists('employers');
        Schema::dropIfExists('investment_orders');
        Schema::dropIfExists('investment_suitability_profiles');
        Schema::dropIfExists('investment_products');
        Schema::dropIfExists('money_autopilot_executions');
        Schema::dropIfExists('money_autopilot_rules');
        Schema::dropIfExists('customer_activation_profiles');
    }
};
