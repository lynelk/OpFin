<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->string('product_accounting_status')->default('pending')->index();
            $table->timestamp('product_finality_applied_at')->nullable()->index();
            $table->timestamp('provider_reconciled_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'product_accounting_status',
                'product_finality_applied_at',
                'provider_reconciled_at',
            ]);
        });
    }
};
