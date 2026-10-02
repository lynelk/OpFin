<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_distribution_accounts', fn (Blueprint $table) => $table->string('financial_intent_source_platform', 80)->nullable());
        // Existing accounts remain unconfigured until their separate authorised review.
    }

    public function down(): void
    {
        Schema::table('partner_distribution_accounts', fn (Blueprint $table) => $table->dropColumn('financial_intent_source_platform'));
    }
};
