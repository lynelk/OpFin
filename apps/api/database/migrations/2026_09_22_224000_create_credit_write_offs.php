<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->unique()->constrained('loans')->restrictOnDelete();
            $table->unsignedBigInteger('principal_written_off_minor');
            $table->unsignedBigInteger('financed_fee_written_off_minor')->default(0);
            $table->string('currency', 3);
            $table->string('policy_version');
            $table->json('evidence');
            $table->boolean('legal_obligation_preserved')->default(true);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('impairment_assessment_id')->constrained('loan_impairment_assessments')->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('written_off_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_write_offs');
    }
};
