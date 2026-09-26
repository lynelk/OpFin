<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_client_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('book_id')->constrained('club_books');
            $table->foreignId('user_id')->constrained('users');
            $table->string('purpose', 16);
            $table->string('active_slot', 128)->nullable()->unique();
            $table->string('idempotency_key', 160);
            $table->text('envelope');
            $table->char('content_hash', 64);
            $table->string('status', 16)->default('prepared');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'user_id', 'purpose', 'idempotency_key'], 'club_client_request_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_client_requests');
    }
};
