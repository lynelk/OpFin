<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_space_payout_mandates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 16);
            $table->char('currency', 3);
            $table->string('custody_agreement_reference', 128);
            $table->string('segregated_settlement_account_reference', 128);
            $table->string('document_sha256', 64);
            $table->foreignId('maker_user_id')->constrained('users');
            $table->foreignId('checker_user_id')->nullable()->constrained('users');
            $table->string('status', 24)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['financial_space_id', 'environment', 'currency', 'status'], 'space_payout_mandate_lookup');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('financial_space_payout_mandates');
    }
};
