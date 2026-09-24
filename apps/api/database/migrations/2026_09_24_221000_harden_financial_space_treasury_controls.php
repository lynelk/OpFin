<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_space_treasury_accounts', function (Blueprint $table) {
            $table->date('current_balance_as_of')->nullable()->after('balance_as_of');
        });

        Schema::table('financial_space_statement_rows', function (Blueprint $table) {
            $table->unique(
                'matched_transaction_id',
                'fst_statement_rows_matched_transaction_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('financial_space_statement_rows', function (Blueprint $table) {
            $table->dropUnique('fst_statement_rows_matched_transaction_unique');
        });

        Schema::table('financial_space_treasury_accounts', function (Blueprint $table) {
            $table->dropColumn('current_balance_as_of');
        });
    }
};
