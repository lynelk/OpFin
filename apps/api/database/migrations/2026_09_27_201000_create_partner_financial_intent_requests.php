<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_financial_intent_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('partner_account_id')->constrained('partner_distribution_accounts')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_platform', 80)->index();
            $table->string('external_reference', 160);
            $table->string('idempotency_key', 180);
            $table->string('need_type', 80)->index();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->json('purpose')->nullable();
            $table->string('customer_consent_reference', 180)->nullable();
            $table->string('status', 48)->default('customer_confirmation_pending')->index();
            $table->foreignId('confirmed_financial_intent_id')->nullable()->constrained('financial_intents')->nullOnDelete();
            $table->foreignId('confirmed_financial_space_id')->nullable()->constrained('financial_spaces')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['partner_account_id', 'external_reference'], 'partner_financial_intent_external_unique');
            $table->unique(['partner_account_id', 'idempotency_key'], 'partner_financial_intent_idempotency_unique');
            $table->index(['customer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_financial_intent_requests');
    }
};
