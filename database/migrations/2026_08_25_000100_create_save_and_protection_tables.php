<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('partner_name');
            $table->string('partner_product_reference')->nullable();
            $table->string('country_code', 2)->default('UG')->index();
            $table->string('currency', 3)->default('UGX');
            $table->string('product_type')->default('goal')->index();
            $table->string('status')->default('draft')->index();
            $table->string('custody_model')->default('partner_held');
            $table->unsignedBigInteger('minimum_contribution_minor')->default(0);
            $table->unsignedBigInteger('maximum_contribution_minor')->nullable();
            $table->unsignedBigInteger('minimum_withdrawal_minor')->default(0);
            $table->unsignedSmallInteger('notice_days')->default(0);
            $table->unsignedSmallInteger('lock_days')->default(0);
            $table->string('terms_version')->default('v1');
            $table->string('terms_url')->nullable();
            $table->json('disclosures')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('savings_product_id')->constrained()->restrictOnDelete();
            $table->string('goal_reference')->unique();
            $table->string('name');
            $table->unsignedBigInteger('target_amount_minor')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedBigInteger('scheduled_amount_minor')->nullable();
            $table->string('contribution_frequency')->nullable();
            $table->boolean('autopilot_enabled')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('savings_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('movement_reference')->unique();
            $table->string('movement_type')->index();
            $table->string('status')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->string('idempotency_key')->unique();
            $table->string('partner_reference')->nullable()->index();
            $table->string('partner_evidence_hash', 64)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('provider_completed_at')->nullable();
            $table->timestamp('partner_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['savings_goal_id', 'movement_type', 'status']);
        });

        Schema::create('protection_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('insurer_name');
            $table->string('underwriter_name')->nullable();
            $table->string('partner_product_reference')->nullable();
            $table->string('country_code', 2)->default('UG')->index();
            $table->string('currency', 3)->default('UGX');
            $table->string('product_type')->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('premium_amount_minor');
            $table->string('premium_frequency')->default('monthly');
            $table->unsignedBigInteger('coverage_limit_minor')->nullable();
            $table->string('disclosure_version')->default('v1');
            $table->json('benefits')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('disclosure_payload');
            $table->string('terms_url')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('protection_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protection_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('policy_reference')->unique();
            $table->string('external_policy_number')->nullable()->index();
            $table->string('partner_reference')->nullable()->index();
            $table->string('status')->default('premium_due')->index();
            $table->unsignedBigInteger('premium_amount_minor');
            $table->string('premium_frequency');
            $table->unsignedBigInteger('coverage_limit_minor')->nullable();
            $table->date('cover_start_date')->nullable();
            $table->date('cover_end_date')->nullable();
            $table->date('next_premium_due_date')->nullable();
            $table->string('disclosure_hash', 64);
            $table->json('acceptance_metadata')->nullable();
            $table->timestamp('enrolled_at');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('protection_premium_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protection_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('status')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->date('coverage_period_start')->nullable();
            $table->date('coverage_period_end')->nullable();
            $table->string('partner_reference')->nullable()->index();
            $table->string('partner_evidence_hash', 64)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('provider_completed_at')->nullable();
            $table->timestamp('partner_confirmed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['protection_policy_id', 'status']);
        });

        Schema::create('protection_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protection_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_reference')->unique();
            $table->string('partner_claim_reference')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->date('incident_date');
            $table->string('category');
            $table->text('description');
            $table->unsignedBigInteger('claimed_amount_minor')->nullable();
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->json('evidence')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protection_claims');
        Schema::dropIfExists('protection_premium_payments');
        Schema::dropIfExists('protection_policies');
        Schema::dropIfExists('protection_products');
        Schema::dropIfExists('savings_movements');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('savings_products');
    }
};
