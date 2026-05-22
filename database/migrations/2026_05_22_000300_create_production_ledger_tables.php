<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->index();
            $table->string('currency', 3)->default('UGX');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('event_type')->index();
            $table->string('currency', 3)->default('UGX');
            $table->morphs('source');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->text('memo')->nullable();
            $table->timestamps();
            $table->index(['ledger_account_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
