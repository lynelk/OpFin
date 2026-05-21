<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->string('phone');
            $table->string('idempotency_key')->unique();
            $table->string('internal_reference')->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('reconciliation_status')->default('unreconciled')->index();
            $table->text('failure_reason')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('webhook_event_id')->nullable()->unique();
            $table->timestamp('webhook_received_at')->nullable();
            $table->timestamp('last_status_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['provider', 'provider_reference']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_transactions');
    }
};
