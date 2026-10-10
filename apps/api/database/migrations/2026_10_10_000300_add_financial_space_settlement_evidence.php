<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_space_action_intents', function (Blueprint $table) {
            $table->string('provider_statement_status', 48)->nullable();
            $table->timestamp('provider_statement_matched_at')->nullable();
            $table->timestamp('book_allocation_approved_at')->nullable();
            $table->string('book_allocation_reference', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('financial_space_action_intents', function (Blueprint $table) {
            $table->dropColumn([
                'provider_statement_status', 'provider_statement_matched_at',
                'book_allocation_approved_at', 'book_allocation_reference',
            ]);
        });
    }
};
