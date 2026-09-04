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
        Schema::table('institutions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alltables', function (Blueprint $table) {
            //
        });
    }
};
