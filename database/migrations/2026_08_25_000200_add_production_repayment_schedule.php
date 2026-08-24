<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('credit_offer_id')->nullable()->after('loan_application_id')->unique()->constrained('credit_offers')->nullOnDelete();
        });

        Schema::create('credit_repayment_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_offer_id')->constrained('credit_offers')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('interest_minor');
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('total_due_minor');
            $table->unsignedBigInteger('principal_outstanding_minor');
            $table->unsignedBigInteger('interest_outstanding_minor');
            $table->unsignedBigInteger('fees_outstanding_minor')->default(0);
            $table->unsignedBigInteger('total_outstanding_minor');
            $table->string('status')->default('due')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_repayment_schedule_items');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_offer_id');
        });
    }
};
