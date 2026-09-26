<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_statements', function (Blueprint $table): void {
            $table->string('idempotency_key', 160)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique(['book_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('club_statements', function (Blueprint $table): void {
            $table->dropUnique(['book_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
