<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capital_mandates', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('owner_user_id')->constrained('partners')->nullOnDelete();
        });

        Schema::create('essentials_billers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('category', 60)->index();
            $table->string('account_label', 100)->default('Account reference');
            $table->string('route', 40)->default('cpay');
            $table->string('status', 40)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('essentials_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('biller_id')->constrained('essentials_billers');
            $table->text('account_reference');
            $table->string('account_reference_hash', 64);
            $table->string('nickname', 120)->nullable();
            $table->string('verification_status', 40)->default('pending')->index();
            $table->string('provider_reference', 160)->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'biller_id', 'account_reference_hash'], 'essentials_account_unique');
        });

        Schema::create('essentials_credit_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lender_partner_id')->constrained('partners');
            $table->foreignId('partner_product_id')->constrained('partner_products');
            $table->foreignId('funding_pool_id')->nullable()->constrained('capital_mandates')->nullOnDelete();
            $table->bigInteger('approved_limit_minor');
            $table->bigInteger('available_limit_minor');
            $table->bigInteger('outstanding_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('active')->index();
            $table->string('decision_route', 40);
            $table->string('decision_reference', 160)->nullable()->index();
            $table->json('decision_snapshot')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'partner_product_id', 'financial_space_id'], 'essentials_line_scope_unique');
        });

        Schema::create('essentials_partner_authorisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_account_id')->constrained('partner_distribution_accounts')->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->json('scopes');
            $table->string('status', 40)->default('active')->index();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'partner_account_id', 'status'], 'essentials_partner_auth_lookup');
        });

        Schema::create('essentials_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('essentials_account_id')->constrained('essentials_accounts');
            $table->foreignId('credit_line_id')->constrained('essentials_credit_lines');
            $table->foreignId('source_partner_account_id')->nullable()->constrained('partner_distribution_accounts')->nullOnDelete();
            $table->string('source_platform', 80)->nullable()->index();
            $table->string('purpose_category', 60)->index();
            $table->bigInteger('amount_minor');
            $table->bigInteger('interest_minor')->default(0);
            $table->bigInteger('fees_minor')->default(0);
            $table->bigInteger('total_repayment_minor');
            $table->unsignedInteger('term_days');
            $table->char('currency', 3)->default('UGX');
            $table->foreignId('lender_partner_id')->constrained('partners');
            $table->foreignId('partner_product_id')->constrained('partner_products');
            $table->foreignId('funding_pool_id')->nullable()->constrained('capital_mandates')->nullOnDelete();
            $table->string('status', 40)->default('offered')->index();
            $table->json('disclosure_snapshot');
            $table->string('disclosure_hash', 64);
            $table->string('partner_authorisation_hash', 64)->nullable();
            $table->timestamp('partner_authorisation_expires_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('essentials_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('quote_id')->unique()->constrained('essentials_quotes');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_obligation_id')->nullable()->constrained('financial_obligations')->nullOnDelete();
            $table->foreignId('essentials_account_id')->constrained('essentials_accounts');
            $table->foreignId('lender_partner_id')->constrained('partners');
            $table->foreignId('partner_product_id')->constrained('partner_products');
            $table->foreignId('funding_pool_id')->nullable()->constrained('capital_mandates')->nullOnDelete();
            $table->bigInteger('principal_minor');
            $table->bigInteger('principal_outstanding_minor');
            $table->bigInteger('total_repayment_minor');
            $table->bigInteger('outstanding_minor');
            $table->bigInteger('repaid_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('funding_reserved')->index();
            $table->string('lender_contract_reference', 160)->nullable()->index();
            $table->string('biller_payment_reference', 160)->nullable()->index();
            $table->json('fulfilment_payload')->nullable();
            $table->json('repayment_schedule')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->date('final_due_date')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('essentials_repayment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('essentials_advances')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date')->index();
            $table->bigInteger('principal_original_minor');
            $table->bigInteger('principal_outstanding_minor');
            $table->bigInteger('interest_original_minor')->default(0);
            $table->bigInteger('interest_outstanding_minor')->default(0);
            $table->bigInteger('fees_original_minor')->default(0);
            $table->bigInteger('fees_outstanding_minor')->default(0);
            $table->bigInteger('total_original_minor');
            $table->bigInteger('total_outstanding_minor');
            $table->string('status', 40)->default('scheduled')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['advance_id', 'installment_number'], 'essentials_schedule_installment_unique');
        });

        Schema::create('essentials_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('advance_id')->constrained('essentials_advances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->bigInteger('principal_applied_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('pending')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->string('cpay_reference', 160)->nullable()->index();
            $table->string('provider_reference', 160)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('essentials_billers')->insert([
            ['code' => 'UEDCL', 'name' => 'UEDCL Electricity', 'category' => 'electricity', 'account_label' => 'Meter number', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NWSC', 'name' => 'National Water and Sewerage Corporation', 'category' => 'water', 'account_label' => 'Customer account number', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DSTV', 'name' => 'DStv', 'category' => 'television', 'account_label' => 'Smartcard / IUC number', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GOTV', 'name' => 'GOtv', 'category' => 'television', 'account_label' => 'IUC number', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'STARTIMES', 'name' => 'StarTimes', 'category' => 'television', 'account_label' => 'Smartcard number', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'INTERNET', 'name' => 'Internet service provider', 'category' => 'internet', 'account_label' => 'Subscriber account', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['generic' => true, 'country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LPG', 'name' => 'Cooking gas / LPG', 'category' => 'energy', 'account_label' => 'Customer / cylinder reference', 'route' => 'cpay', 'status' => 'active', 'metadata' => json_encode(['generic' => true, 'country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RENT', 'name' => 'Rent / landlord', 'category' => 'rent', 'account_label' => 'Tenancy / landlord reference', 'route' => 'manual_verification', 'status' => 'active', 'metadata' => json_encode(['generic' => true, 'requires_beneficiary_verification' => true, 'country' => 'UG']), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('essentials_repayments');
        Schema::dropIfExists('essentials_repayment_schedule_items');
        Schema::dropIfExists('essentials_advances');
        Schema::dropIfExists('essentials_quotes');
        Schema::dropIfExists('essentials_partner_authorisations');
        Schema::dropIfExists('essentials_credit_lines');
        Schema::dropIfExists('essentials_accounts');
        Schema::dropIfExists('essentials_billers');

        Schema::table('capital_mandates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
