<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_deduction_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('financing_application_id')->nullable()->unique()->constrained('financing_applications')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('scheme', 80)->default('government_pdms')->index();
            $table->string('provider', 80)->default('pdms')->index();
            $table->string('vote_code', 80)->nullable()->index();
            $table->string('vote_name', 180)->nullable();
            $table->string('employment_reference_hash', 64)->nullable()->index();
            $table->string('status', 48)->default('draft')->index();
            $table->string('affordability_status', 32)->default('not_checked')->index();
            $table->bigInteger('affordable_amount_minor')->nullable();
            $table->bigInteger('requested_deduction_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->string('provider_agreement_reference', 180)->nullable()->index();
            $table->string('reservation_reference', 180)->nullable()->index();
            $table->timestamp('reservation_expires_at')->nullable()->index();
            $table->foreignId('undertaking_consent_record_id')->nullable()->constrained('consent_records')->nullOnDelete();
            $table->json('key_facts_snapshot')->nullable();
            $table->json('provider_state')->nullable();
            $table->string('last_provider_reference', 180)->nullable();
            $table->string('rejection_code', 120)->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('payroll_submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['financial_space_id', 'status']);
        });

        Schema::create('payroll_deduction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_deduction_case_id')->constrained('payroll_deduction_cases')->cascadeOnDelete();
            $table->uuid('correlation_id')->index();
            $table->string('idempotency_key', 180);
            $table->char('instruction_hash', 64)->index();
            $table->string('event_type', 100)->index();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 40)->default('api');
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['payroll_deduction_case_id', 'idempotency_key'], 'payroll_deduction_event_idempotency_unique');
        });

        Schema::create('payroll_deduction_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_deduction_case_id')->constrained('payroll_deduction_cases')->cascadeOnDelete();
            $table->string('payroll_period', 7);
            $table->unsignedInteger('submission_attempt')->default(1);
            $table->bigInteger('expected_minor');
            $table->bigInteger('recovered_minor')->default(0);
            $table->bigInteger('variance_minor')->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('status', 40)->default('pending')->index();
            $table->string('result_category', 80)->nullable()->index();
            $table->string('provider_reference', 180)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_deduction_case_id', 'payroll_period', 'submission_attempt'], 'payroll_deduction_period_attempt_unique');
        });

        $this->protectEventEvidence();
    }

    private function protectEventEvidence(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE OR REPLACE FUNCTION opfin_payroll_event_immutable() RETURNS trigger LANGUAGE plpgsql AS $opfin$ BEGIN RAISE EXCEPTION \'Payroll deduction event evidence is immutable\'; END; $opfin$');
            DB::unprepared('CREATE TRIGGER payroll_deduction_events_immutable BEFORE UPDATE OR DELETE ON payroll_deduction_events FOR EACH ROW EXECUTE FUNCTION opfin_payroll_event_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER payroll_deduction_events_immutable_update BEFORE UPDATE ON payroll_deduction_events BEGIN SELECT RAISE(ABORT, 'Payroll deduction event evidence is immutable'); END");
            DB::unprepared("CREATE TRIGGER payroll_deduction_events_immutable_delete BEFORE DELETE ON payroll_deduction_events BEGIN SELECT RAISE(ABORT, 'Payroll deduction event evidence cannot be deleted'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable ON payroll_deduction_events');
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_payroll_event_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable_delete');
        }

        Schema::dropIfExists('payroll_deduction_reconciliations');
        Schema::dropIfExists('payroll_deduction_events');
        Schema::dropIfExists('payroll_deduction_cases');
    }
};
