<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_reference_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_offer_id')->nullable()->constrained('credit_offers')->nullOnDelete();
            $table->string('dedupe_key', 64)->unique();
            $table->string('event_type')->index();
            $table->string('information_type')->index();
            $table->date('reporting_date')->index();
            $table->string('status')->default('pending')->index();
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('due_at')->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['loan_id', 'reporting_date']);
        });

        Schema::create('transaction_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->unique()->constrained('mobile_money_transactions')->cascadeOnDelete();
            $table->string('receipt_reference')->unique();
            $table->string('transaction_type')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->index();
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->string('delivery_channel')->nullable();
            $table->timestamp('issued_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_npl_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('non_performing_at')->nullable()->index();
            $table->unsignedBigInteger('principal_at_npl_minor')->default(0);
            $table->unsignedBigInteger('initial_interest_minor')->default(0);
            $table->unsignedBigInteger('penalty_interest_accrued_minor')->default(0);
            $table->unsignedBigInteger('default_penalty_cap_minor')->default(0);
            $table->unsignedBigInteger('recoverable_interest_cap_minor')->default(0);
            $table->unsignedBigInteger('total_recoverable_cap_minor')->default(0);
            $table->unsignedBigInteger('total_recovered_since_npl_minor')->default(0);
            $table->string('enforcement_mode')->default('enforce')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('phone');
            $table->string('name')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('verification_method')->nullable();
            $table->string('verification_reference')->nullable();
            $table->json('consent_evidence')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'position']);
            $table->unique(['loan_application_id', 'phone']);
        });

        Schema::create('credit_term_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_offer_id')->nullable()->constrained('credit_offers')->nullOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('proposed')->index();
            $table->json('proposed_changes');
            $table->text('reason');
            $table->boolean('requires_umra_approval')->default(false);
            $table->string('umra_approval_reference')->nullable();
            $table->string('umra_approval_document_hash', 64)->nullable();
            $table->timestamp('umra_approved_at')->nullable();
            $table->string('customer_consent_hash', 64)->nullable();
            $table->json('customer_consent_metadata')->nullable();
            $table->timestamp('customer_consented_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });

        Schema::table('support_cases', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->index()->after('resolved_at');
            $table->string('regulatory_category')->nullable()->index()->after('category');
            $table->text('resolution_summary')->nullable()->after('description');
        });

        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->unsignedTinyInteger('guarantors_required')->default(0)->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->dropColumn('guarantors_required');
        });

        Schema::table('support_cases', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'regulatory_category', 'resolution_summary']);
        });

        Schema::dropIfExists('credit_term_variations');
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loan_npl_controls');
        Schema::dropIfExists('transaction_receipts');
        Schema::dropIfExists('credit_reference_submissions');
    }
};
