<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->enum('interest_cycle', ['Daily', 'Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
            $table->enum('repayment_frequency', ['Daily', 'Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
        });
    }

    public function down()
    {
        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->enum('interest_cycle', ['Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
            $table->enum('repayment_frequency', ['Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
        });
    }
};
