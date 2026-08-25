<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_statement_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->string('record_hash', 64);
            $table->string('provider_reference')->nullable()->index();
            $table->string('internal_reference')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('direction')->index();
            $table->string('provider_status')->index();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['reconciliation_run_id', 'record_hash'], 'provider_statement_run_hash_unique');
            $table->index(['reconciliation_run_id', 'provider_reference'], 'provider_statement_run_reference_idx');
        });

        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->foreignId('provider_statement_record_id')
                ->nullable()
                ->after('mobile_money_transaction_id')
                ->constrained('provider_statement_records')
                ->nullOnDelete();
            $table->string('internal_reference')->nullable()->after('provider_reference')->index();
            $table->string('direction')->nullable()->after('internal_reference');
            $table->string('currency', 3)->nullable()->after('direction');
            $table->string('system_status')->nullable()->after('currency');
            $table->string('provider_status')->nullable()->after('system_status');
            $table->string('exception_type')->nullable()->after('provider_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_statement_record_id');
            $table->dropColumn([
                'internal_reference',
                'direction',
                'currency',
                'system_status',
                'provider_status',
                'exception_type',
            ]);
        });

        Schema::dropIfExists('provider_statement_records');
    }
};
