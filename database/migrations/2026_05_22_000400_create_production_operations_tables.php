<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->date('business_date')->index();
            $table->string('status')->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('system_amount_minor')->default(0);
            $table->unsignedBigInteger('provider_amount_minor')->nullable();
            $table->string('status')->default('unmatched')->index();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number')->unique();
            $table->string('category')->index();
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('subject');
            $table->text('description');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('generated')->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('parameters')->nullable();
            $table->json('summary');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
        Schema::dropIfExists('support_cases');
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_runs');
    }
};
