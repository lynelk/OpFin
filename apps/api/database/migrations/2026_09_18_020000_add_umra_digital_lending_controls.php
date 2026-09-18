<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_cases', function (Blueprint $table) {
            $table->timestamp('regulatory_due_at')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->boolean('sla_breached')->default(false)->index();
            $table->json('complaint_procedure_snapshot')->nullable();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('non_performing_at')->nullable()->index();
            $table->unsignedBigInteger('principal_at_npl_minor')->nullable();
            $table->unsignedBigInteger('initial_interest_minor')->nullable();
            $table->unsignedBigInteger('default_interest_accrued_minor')->default(0);
            $table->unsignedBigInteger('default_interest_cap_minor')->nullable();
            $table->unsignedBigInteger('npl_recovery_cap_minor')->nullable();
            $table->boolean('umra_npl_cap_enforcement_enabled')->default(true);
            $table->timestamp('npl_policy_checked_at')->nullable();
        });

        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->decimal('default_interest_rate', 12, 6)->default(0);
            $table->string('default_interest_cycle')->default('monthly');
            $table->string('umra_interest_approval_reference')->nullable();
            $table->timestamp('umra_interest_approved_at')->nullable();
        });

        Schema::create('credit_information_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type')->index();
            $table->string('event_type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('provider')->nullable()->index();
            $table->string('provider_reference')->nullable()->index();
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->timestamp('due_at')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'event_type', 'payload_hash'], 'credit_info_event_unique');
        });

        Schema::create('transaction_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('receipt_type')->index();
            $table->string('status')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->json('details');
            $table->char('receipt_hash', 64);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('guarantor_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('relationship')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('verification_channel')->default('sms');
            $table->char('verification_token_hash', 64)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('consent_evidence')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'phone']);
        });

        Schema::create('credit_term_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->json('current_terms');
            $table->json('proposed_terms');
            $table->text('reason');
            $table->boolean('customer_consent_required')->default(true);
            $table->string('umra_approval_reference')->nullable();
            $table->timestamp('umra_approved_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_term_change_requests');
        Schema::dropIfExists('guarantor_contacts');
        Schema::dropIfExists('transaction_receipts');
        Schema::dropIfExists('credit_information_reports');

        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->dropColumn([
                'default_interest_rate',
                'default_interest_cycle',
                'umra_interest_approval_reference',
                'umra_interest_approved_at',
            ]);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'non_performing_at',
                'principal_at_npl_minor',
                'initial_interest_minor',
                'default_interest_accrued_minor',
                'default_interest_cap_minor',
                'npl_recovery_cap_minor',
                'umra_npl_cap_enforcement_enabled',
                'npl_policy_checked_at',
            ]);
        });

        Schema::table('support_cases', function (Blueprint $table) {
            $table->dropColumn([
                'regulatory_due_at',
                'first_response_at',
                'sla_breached',
                'complaint_procedure_snapshot',
            ]);
        });
    }
};
