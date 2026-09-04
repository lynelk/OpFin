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
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->decimal('principal_outstanding', 15, 2)->after('interest');
            $table->decimal('interest_outstanding', 15, 2)->after('principal_outstanding');
            $table->renameColumn('balance', 'total_outstanding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->renameColumn('total_outstanding', 'balance');
            $table->dropColumn('interest_outstanding');
            $table->dropColumn('principal_outstanding');
        });
    }
};
