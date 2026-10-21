<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_group_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['financial_space_id', 'status']);
        });

        Schema::create('savings_group_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users');
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->string('kind', 32);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('pledged');
            $table->string('idempotency_key', 120);
            $table->foreignId('statement_transaction_id')->nullable()->unique()
                ->constrained('financial_space_transactions');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'idempotency_key'], 'sg_contribution_idempotency');
            $table->index(['cycle_id', 'member_user_id', 'kind', 'status'], 'sg_contribution_lookup');
        });

        Schema::create('savings_group_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->cascadeOnDelete();
            $table->string('title', 160);
            $table->timestamp('scheduled_at');
            $table->string('status', 24)->default('planned');
            $table->unsignedInteger('quorum_required');
            $table->unsignedInteger('declared_present_count')->nullable();
            $table->text('minutes')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('savings_group_member_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->cascadeOnDelete();
            $table->foreignId('borrower_user_id')->constrained('users');
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedInteger('term_days');
            $table->unsignedInteger('interest_rate_bps')->default(0);
            $table->char('currency', 3);
            $table->string('status', 24)->default('requested');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('disbursement_transaction_id')->nullable()->unique()
                ->constrained('financial_space_transactions');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
            $table->index(['cycle_id', 'borrower_user_id', 'status'], 'sg_loan_lookup');
        });

        Schema::create('savings_group_social_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->string('status', 24)->default('requested');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('payout_transaction_id')->nullable()->unique()
                ->constrained('financial_space_transactions');
            $table->timestamps();
        });

        Schema::create('savings_group_shareout_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('savings_group_cycles')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users');
            $table->unsignedBigInteger('principal_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('proposed');
            $table->foreignId('proposed_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('payout_transaction_id')->nullable()->unique()
                ->constrained('financial_space_transactions');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'member_user_id'], 'sg_shareout_per_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_group_shareout_allocations');
        Schema::dropIfExists('savings_group_social_claims');
        Schema::dropIfExists('savings_group_member_loans');
        Schema::dropIfExists('savings_group_meetings');
        Schema::dropIfExists('savings_group_contributions');
        Schema::dropIfExists('savings_group_cycles');
    }
};
