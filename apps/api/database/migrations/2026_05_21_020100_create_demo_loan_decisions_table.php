<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_loan_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->default(0);
            $table->unsignedBigInteger('monthly_income_minor')->default(0);
            $table->unsignedBigInteger('estimated_monthly_obligation_minor')->default(0);
            $table->json('reason_codes');
            $table->text('decision_summary');
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_loan_decisions');
    }
};
