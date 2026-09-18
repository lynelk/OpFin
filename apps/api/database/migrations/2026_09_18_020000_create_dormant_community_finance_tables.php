<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_finance_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('internal_key')->unique();
            $table->string('public_name');
            $table->string('plain_language_name')->nullable();
            $table->string('programme_type');
            $table->string('status')->default('dormant_ready')->index();
            $table->string('operating_model')->nullable();
            $table->char('country', 2)->default('UG')->index();
            $table->char('currency', 3)->default('UGX');
            $table->string('regulated_partner_name')->nullable();
            $table->string('activation_gate')->index();
            $table->json('activation_requirements')->nullable();
            $table->json('eligibility_rules')->nullable();
            $table->json('risk_controls')->nullable();
            $table->json('terms')->nullable();
            $table->json('disclosures')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('community_finance_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_programme_id')->constrained('community_finance_programmes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employer_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('member_number')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('member_role')->default('member');
            $table->unsignedBigInteger('share_capital_required_minor')->default(0);
            $table->unsignedBigInteger('share_capital_balance_minor')->default(0);
            $table->unsignedBigInteger('savings_balance_minor')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['community_finance_programme_id', 'user_id'], 'cf_memberships_programme_user_unique');
            $table->unique(['community_finance_programme_id', 'member_number'], 'cf_memberships_programme_member_unique');
        });

        Schema::create('community_finance_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_programme_id')->constrained('community_finance_programmes')->restrictOnDelete();
            $table->foreignId('community_finance_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_code');
            $table->string('account_type')->index();
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('balance_minor')->default(0);
            $table->string('status')->default('dormant')->index();
            $table->json('restrictions')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['community_finance_programme_id', 'account_code'], 'cf_ledger_accounts_programme_code_unique');
        });

        Schema::create('community_finance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_programme_id')->constrained('community_finance_programmes')->restrictOnDelete();
            $table->foreignId('community_finance_ledger_account_id')->constrained('community_finance_ledger_accounts')->restrictOnDelete();
            $table->foreignId('community_finance_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['community_finance_programme_id', 'idempotency_key'], 'cf_ledger_entries_programme_idempotency_unique');
            $table->index(['source_type', 'source_id'], 'cf_ledger_entries_source_index');
        });

        Schema::create('community_finance_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_programme_id')->constrained('community_finance_programmes')->restrictOnDelete();
            $table->foreignId('community_finance_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employer_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('credit_decision_id')->nullable()->constrained('credit_decisions')->nullOnDelete();
            $table->string('facility_type')->index();
            $table->string('public_product_name');
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('requested_amount_minor')->default(0);
            $table->unsignedBigInteger('approved_amount_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('tenor_days')->nullable();
            $table->json('pricing_summary')->nullable();
            $table->json('eligibility_snapshot')->nullable();
            $table->string('decision_status')->default('pending')->index();
            $table->string('source_lender_type')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('community_finance_guarantees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_facility_id')->constrained('community_finance_facilities')->restrictOnDelete();
            $table->foreignId('guarantor_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->foreignId('guarantor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('requested')->index();
            $table->unsignedBigInteger('guarantee_amount_minor')->default(0);
            $table->string('consent_reference')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('community_finance_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('community_finance_programme_id')->nullable()->constrained('community_finance_programmes')->nullOnDelete();
            $table->foreignId('community_finance_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->string('score_name')->default('Member Growth Score');
            $table->unsignedTinyInteger('composite_score')->nullable();
            $table->string('band')->nullable();
            $table->unsignedTinyInteger('confidence_percent')->default(0);
            $table->json('component_scores');
            $table->json('factor_breakdown');
            $table->json('explanation');
            $table->string('policy_version');
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'generated_at'], 'cf_scorecards_user_generated_index');
        });

        Schema::create('community_finance_partner_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_finance_programme_id')->nullable()->constrained('community_finance_programmes')->nullOnDelete();
            $table->foreignId('community_finance_membership_id')->nullable()->constrained('community_finance_memberships')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_type')->index();
            $table->string('public_product_name');
            $table->string('provider_name')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('asset_description')->nullable();
            $table->unsignedBigInteger('premium_amount_minor')->default(0);
            $table->unsignedBigInteger('financed_amount_minor')->default(0);
            $table->unsignedBigInteger('deposit_amount_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('term_months')->nullable();
            $table->string('partner_reference')->nullable();
            $table->json('terms')->nullable();
            $table->json('risk_controls')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_finance_partner_plans');
        Schema::dropIfExists('community_finance_scorecards');
        Schema::dropIfExists('community_finance_guarantees');
        Schema::dropIfExists('community_finance_facilities');
        Schema::dropIfExists('community_finance_ledger_entries');
        Schema::dropIfExists('community_finance_ledger_accounts');
        Schema::dropIfExists('community_finance_memberships');
        Schema::dropIfExists('community_finance_programmes');
    }
};
