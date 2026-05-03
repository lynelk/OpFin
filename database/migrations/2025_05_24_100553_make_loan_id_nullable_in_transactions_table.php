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
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);

            // Change the column to nullable
            $table->unsignedBigInteger('loan_id')->nullable()->change();

            // Re-add the foreign key constraint if needed
            $table->foreign('loan_id')->references('id')->on('loans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['loan_id']);

            // Change back to not nullable
            $table->unsignedBigInteger('loan_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('loan_id')->references('id')->on('loans');
        });
    }
};
