<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE loan_product_terms ALTER COLUMN interest_cycle TYPE varchar(255) USING interest_cycle::text');
            DB::statement('ALTER TABLE loan_product_terms ALTER COLUMN interest_cycle SET DEFAULT \'Monthly\'');
            DB::statement('ALTER TABLE loan_product_terms DROP CONSTRAINT IF EXISTS loan_product_terms_interest_cycle_check');
            DB::statement('ALTER TABLE loan_product_terms ADD CONSTRAINT loan_product_terms_interest_cycle_check CHECK (interest_cycle IN (\'Daily\', \'Weekly\', \'Monthly\'))');

            DB::statement('ALTER TABLE loan_product_terms ALTER COLUMN repayment_frequency TYPE varchar(255) USING repayment_frequency::text');
            DB::statement('ALTER TABLE loan_product_terms ALTER COLUMN repayment_frequency SET DEFAULT \'Monthly\'');
            DB::statement('ALTER TABLE loan_product_terms DROP CONSTRAINT IF EXISTS loan_product_terms_repayment_frequency_check');
            DB::statement('ALTER TABLE loan_product_terms ADD CONSTRAINT loan_product_terms_repayment_frequency_check CHECK (repayment_frequency IN (\'Daily\', \'Weekly\', \'Monthly\'))');

            return;
        }

        Schema::table('loan_product_terms', function (Blueprint $table) {
            $table->enum('interest_cycle', ['Daily', 'Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
            $table->enum('repayment_frequency', ['Daily', 'Weekly', 'Monthly'])
                ->default('Monthly')
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE loan_product_terms DROP CONSTRAINT IF EXISTS loan_product_terms_interest_cycle_check');
            DB::statement('ALTER TABLE loan_product_terms ADD CONSTRAINT loan_product_terms_interest_cycle_check CHECK (interest_cycle IN (\'Weekly\', \'Monthly\'))');
            DB::statement('ALTER TABLE loan_product_terms DROP CONSTRAINT IF EXISTS loan_product_terms_repayment_frequency_check');
            DB::statement('ALTER TABLE loan_product_terms ADD CONSTRAINT loan_product_terms_repayment_frequency_check CHECK (repayment_frequency IN (\'Weekly\', \'Monthly\'))');

            return;
        }

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
