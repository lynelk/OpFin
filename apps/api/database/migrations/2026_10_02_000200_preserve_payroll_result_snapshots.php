<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_deduction_events', 'instruction_hash')) {
            Schema::table('payroll_deduction_events', fn (Blueprint $table) => $table->string('instruction_hash', 64)->nullable());
        }
        if (! Schema::hasColumn('payroll_deduction_reconciliations', 'submission_attempt')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->unsignedInteger('submission_attempt')->default(1));
        }
        $indexes = collect(Schema::getIndexes('payroll_deduction_reconciliations'))->pluck('name');
        if ($indexes->contains('payroll_deduction_period_unique')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->dropUnique('payroll_deduction_period_unique'));
        }
        if (! $indexes->contains('payroll_deduction_attempt_unique')) {
            Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->unique(
                ['payroll_deduction_case_id', 'payroll_period', 'submission_attempt'], 'payroll_deduction_attempt_unique'));
        }
        Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->json('provider_result_snapshot')->nullable());
        $columns = ['payroll_deduction_case_id', 'payroll_period', 'submission_attempt', 'expected_minor', 'currency', 'result_category', 'provider_reference', 'provider_result_snapshot'];
        if (DB::getDriverName() === 'pgsql') {
            $comparisons = array_map(fn ($column) => 'OLD.'.$column.'::text IS DISTINCT FROM NEW.'.$column.'::text', $columns);
            DB::unprepared("CREATE OR REPLACE FUNCTION opfin_payroll_result_immutable() RETURNS trigger LANGUAGE plpgsql AS \$opfin\$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Payroll result evidence cannot be deleted'; END IF; IF ".implode(' OR ', $comparisons)." THEN RAISE EXCEPTION 'Original payroll result evidence is immutable'; END IF; RETURN NEW; END; \$opfin\$");
            DB::unprepared('CREATE TRIGGER payroll_result_immutable BEFORE UPDATE OR DELETE ON payroll_deduction_reconciliations FOR EACH ROW EXECUTE FUNCTION opfin_payroll_result_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            $comparisons = array_map(fn ($column) => 'OLD.'.$column.' IS NOT NEW.'.$column, $columns);
            DB::unprepared('CREATE TRIGGER payroll_result_immutable_update BEFORE UPDATE ON payroll_deduction_reconciliations WHEN '.implode(' OR ', $comparisons)." BEGIN SELECT RAISE(ABORT, 'Original payroll result evidence is immutable'); END");
            DB::unprepared("CREATE TRIGGER payroll_result_immutable_delete BEFORE DELETE ON payroll_deduction_reconciliations BEGIN SELECT RAISE(ABORT, 'Payroll result evidence cannot be deleted'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable ON payroll_deduction_reconciliations');
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_payroll_result_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_result_immutable_delete');
        }
        Schema::table('payroll_deduction_reconciliations', fn (Blueprint $table) => $table->dropColumn('provider_result_snapshot'));
        // Keep additive compatibility columns and attempt uniqueness; do not collapse history.
    }
};
