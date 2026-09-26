<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_product_passports', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('jurisdiction', 8)->index();
            $table->string('regulated_activity', 120);
            $table->string('booking_entity', 160);
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('funding_entity', 160)->nullable();
            $table->string('servicer', 160)->nullable();
            $table->string('licence_or_approval_reference', 200)->nullable();
            $table->json('restrictions')->nullable();
            $table->json('tax_accounting_references')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sharia_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('authority_name', 180);
            $table->string('approval_reference', 200);
            $table->string('scope_type', 80);
            $table->string('scope_reference', 160);
            $table->string('status', 40)->default('draft')->index();
            $table->json('conditions')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['authority_name', 'approval_reference']);
        });

        Schema::create('financial_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->string('name', 180);
            $table->string('rail', 24)->index();
            $table->string('family', 80)->index();
            $table->string('contract_type', 80);
            $table->string('jurisdiction', 8)->index();
            $table->string('currency', 3)->default('UGX');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('legal_product_passport_id')->nullable()->constrained('legal_product_passports')->nullOnDelete();
            $table->foreignId('sharia_approval_id')->nullable()->constrained('sharia_approvals')->nullOnDelete();
            $table->json('customer_classes')->nullable();
            $table->json('purpose_rules')->nullable();
            $table->json('policy_versions')->nullable();
            $table->json('settlement_model')->nullable();
            $table->json('asset_supplier_requirements')->nullable();
            $table->json('disclosure')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('financial_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('need_type', 80)->index();
            $table->string('principles_preference', 32)->default('ALL_SUITABLE')->index();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->json('purpose')->nullable();
            $table->string('status', 40)->default('open')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('financial_intent_id')->constrained('financial_intents');
            $table->foreignId('financial_product_id')->constrained('financial_products');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->json('suitability_snapshot')->nullable();
            $table->json('eligibility_snapshot')->nullable();
            $table->json('risk_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('financing_arrangements', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('financing_application_id')->unique()->constrained('financing_applications');
            $table->foreignId('financial_product_id')->constrained('financial_products');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funding_pool_id')->nullable()->constrained('capital_mandates')->nullOnDelete();
            $table->string('contract_type', 80);
            $table->bigInteger('principal_or_cost_minor');
            $table->bigInteger('total_obligation_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('contracting')->index();
            $table->string('legacy_type', 80)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->json('contract_snapshot');
            $table->string('contract_hash', 64);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['legacy_type', 'legacy_id']);
        });

        Schema::create('contract_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financing_arrangement_id')->constrained('financing_arrangements')->cascadeOnDelete();
            $table->uuid('correlation_id')->index();
            $table->string('idempotency_key', 160);
            $table->string('event_type', 100);
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 240)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['financing_arrangement_id', 'idempotency_key']);
        });

        Schema::create('asset_passports', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset_class', 80)->index();
            $table->string('asset_subclass', 80)->nullable();
            $table->string('make', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('external_identifier_hash', 64)->nullable()->index();
            $table->json('identifier_evidence')->nullable();
            $table->json('ownership_evidence')->nullable();
            $table->json('valuation')->nullable();
            $table->string('status', 40)->default('registered')->index();
            $table->timestamps();
        });

        Schema::create('supplier_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('name', 180);
            $table->string('country', 8)->default('UG');
            $table->string('verification_status', 40)->default('pending')->index();
            $table->json('kyb_evidence')->nullable();
            $table->json('settlement_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_profiles');
        Schema::dropIfExists('asset_passports');
        Schema::dropIfExists('contract_events');
        Schema::dropIfExists('financing_arrangements');
        Schema::dropIfExists('financing_applications');
        Schema::dropIfExists('financial_intents');
        Schema::dropIfExists('financial_products');
        Schema::dropIfExists('sharia_approvals');
        Schema::dropIfExists('legal_product_passports');
    }
};
