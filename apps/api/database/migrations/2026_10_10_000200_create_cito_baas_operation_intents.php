<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cito_baas_operation_intents', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 16);
            $table->string('operation', 32);
            $table->string('billing_account_reference', 128);
            $table->string('idempotency_key', 128)->unique();
            $table->string('instruction_hash', 64);
            $table->json('request_payload');
            $table->foreignId('maker_user_id')->constrained('users');
            $table->foreignId('checker_user_id')->nullable()->constrained('users');
            $table->string('status', 36)->default('pending_approval')->index();
            $table->string('provider_reference', 128)->nullable();
            $table->string('provider_status', 48)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['environment', 'billing_account_reference', 'status'], 'baas_environment_account_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cito_baas_operation_intents');
    }
};
