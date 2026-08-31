<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('otp');
            $table->timestamp('verified_at')->nullable()->after('expires_at');
            $table->string('verification_token_hash', 64)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'verified_at', 'verification_token_hash']);
        });
    }
};
