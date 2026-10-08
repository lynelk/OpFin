<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_finance_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_space_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_passport_id')->constrained('asset_passports')->restrictOnDelete();
            $table->foreignId('supplier_profile_id')->constrained('supplier_profiles')->restrictOnDelete();
            $table->foreignId('financial_product_id')->constrained('financial_products')->restrictOnDelete();
            $table->foreignId('capital_mandate_id')->constrained('capital_mandates')->restrictOnDelete();
            $table->foreignId('financing_arrangement_id')->nullable()->constrained('financing_arrangements')->restrictOnDelete();
            $table->string('vertical', 24)->index();
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('asset_price_minor');
            $table->bigInteger('customer_contribution_minor')->default(0);
            $table->bigInteger('requested_finance_minor');
            $table->bigInteger('approved_finance_minor')->nullable();
            $table->unsignedInteger('term_months');
            $table->string('status', 40)->default('submitted')->index();
            $table->json('policy_snapshot')->nullable();
            $table->string('idempotency_key', 160);
            $table->char('instruction_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_space_id', 'idempotency_key']);
            $table->index(['asset_passport_id', 'status']);
            $table->index(['capital_mandate_id', 'status']);
        });

        Schema::create('asset_finance_case_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_finance_case_id')->constrained('asset_finance_cases')->cascadeOnDelete();
            $table->string('evidence_type', 48);
            $table->string('evidence_reference', 200);
            $table->json('metadata')->nullable();
            $table->string('status', 20)->default('submitted')->index();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['asset_finance_case_id', 'evidence_type', 'evidence_reference'], 'asset_finance_evidence_unique');
        });

        Schema::create('asset_finance_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('asset_finance_case_id')->constrained('asset_finance_cases')->restrictOnDelete();
            $table->string('settlement_type', 40);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('pending_provider_confirmation')->index();
            $table->string('provider_reference', 200)->nullable();
            $table->string('reconciliation_reference', 200)->nullable();
            $table->string('idempotency_key', 160);
            $table->char('instruction_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['asset_finance_case_id', 'idempotency_key']);
        });

        Schema::create('asset_finance_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_finance_case_id')->constrained('asset_finance_cases')->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160);
            $table->string('reason', 240)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['asset_finance_case_id', 'idempotency_key']);
        });

        $this->appendOnly('asset_finance_case_events');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS asset_finance_case_events_immutable ON asset_finance_case_events');
            DB::unprepared('DROP FUNCTION IF EXISTS asset_finance_case_events_immutable()');
        }

        Schema::dropIfExists('asset_finance_case_events');
        Schema::dropIfExists('asset_finance_settlements');
        Schema::dropIfExists('asset_finance_case_evidence');
        Schema::dropIfExists('asset_finance_cases');
    }

    private function appendOnly(string $table): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION {$table}_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Asset finance history is append-only'; END; $$");
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_immutable()");
        } elseif ($driver === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $verb) {
                DB::unprepared("CREATE TRIGGER {$table}_no_".strtolower($verb)." BEFORE {$verb} ON {$table} BEGIN SELECT RAISE(ABORT, 'Asset finance history is append-only'); END");
            }
        }
    }
};
