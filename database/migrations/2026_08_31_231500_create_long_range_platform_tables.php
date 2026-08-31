<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 40);
            $table->string('provider', 80);
            $table->string('masked_identifier', 120);
            $table->string('consent_reference', 160)->nullable();
            $table->string('status', 40)->default('pending_verification');
            $table->string('data_confidence', 40)->default('user_declared');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('household_finance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('household_size')->default(1);
            $table->bigInteger('monthly_income_minor')->nullable();
            $table->bigInteger('monthly_fixed_costs_minor')->nullable();
            $table->bigInteger('emergency_target_minor')->nullable();
            $table->json('shared_goals')->nullable();
            $table->json('dependants')->nullable();
            $table->timestamps();
        });

        Schema::create('microbusiness_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name', 160);
            $table->string('business_type', 80);
            $table->string('registration_reference', 160)->nullable();
            $table->bigInteger('monthly_revenue_minor')->nullable();
            $table->bigInteger('monthly_expense_minor')->nullable();
            $table->json('operating_data')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_finance_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_category', 80);
            $table->string('asset_description', 255);
            $table->bigInteger('asset_price_minor');
            $table->bigInteger('deposit_minor')->default(0);
            $table->unsignedInteger('requested_term_months');
            $table->string('status', 40)->default('submitted');
            $table->boolean('geolocation_consent')->default(false);
            $table->json('decision_evidence')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('community_finance_memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('institution_type', 40);
            $table->string('institution_name', 160);
            $table->string('member_reference', 160)->nullable();
            $table->string('status', 40)->default('pending_verification');
            $table->json('permissions')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'institution_type']);
        });

        Schema::create('participatory_finance_listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('borrower_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 160);
            $table->bigInteger('target_amount_minor');
            $table->bigInteger('funded_amount_minor')->default(0);
            $table->unsignedInteger('term_days');
            $table->string('status', 40)->default('draft');
            $table->string('lender_of_record', 160)->nullable();
            $table->json('disclosures')->nullable();
            $table->timestamps();
        });

        Schema::create('participatory_finance_commitments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('listing_id')->constrained('participatory_finance_listings')->cascadeOnDelete();
            $table->foreignId('investor_user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('status', 40)->default('awaiting_step_up');
            $table->string('payment_reference', 160)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'investor_user_id', 'reference']);
        });

        Schema::create('referral_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 40);
            $table->string('event_type', 60)->default('invited');
            $table->string('status', 40)->default('pending');
            $table->bigInteger('reward_minor')->default(0);
            $table->json('abuse_checks')->nullable();
            $table->timestamps();
            $table->index(['referrer_user_id', 'status']);
        });

        Schema::create('ussd_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 160)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32);
            $table->string('state', 60)->default('start');
            $table->json('context')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offline_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_reference', 160);
            $table->string('status', 40)->default('accepted');
            $table->unsignedInteger('event_count')->default(0);
            $table->string('payload_hash', 64);
            $table->json('events');
            $table->json('conflicts')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'device_reference']);
        });

        Schema::create('capital_mandates', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mandate_type', 60);
            $table->string('name', 160);
            $table->bigInteger('committed_capital_minor')->default(0);
            $table->bigInteger('deployed_capital_minor')->default(0);
            $table->string('status', 40)->default('draft');
            $table->json('investment_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_distribution_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('partner_name', 160);
            $table->string('partner_type', 60);
            $table->string('status', 40)->default('pending_due_diligence');
            $table->json('allowed_products')->nullable();
            $table->json('commercial_terms')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_distribution_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('partner_account_id')->constrained('partner_distribution_accounts')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('product_code', 80)->nullable();
            $table->string('status', 40)->default('received');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_distribution_events');
        Schema::dropIfExists('partner_distribution_accounts');
        Schema::dropIfExists('capital_mandates');
        Schema::dropIfExists('offline_sync_batches');
        Schema::dropIfExists('ussd_sessions');
        Schema::dropIfExists('referral_events');
        Schema::dropIfExists('participatory_finance_commitments');
        Schema::dropIfExists('participatory_finance_listings');
        Schema::dropIfExists('community_finance_memberships');
        Schema::dropIfExists('asset_finance_requests');
        Schema::dropIfExists('microbusiness_profiles');
        Schema::dropIfExists('household_finance_profiles');
        Schema::dropIfExists('linked_financial_accounts');
    }
};
