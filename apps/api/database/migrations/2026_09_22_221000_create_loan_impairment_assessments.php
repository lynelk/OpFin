<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_impairment_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->date('as_of_date');
            $table->string('stage')->index();
            $table->unsignedBigInteger('gross_exposure_minor');
            $table->unsignedBigInteger('expected_credit_loss_minor');
            $table->string('currency', 3);
            $table->string('policy_version');
            $table->json('evidence');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->unique(['loan_id', 'as_of_date']);
            $table->index(['as_of_date', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_impairment_assessments');
    }
};
