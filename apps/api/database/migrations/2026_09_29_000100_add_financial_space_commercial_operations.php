<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('commercial_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('commercial_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('financial_space_type')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('charge_basis')->default('flat');
            $table->bigInteger('flat_amount_minor')->default(0);
            $table->unsignedInteger('rate_bps')->default(0);
            $table->bigInteger('minimum_amount_minor')->nullable();
            $table->bigInteger('maximum_amount_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->string('payer')->default('customer');
            $table->string('status')->default('draft')->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_space_action_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained('commercial_pricing_rules')->nullOnDelete();
            $table->foreignId('mobile_money_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('principal_amount_minor');
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('counterparty_phone')->nullable();
            $table->string('status')->default('pending_approval')->index();
            $table->string('idempotency_key')->unique();
            $table->string('source_type')->nullable();
            $table->string('source_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('financial_space_action_intents');
        Schema::dropIfExists('commercial_pricing_rules');
    }
};
