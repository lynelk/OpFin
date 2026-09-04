<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crb_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_reference')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->integer('score')->nullable();
            $table->json('risk_flags')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crb_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->default(0);
            $table->unsignedBigInteger('monthly_income_minor')->nullable();
            $table->unsignedBigInteger('estimated_obligation_minor')->nullable();
            $table->json('reason_codes');
            $table->text('decision_summary');
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique('loan_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_decisions');
        Schema::dropIfExists('crb_reports');
    }
};
