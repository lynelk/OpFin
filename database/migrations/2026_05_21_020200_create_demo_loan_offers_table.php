<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_loan_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_loan_decision_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending_acceptance')->index();
            $table->unsignedBigInteger('principal_amount_minor');
            $table->unsignedBigInteger('total_repayment_minor');
            $table->unsignedInteger('duration_days');
            $table->decimal('interest_rate', 8, 4);
            $table->string('interest_type');
            $table->string('repayment_frequency');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_loan_offers');
    }
};
