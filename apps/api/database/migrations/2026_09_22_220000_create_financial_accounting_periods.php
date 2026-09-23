<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('closed')->index();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('financial_integrity_run_id')->nullable();
            $table->string('integrity_evidence_hash', 64)->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
            $table->index(['status', 'period_start', 'period_end'], 'financial_accounting_period_status_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounting_periods');
    }
};
