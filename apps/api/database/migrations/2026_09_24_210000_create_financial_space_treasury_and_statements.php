<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_space_treasury_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('account_name');
            $table->string('account_type')->index();
            $table->string('institution_name')->nullable();
            $table->string('account_reference_masked')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('current_balance_minor')->default(0);
            $table->string('status')->default('active')->index();
            $table->date('balance_as_of')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financial_space_id', 'status']);
            $table->unique(
                ['financial_space_id', 'account_name', 'currency'],
                'fst_accounts_space_name_currency_unique'
            );
        });

        Schema::create('financial_space_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_account_id')->constrained('financial_space_treasury_accounts')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('transaction_type')->default('other')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('description');
            $table->string('counterparty_name')->nullable();
            $table->date('transaction_date')->index();
            $table->date('value_date')->nullable()->index();
            $table->string('source_type')->default('manual')->index();
            $table->string('source_reference')->nullable()->index();
            $table->string('reconciliation_status')->default('unreconciled')->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['financial_space_id', 'transaction_date']);
            $table->index(['treasury_account_id', 'transaction_date']);
            $table->unique(
                ['financial_space_id', 'source_type', 'source_reference'],
                'fst_transactions_source_unique'
            );
        });

        Schema::create('financial_space_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_account_id')->constrained('financial_space_treasury_accounts')->cascadeOnDelete();
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_format')->index();
            $table->string('original_filename')->nullable();
            $table->string('source_hash', 64)->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->bigInteger('opening_balance_minor')->nullable();
            $table->bigInteger('closing_balance_minor')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('exception_count')->default(0);
            $table->string('status')->default('parsed')->index();
            $table->json('column_mapping')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->unique(
                ['financial_space_id', 'treasury_account_id', 'source_hash'],
                'fst_imports_dedupe_unique'
            );
        });

        Schema::create('financial_space_statement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_import_id')->constrained('financial_space_statement_imports')->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_account_id')->constrained('financial_space_treasury_accounts')->cascadeOnDelete();
            $table->foreignId('matched_transaction_id')->nullable()->constrained('financial_space_transactions')->nullOnDelete();
            $table->string('row_hash', 64);
            $table->string('statement_reference')->nullable()->index();
            $table->date('transaction_date')->index();
            $table->date('value_date')->nullable()->index();
            $table->string('description');
            $table->string('direction')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('running_balance_minor')->nullable();
            $table->string('reconciliation_status')->default('unmatched')->index();
            $table->string('exception_type')->nullable()->index();
            $table->string('match_method')->nullable();
            $table->unsignedTinyInteger('match_confidence_percent')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['statement_import_id', 'row_hash'], 'fst_statement_rows_dedupe_unique');
            $table->index(['financial_space_id', 'transaction_date']);
            $table->index(['treasury_account_id', 'reconciliation_status']);
        });

        Schema::create('financial_space_generated_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('statement_number')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treasury_account_id')->constrained('financial_space_treasury_accounts')->cascadeOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('opening_balance_minor');
            $table->bigInteger('closing_balance_minor');
            $table->unsignedBigInteger('total_debits_minor')->default(0);
            $table->unsignedBigInteger('total_credits_minor')->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->string('reconciliation_status')->default('unreconciled')->index();
            $table->string('content_hash', 64);
            $table->json('statement_payload');
            $table->timestamp('generated_at');
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['financial_space_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_space_generated_statements');
        Schema::dropIfExists('financial_space_statement_rows');
        Schema::dropIfExists('financial_space_statement_imports');
        Schema::dropIfExists('financial_space_transactions');
        Schema::dropIfExists('financial_space_treasury_accounts');
    }
};
