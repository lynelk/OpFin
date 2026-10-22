<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_space_member_journals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->unique()
                ->constrained('financial_space_transactions')->restrictOnDelete();
            $table->foreignId('action_intent_id')->nullable()->unique()
                ->constrained('financial_space_action_intents')->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->string('event_key', 190);
            $table->string('event_type', 70);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('evidence_level', 60);
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['financial_space_id', 'event_key'], 'fsmj_event_dedupe');
        });

        Schema::create('financial_space_member_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_id')->constrained('financial_space_member_journals')
                ->restrictOnDelete();
            $table->string('account_code', 90);
            $table->foreignId('member_user_id')->nullable()->constrained('users');
            $table->string('side', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamps();
            $table->index(['journal_id', 'side'], 'fsmj_entry_side');
            $table->index(['account_code', 'member_user_id'], 'fsmj_account_member');
        });

        Schema::create('savings_group_loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->restrictOnDelete();
            $table->foreignId('loan_id')->constrained('savings_group_member_loans')->restrictOnDelete();
            $table->foreignId('action_intent_id')->unique()
                ->constrained('financial_space_action_intents')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->unique()
                ->constrained('financial_space_transactions')->restrictOnDelete();
            $table->unsignedBigInteger('principal_paid_minor');
            $table->char('currency', 3);
            $table->foreignId('allocated_by_user_id')->constrained('users');
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index(['loan_id', 'allocated_at']);
        });

        Schema::create('sacco_member_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_account_id')->constrained('sacco_member_accounts')->restrictOnDelete();
            $table->foreignId('member_user_id')->constrained('users');
            $table->foreignId('action_intent_id')->unique()
                ->constrained('financial_space_action_intents')->restrictOnDelete();
            $table->foreignId('treasury_transaction_id')->unique()
                ->constrained('financial_space_transactions')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->foreignId('allocated_by_user_id')->constrained('users');
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index(['financial_space_id', 'member_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_member_cash_movements');
        Schema::dropIfExists('savings_group_loan_repayments');
        Schema::dropIfExists('financial_space_member_journal_entries');
        Schema::dropIfExists('financial_space_member_journals');
    }
};
