<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->string('accounting_status')->default('unposted')->after('reconciliation_status')->index();
            $table->string('statement_reconciliation_status')->default('unreconciled')->after('accounting_status')->index();
            $table->timestamp('accounting_posted_at')->nullable()->after('statement_reconciliation_status');
            $table->timestamp('statement_reconciled_at')->nullable()->after('accounting_posted_at');
        });

        // Do not infer accounting completion from the legacy reconciliation field.
        // Historical successful/reversed movements remain unposted until immutable ledger
        // evidence is rebuilt or verified. Failed movements require no product accounting.
        DB::table('mobile_money_transactions')
            ->where('status', 'failed')
            ->update(['accounting_status' => 'not_required']);

        Schema::create('financial_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('policy_type')->index();
            $table->char('jurisdiction_country', 2)->default('UG')->index();
            $table->string('licence_class')->nullable()->index();
            $table->string('product_scope')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft')->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->json('rules');
            $table->string('source_reference')->nullable();
            $table->text('source_url')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['policy_type', 'jurisdiction_country', 'licence_class', 'status'], 'financial_policy_lookup');
        });

        Schema::create('financial_control_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('control_code')->index();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'control_code', 'status'], 'financial_override_subject');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('default_interest_paid_minor')->default(0)->after('default_interest_accrued_minor');
            $table->timestamp('default_interest_last_accrued_at')->nullable()->after('default_interest_paid_minor');
            $table->json('default_interest_policy_snapshot')->nullable()->after('default_interest_last_accrued_at');
        });

        Schema::create('early_settlement_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('quote_reference')->unique();
            $table->date('as_of_date')->index();
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('contractual_interest_due_minor')->default(0);
            $table->unsignedBigInteger('default_interest_due_minor')->default(0);
            $table->unsignedBigInteger('fees_due_minor')->default(0);
            $table->unsignedBigInteger('settlement_fee_minor')->default(0);
            $table->unsignedBigInteger('unearned_interest_rebate_minor')->default(0);
            $table->unsignedBigInteger('unearned_fee_rebate_minor')->default(0);
            $table->unsignedBigInteger('total_settlement_minor');
            $table->char('currency', 3)->default('UGX');
            $table->json('policy_snapshot')->nullable();
            $table->string('status')->default('quoted')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_fee_recognition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('recognition_method');
            $table->date('recognised_through')->index();
            $table->json('policy_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_reference')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opfin_plan_id')->constrained('opfin_plans')->restrictOnDelete();
            $table->foreignId('subscribed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending_payment')->index();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('billing_period')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('invoice_number')->unique();
            $table->foreignId('subscription_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('revenue_event_id')->nullable()->constrained('revenue_events')->nullOnDelete();
            $table->unsignedBigInteger('net_amount_minor');
            $table->unsignedBigInteger('tax_amount_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('status')->default('payment_pending')->index();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('revenue_events', function (Blueprint $table) {
            $table->uuid('occurrence_key')->nullable()->after('public_id')->unique();
            $table->foreignId('ledger_transaction_id')->nullable()->after('commercial_agreement_id')->constrained()->nullOnDelete();
            $table->string('accounting_status')->default('unposted')->after('status')->index();
            $table->string('statement_reconciliation_status')->default('unreconciled')->after('accounting_status')->index();
        });

        Schema::create('tax_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_reference')->unique();
            $table->foreignId('revenue_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tax_type')->index();
            $table->unsignedBigInteger('taxable_amount_minor');
            $table->decimal('rate_percent', 12, 6)->default(0);
            $table->unsignedBigInteger('tax_amount_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('status')->default('accrued')->index();
            $table->json('rule_snapshot');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('remitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('efris_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_reference')->unique();
            $table->foreignId('tax_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('revenue_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type')->default('invoice')->index();
            $table->string('status')->default('draft')->index();
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->string('provider_reference')->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efris_documents');
        Schema::dropIfExists('tax_events');

        Schema::table('revenue_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_transaction_id');
            $table->dropColumn(['occurrence_key', 'accounting_status', 'statement_reconciliation_status']);
        });

        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscription_contracts');
        Schema::dropIfExists('credit_fee_recognition_events');
        Schema::dropIfExists('early_settlement_quotes');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['default_interest_paid_minor', 'default_interest_last_accrued_at', 'default_interest_policy_snapshot']);
        });

        Schema::dropIfExists('financial_control_overrides');
        Schema::dropIfExists('financial_policies');

        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->dropColumn(['accounting_status', 'statement_reconciliation_status', 'accounting_posted_at', 'statement_reconciled_at']);
        });
    }
};
