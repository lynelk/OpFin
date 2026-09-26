<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essentials_collection_instructions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repayment_id')->unique()->constrained('essentials_repayments');
            $table->foreignId('advance_id')->constrained('essentials_advances');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('wallet_id')->nullable()->constrained('customer_wallets')->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('environment', 16);
            $table->string('merchant_scope', 64);
            $table->char('instruction_hash', 64);
            $table->char('route_hash', 64);
            $table->text('snapshot');
            $table->string('status', 32)->default('prepared');
            $table->string('provider_reference', 160)->nullable();
            $table->string('exception_code', 80)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['environment', 'merchant_scope', 'provider_reference'], 'ess_coll_provider_reference_once');
            $table->index(['advance_id', 'status']);
        });
        Schema::create('essentials_collection_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instruction_id')->constrained('essentials_collection_instructions');
            $table->char('evidence_hash', 64);
            $table->json('evidence');
            $table->timestamp('observed_at');
            $table->unique(['instruction_id', 'evidence_hash'], 'ess_coll_observation_once');
        });
        Schema::create('essentials_collection_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instruction_id')->constrained('essentials_collection_instructions');
            $table->foreignId('schedule_item_id')->constrained('essentials_repayment_schedule_items');
            $table->bigInteger('principal_minor');
            $table->bigInteger('interest_minor');
            $table->bigInteger('fees_minor');
            $table->string('policy_version', 64);
            $table->timestamp('created_at');
            $table->unique(['instruction_id', 'schedule_item_id'], 'ess_coll_allocation_once');
        });
        Schema::create('essentials_servicing_journals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('advance_id')->constrained('essentials_advances');
            $table->foreignId('repayment_id')->constrained('essentials_repayments');
            $table->foreignId('lender_partner_id')->constrained('partners');
            $table->string('event_key', 160)->unique();
            $table->string('event_type', 48);
            $table->char('currency', 3);
            $table->bigInteger('debits_minor');
            $table->bigInteger('credits_minor');
            $table->json('entries');
            $table->char('content_hash', 64);
            $table->json('evidence');
            $table->timestamp('created_at');
        });
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX ess_coll_one_inflight ON essentials_collection_instructions (advance_id) WHERE status IN ('prepared', 'submitting', 'pending', 'confirmed_unapplied')");
        }
        $tables = ['essentials_collection_observations', 'essentials_collection_allocations', 'essentials_servicing_journals'];
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION opfin_collection_evidence_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Collection evidence is immutable; append a correcting event' USING ERRCODE = '23514'; END; $$");
            foreach ($tables as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION opfin_collection_evidence_immutable()");
            }
            DB::statement('ALTER TABLE essentials_servicing_journals ADD CONSTRAINT ess_servicing_balanced CHECK (debits_minor > 0 AND debits_minor = credits_minor)');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ($tables as $table) {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    $name = $table.'_immutable_'.strtolower($operation);
                    DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} BEGIN SELECT RAISE(ABORT, 'Collection evidence is immutable'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['essentials_servicing_journals', 'essentials_collection_allocations', 'essentials_collection_observations', 'essentials_collection_instructions'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_collection_evidence_immutable()');
        }
    }
};
