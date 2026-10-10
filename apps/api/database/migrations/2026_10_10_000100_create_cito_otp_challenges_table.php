<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cito_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->unique();
            $table->string('purpose', 64);
            $table->string('channel', 12)->default('SMS');
            $table->string('environment', 16);
            $table->string('idempotency_key', 128)->unique();
            $table->string('challenge_reference', 128)->nullable();
            $table->string('status', 32)->default('created');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cito_otp_challenges');
    }
};
