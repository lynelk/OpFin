<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider')->default('manual');
            $table->string('provider_reference')->nullable()->index();
            $table->string('national_id')->index();
            $table->string('status')->default('pending_review')->index();
            $table->json('evidence')->nullable();
            $table->json('risk_flags')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose')->index();
            $table->string('policy_version');
            $table->string('status')->default('granted')->index();
            $table->string('channel')->default('api');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('kyc_cases');
    }
};
