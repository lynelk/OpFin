<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER club_distributions_immutable BEFORE UPDATE OR DELETE ON club_distributions FOR EACH ROW EXECUTE FUNCTION opfin_club_immutable()');
            DB::unprepared("CREATE FUNCTION opfin_club_allocation_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Distribution entitlement evidence cannot be deleted'; END IF; IF NEW.distribution_id IS DISTINCT FROM OLD.distribution_id OR NEW.member_id IS DISTINCT FROM OLD.member_id OR NEW.amount_minor IS DISTINCT FROM OLD.amount_minor OR NEW.weight IS DISTINCT FROM OLD.weight OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'Distribution entitlement evidence is immutable'; END IF; IF NEW.paid_minor < 0 OR NEW.paid_minor > NEW.amount_minor THEN RAISE EXCEPTION 'Distribution payment exceeds its entitlement'; END IF; RETURN NEW; END; $$");
            DB::unprepared('CREATE TRIGGER club_allocations_protected BEFORE UPDATE OR DELETE ON club_distribution_allocations FOR EACH ROW EXECUTE FUNCTION opfin_club_allocation_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $suffix = strtolower($operation);
                DB::unprepared("CREATE TRIGGER club_distributions_immutable_{$suffix} BEFORE {$operation} ON club_distributions BEGIN SELECT RAISE(ABORT, 'Distribution declaration is immutable'); END");
            }
            DB::unprepared("CREATE TRIGGER club_allocations_no_delete BEFORE DELETE ON club_distribution_allocations BEGIN SELECT RAISE(ABORT, 'Distribution entitlements cannot be deleted'); END");
            DB::unprepared("CREATE TRIGGER club_allocations_protected BEFORE UPDATE ON club_distribution_allocations WHEN NEW.distribution_id IS NOT OLD.distribution_id OR NEW.member_id IS NOT OLD.member_id OR NEW.amount_minor IS NOT OLD.amount_minor OR NEW.weight IS NOT OLD.weight OR NEW.created_at IS NOT OLD.created_at OR NEW.paid_minor < 0 OR NEW.paid_minor > NEW.amount_minor BEGIN SELECT RAISE(ABORT, 'Distribution entitlement or payment boundary cannot be changed'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS club_distributions_immutable ON club_distributions');
            DB::unprepared('DROP TRIGGER IF EXISTS club_allocations_protected ON club_distribution_allocations');
            DB::unprepared('DROP FUNCTION IF EXISTS opfin_club_allocation_immutable()');
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['club_distributions_immutable_update', 'club_distributions_immutable_delete', 'club_allocations_no_delete', 'club_allocations_protected'] as $trigger) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
            }
        }
    }
};
