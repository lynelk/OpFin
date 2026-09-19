<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production reconciliation: this dormant foundation existed before the
        // migration ledger was aligned in some environments. If its membership
        // table is already present, treat the legacy foundation as authoritative
        // and allow Laravel to record this migration rather than attempting a
        // destructive duplicate create.
        if (Schema::hasTable('community_finance_memberships')) {
            return;
        }

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
            $table->unsignedBigInteger('community_finance_programme_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('employer_institution_id')->nullable();
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
            $table->unique(['community_finance_programme_id', 'user_id'], 'cfm_programme_user_unique');
            $table->unique(['community_finance_programme_id', 'member_number'], 'cfm_programme_member_unique');
            $table->index('community_finance_programme_id', 'cfm_programme_idx');
            $table->index('user_id', 'cfm_user_idx');
            $table->index('employer_institution_id', 'cfm_employer_idx');
            $table->foreign('community_finance_programme_id', 'cfm_programme_fk')->references('id')->on('community_finance_programmes')->restrictOnDelete();
            $table->foreign('user_id', 'cfm_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('employer_institution_id', 'cfm_employer_fk')->references('id')->on('institutions')->nullOnDelete();
        });

        Schema::create('community_finance_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_finance_programme_id');
            $table->unsignedBigInteger('community_finance_membership_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('account_code');
            $table->string('account_type')->index();
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('balance_minor')->default(0);
            $table->string('status')->default('dormant')->index();
            $table->json('restrictions')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['community_finance_programme_id', 'account_code'], 'cfla_programme_code_unique');
            $table->index('community_finance_programme_id', 'cfla_programme_idx');
            $table->index('community_finance_membership_id', 'cfla_membership_idx');
            $table->index('user_id', 'cfla_user_idx');
            $table->foreign('community_finance_programme_id', 'cfla_programme_fk')->references('id')->on('community_finance_programmes')->restrictOnDelete();
            $table->foreign('community_finance_membership_id', 'cfla_membership_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
            $table->foreign('user_id', 'cfla_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('community_finance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_finance_programme_id');
            $table->unsignedBigInteger('community_finance_ledger_account_id');
            $table->unsignedBigInteger('community_finance_membership_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
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
            $table->unique(['community_finance_programme_id', 'idempotency_key'], 'cfle_programme_idem_unique');
            $table->index(['source_type', 'source_id'], 'cfle_source_idx');
            $table->index('community_finance_programme_id', 'cfle_programme_idx');
            $table->index('community_finance_ledger_account_id', 'cfle_account_idx');
            $table->index('community_finance_membership_id', 'cfle_membership_idx');
            $table->index('user_id', 'cfle_user_idx');
            $table->foreign('community_finance_programme_id', 'cfle_programme_fk')->references('id')->on('community_finance_programmes')->restrictOnDelete();
            $table->foreign('community_finance_ledger_account_id', 'cfle_account_fk')->references('id')->on('community_finance_ledger_accounts')->restrictOnDelete();
            $table->foreign('community_finance_membership_id', 'cfle_membership_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
            $table->foreign('user_id', 'cfle_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('community_finance_facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_finance_programme_id');
            $table->unsignedBigInteger('community_finance_membership_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('employer_institution_id')->nullable();
            $table->unsignedBigInteger('credit_decision_id')->nullable();
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
            $table->index('community_finance_programme_id', 'cff_programme_idx');
            $table->index('community_finance_membership_id', 'cff_membership_idx');
            $table->index('user_id', 'cff_user_idx');
            $table->index('employer_institution_id', 'cff_employer_idx');
            $table->index('credit_decision_id', 'cff_decision_idx');
            $table->foreign('community_finance_programme_id', 'cff_programme_fk')->references('id')->on('community_finance_programmes')->restrictOnDelete();
            $table->foreign('community_finance_membership_id', 'cff_membership_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
            $table->foreign('user_id', 'cff_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('employer_institution_id', 'cff_employer_fk')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('credit_decision_id', 'cff_decision_fk')->references('id')->on('credit_decisions')->nullOnDelete();
        });

        Schema::create('community_finance_guarantees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_finance_facility_id');
            $table->unsignedBigInteger('guarantor_membership_id')->nullable();
            $table->unsignedBigInteger('guarantor_user_id')->nullable();
            $table->string('status')->default('requested')->index();
            $table->unsignedBigInteger('guarantee_amount_minor')->default(0);
            $table->string('consent_reference')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('community_finance_facility_id', 'cfg_facility_idx');
            $table->index('guarantor_membership_id', 'cfg_member_idx');
            $table->index('guarantor_user_id', 'cfg_user_idx');
            $table->foreign('community_finance_facility_id', 'cfg_facility_fk')->references('id')->on('community_finance_facilities')->restrictOnDelete();
            $table->foreign('guarantor_membership_id', 'cfg_member_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
            $table->foreign('guarantor_user_id', 'cfg_user_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('community_finance_scorecards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('community_finance_programme_id')->nullable();
            $table->unsignedBigInteger('community_finance_membership_id')->nullable();
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
            $table->index(['user_id', 'generated_at'], 'cfs_user_generated_idx');
            $table->index('community_finance_programme_id', 'cfs_programme_idx');
            $table->index('community_finance_membership_id', 'cfs_membership_idx');
            $table->foreign('user_id', 'cfs_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('community_finance_programme_id', 'cfs_programme_fk')->references('id')->on('community_finance_programmes')->nullOnDelete();
            $table->foreign('community_finance_membership_id', 'cfs_membership_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
        });

        Schema::create('community_finance_partner_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('community_finance_programme_id')->nullable();
            $table->unsignedBigInteger('community_finance_membership_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
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
            $table->index('community_finance_programme_id', 'cfp_programme_idx');
            $table->index('community_finance_membership_id', 'cfp_membership_idx');
            $table->index('user_id', 'cfp_user_idx');
            $table->foreign('community_finance_programme_id', 'cfp_programme_fk')->references('id')->on('community_finance_programmes')->nullOnDelete();
            $table->foreign('community_finance_membership_id', 'cfp_membership_fk')->references('id')->on('community_finance_memberships')->nullOnDelete();
            $table->foreign('user_id', 'cfp_user_fk')->references('id')->on('users')->nullOnDelete();
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
