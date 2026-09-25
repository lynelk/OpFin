<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verification_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kyc_case_id')->constrained('kyc_cases')->cascadeOnDelete();
            $table->foreignId('consent_record_id')->constrained('consent_records')->cascadeOnDelete();
            $table->char('lookup_hash', 64)->index();
            $table->char('receipt_key', 64)->unique();
            $table->string('policy_version', 64);
            $table->string('provider', 40);
            $table->string('environment', 32);
            $table->text('provider_reference');
            $table->string('status', 16);
            $table->text('result');
            $table->timestamp('verified_at');
            $table->timestamp('refresh_after');
            $table->timestamp('expires_at')->index();
            $table->timestamp('retention_until')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consent_record_id', 'lookup_hash'], 'identity_receipt_subject_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_receipts');
    }
};
