<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpay_webhook_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_id', 128);
            $table->string('callback_task_id', 128);
            $table->string('nonce', 255);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['merchant_id', 'nonce']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpay_webhook_nonces');
    }
};
