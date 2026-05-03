<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_product_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained();
            $table->decimal('interest_rate')->default(12);
            $table->enum('interest_type', ['Flat', 'Amortization'])->default('Flat');
            $table->enum('interest_cycle', ['Weekly', 'Monthly'])->default('Monthly');
            $table->enum('repayment_frequency', ['Weekly', 'Monthly'])->default('Monthly');
            $table->integer('duration')->default(30);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_terms');
    }
};
