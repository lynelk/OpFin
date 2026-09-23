<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained('reconciliation_runs')->restrictOnDelete();
            $table->string('provider');
            $table->string('currency', 3);
            $table->date('business_date');
            $table->bigInteger('collections_minor');
            $table->bigInteger('disbursements_minor');
            $table->unsignedBigInteger('provider_fee_minor')->default(0);
            $table->bigInteger('bank_net_settlement_minor');
            $table->string('bank_reference');
            $table->json('evidence');
            $table->string('evidence_hash', 64);
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->unique(['reconciliation_run_id', 'currency']);
            $table->unique(['provider', 'bank_reference']);
            $table->index(['provider', 'business_date', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settlement_batches');
    }
};
