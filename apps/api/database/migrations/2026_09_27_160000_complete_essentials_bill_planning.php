<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essentials_bill_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained('financial_spaces');
            $table->foreignId('essentials_account_id')->constrained('essentials_accounts');
            $table->unsignedBigInteger('expected_amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('frequency', 20);
            $table->date('next_due_date')->index();
            $table->boolean('necessary')->default(true);
            $table->unsignedTinyInteger('reminder_days_before')->default(3);
            $table->boolean('active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'active', 'next_due_date']);
            $table->index(['financial_space_id', 'active']);
        });

        Schema::create('essentials_affordability_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained('financial_spaces');
            $table->foreignId('essentials_account_id')->constrained('essentials_accounts');
            $table->foreignId('bill_plan_id')->nullable()->constrained('essentials_bill_plans')->nullOnDelete();
            $table->unsignedBigInteger('bill_amount_minor');
            $table->date('bill_due_date');
            $table->date('horizon_end');
            $table->char('currency', 3)->default('UGX');
            $table->bigInteger('recorded_available_minor')->nullable();
            $table->unsignedBigInteger('own_money_capacity_minor')->default(0);
            $table->unsignedBigInteger('financing_gap_minor')->default(0);
            $table->unsignedBigInteger('projected_income_minor')->default(0);
            $table->unsignedBigInteger('scheduled_outflows_minor')->default(0);
            $table->unsignedBigInteger('proposed_repayment_minor')->default(0);
            $table->bigInteger('minimum_projected_balance_minor')->nullable();
            $table->string('classification', 40)->index();
            $table->json('snapshot');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'essentials_account_id', 'created_at'], 'ess_afford_user_account');
        });

        Schema::create('essentials_own_money_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained('financial_spaces');
            $table->foreignId('essentials_account_id')->constrained('essentials_accounts');
            $table->foreignId('bill_plan_id')->nullable()->constrained('essentials_bill_plans')->nullOnDelete();
            $table->foreignId('affordability_assessment_id')->nullable()->constrained('essentials_affordability_assessments')->nullOnDelete();
            $table->foreignId('wallet_id')->constrained('customer_wallets');
            $table->foreignId('collection_transaction_id')->nullable()->unique()->constrained('mobile_money_transactions')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('prepared')->index();
            $table->string('idempotency_key', 160)->unique();
            $table->string('provider_reference', 160)->nullable()->index();
            $table->json('evidence')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('essentials_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('own_money_payment_id')->constrained('essentials_own_money_payments');
            $table->string('event_key', 180)->unique();
            $table->string('event_type', 60)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->json('evidence');
            $table->char('content_hash', 64);
            $table->timestamp('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION opfin_essentials_payment_event_immutable()
                RETURNS trigger LANGUAGE plpgsql AS $
                BEGIN
                    RAISE EXCEPTION 'Essentials payment evidence is immutable; append a correcting event'
                        USING ERRCODE = '23514';
                END; $
                SQL);
            DB::unprepared(
                'CREATE TRIGGER essentials_payment_events_immutable
                BEFORE UPDATE OR DELETE ON essentials_payment_events
                FOR EACH ROW EXECUTE FUNCTION opfin_essentials_payment_event_immutable()'
            );
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = 'essentials_payment_events_immutable_'.strtolower($operation);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON essentials_payment_events BEGIN SELECT RAISE(ABORT, 'Essentials payment evidence is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('essentials_payment_events');
        Schema::dropIfExists('essentials_own_money_payments');
        Schema::dropIfExists('essentials_affordability_assessments');
        Schema::dropIfExists('essentials_bill_plans');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(
                'DROP FUNCTION IF EXISTS opfin_essentials_payment_event_immutable()'
            );
        }
    }
};
