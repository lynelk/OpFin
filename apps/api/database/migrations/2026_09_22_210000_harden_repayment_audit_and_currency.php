<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'currency']);
        });

        Schema::create('credit_repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->constrained('mobile_money_transactions')->nullOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('credit_repayment_schedule_item_id')->constrained('credit_repayment_schedule_items')->restrictOnDelete();
            $table->unsignedBigInteger('principal_minor')->default(0);
            $table->unsignedBigInteger('interest_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->timestamps();

            $table->unique(['transaction_id', 'credit_repayment_schedule_item_id'], 'credit_repayment_allocations_transaction_schedule_unique');
            $table->index(['loan_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_repayment_allocations');

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['code', 'currency']);
            $table->unique('code');
        });
    }
};
