<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Install protection on databases that ran an earlier payroll migration.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION opfin_payroll_event_immutable()
                RETURNS trigger LANGUAGE plpgsql AS $opfin$
                BEGIN RAISE EXCEPTION 'Payroll deduction event evidence is immutable'; END;
                $opfin$
                SQL);
            DB::unprepared('DROP TRIGGER IF EXISTS payroll_deduction_events_immutable ON payroll_deduction_events');
            DB::unprepared('CREATE TRIGGER payroll_deduction_events_immutable BEFORE UPDATE OR DELETE ON payroll_deduction_events FOR EACH ROW EXECUTE FUNCTION opfin_payroll_event_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = 'payroll_deduction_events_immutable_'.strtolower($operation);
                DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON payroll_deduction_events BEGIN SELECT RAISE(ABORT, 'Payroll deduction event evidence is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        // Rollback must not disable immutable evidence protection from the baseline.
    }
};
