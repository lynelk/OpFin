<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('lender_relationship')->default('independent');
            $table->char('country', 2)->default('UG');
            $table->string('regulator_code')->nullable();
            $table->string('licence_class')->nullable();
            $table->string('authority_basis')->default('pending');
            $table->string('authority_reference')->nullable();
            $table->date('authority_valid_until')->nullable();
            $table->boolean('rate_change_approval_required')->default(true);
        });
        Schema::table('loan_products', function (Blueprint $table) {
            $table->char('country', 2)->default('UG');
            $table->char('currency', 3)->default('UGX');
            $table->string('product_category')->default('personal_loan');
            $table->unsignedBigInteger('min_amount_minor')->default(1);
            $table->unsignedBigInteger('max_amount_minor')->nullable();
            $table->json('borrower_purposes')->nullable();
            $table->foreignId('funding_pool_id')->nullable()->constrained('capital_mandates')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_platform_credit')->default(false);
        });
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->json('routing_snapshot')->nullable();
        });
        // Preserve existing values while allowing the cycles already supported by
        // CreditEconomicsService; channel policy does not constrain the schema.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            foreach (['interest_type', 'interest_cycle', 'repayment_frequency'] as $column) {
                DB::statement('ALTER TABLE loan_product_terms DROP CONSTRAINT IF EXISTS loan_product_terms_'.$column.'_check');
            }
        }
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->string('interest_type')->default('Flat')->change();
            $table->string('interest_cycle')->default('Monthly')->change();
            $table->string('repayment_frequency')->default('Monthly')->change();

            $table->string('regulatory_approval_reference')->nullable();
            $table->timestamp('regulatory_approved_at')->nullable();
        });
        Schema::table('credit_term_change_requests', function (Blueprint $table) {
            $table->string('regulatory_approval_reference')->nullable();
            $table->timestamp('regulatory_approved_at')->nullable();
        });
        Schema::create('credit_distribution_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 64);
            $table->unsignedInteger('version');
            $table->string('channel');
            $table->string('country', 2)->default('*');
            $table->string('product_category')->default('personal_loan');
            $table->foreignId('institution_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('partner_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('availability');
            $table->unsignedInteger('min_duration_days')->nullable();
            $table->decimal('max_apr_percent', 16, 6)->nullable();
            $table->text('reason');
            $table->string('source_reference');
            $table->text('source_url')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['scope_key', 'version']);
            $table->index(['channel', 'country', 'product_category'], 'credit_distribution_lookup');
        });
        Schema::create('platform_credit_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('mode');
            $table->text('reason');
            $table->unsignedBigInteger('max_affiliated_loan_minor')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credit_strategies');
        Schema::dropIfExists('credit_distribution_rules');
        Schema::table('credit_term_change_requests', fn (Blueprint $table) => $table->dropColumn(['regulatory_approval_reference', 'regulatory_approved_at']));
        Schema::table('loan_product_terms', fn (Blueprint $table) => $table->dropColumn(['regulatory_approval_reference', 'regulatory_approved_at']));
        Schema::table('loan_applications', fn (Blueprint $table) => $table->dropColumn('routing_snapshot'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_manage_platform_credit'));
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funding_pool_id');
            $table->dropColumn(['country', 'currency', 'product_category', 'min_amount_minor', 'max_amount_minor', 'borrower_purposes']);
        });
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn(['lender_relationship', 'country', 'regulator_code', 'licence_class', 'authority_basis', 'authority_reference', 'authority_valid_until', 'rate_change_approval_required']));
    }
};
