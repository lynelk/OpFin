<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opfin_plans', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name');
            $table->string('audience')->default('individual'); $table->string('status')->default('active')->index();
            $table->unsignedBigInteger('price_minor')->default(0); $table->char('currency', 3)->default('UGX');
            $table->string('billing_period')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('financial_space_entitlements', function (Blueprint $table) {
            $table->id(); $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opfin_plan_id')->nullable()->constrained('opfin_plans')->nullOnDelete();
            $table->string('entitlement_key'); $table->string('status')->default('active')->index();
            $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable(); $table->json('limits')->nullable(); $table->timestamps();
            $table->unique(['financial_space_id','entitlement_key'], 'fse_space_key_unique');
        });
        Schema::create('partners', function (Blueprint $table) {
            $table->id(); $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique(); $table->string('name'); $table->string('partner_type')->index();
            $table->char('country', 2)->default('UG')->index(); $table->string('status')->default('onboarding')->index();
            $table->string('adapter_key')->nullable(); $table->json('regulatory_evidence')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('partner_products', function (Blueprint $table) {
            $table->id(); $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('code'); $table->string('name'); $table->string('product_type')->index(); $table->string('status')->default('draft')->index();
            $table->char('country', 2)->default('UG')->index(); $table->char('currency', 3)->default('UGX');
            $table->json('eligibility_rules')->nullable(); $table->json('pricing')->nullable(); $table->json('disclosures')->nullable(); $table->json('integration_config')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->unique(['partner_id','code']);
        });
        Schema::create('commercial_agreements', function (Blueprint $table) {
            $table->id(); $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agreement_type')->index(); $table->string('status')->default('draft')->index();
            $table->date('effective_from'); $table->date('effective_to')->nullable(); $table->json('terms'); $table->timestamps();
        });
        Schema::create('revenue_events', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_product_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('commercial_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index(); $table->string('source_type')->nullable(); $table->string('source_reference')->nullable()->index();
            $table->unsignedBigInteger('gross_amount_minor'); $table->bigInteger('opfin_amount_minor')->default(0); $table->bigInteger('partner_amount_minor')->default(0); $table->bigInteger('tax_amount_minor')->default(0);
            $table->char('currency',3)->default('UGX'); $table->string('status')->default('accrued')->index(); $table->string('cpay_reference')->nullable()->index(); $table->string('reconciliation_reference')->nullable()->index();
            $table->timestamp('occurred_at'); $table->timestamp('settled_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->unique(['event_type','source_type','source_reference'], 'revenue_source_idempotency');
        });
        Schema::create('financial_obligations', function (Blueprint $table) {
            $table->id(); $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->index(); $table->string('direction')->index(); $table->string('counterparty_name')->nullable();
            $table->unsignedBigInteger('original_amount_minor'); $table->unsignedBigInteger('outstanding_amount_minor'); $table->char('currency',3)->default('UGX');
            $table->date('due_date')->nullable()->index(); $table->string('status')->default('open')->index(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('financial_assets', function (Blueprint $table) {
            $table->id(); $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset_type')->index(); $table->string('name'); $table->unsignedBigInteger('value_minor')->default(0); $table->char('currency',3)->default('UGX');
            $table->date('valued_at')->nullable(); $table->string('status')->default('active')->index(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('organisation_onboarding_cases', function (Blueprint $table) {
            $table->id(); $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->string('organisation_type')->index(); $table->string('stage')->default('profile')->index(); $table->string('status')->default('draft')->index();
            $table->json('requirements')->nullable(); $table->json('evidence')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('verified_at')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_onboarding_cases'); Schema::dropIfExists('financial_assets'); Schema::dropIfExists('financial_obligations');
        Schema::dropIfExists('revenue_events'); Schema::dropIfExists('commercial_agreements'); Schema::dropIfExists('partner_products'); Schema::dropIfExists('partners');
        Schema::dropIfExists('financial_space_entitlements'); Schema::dropIfExists('opfin_plans');
    }
};
