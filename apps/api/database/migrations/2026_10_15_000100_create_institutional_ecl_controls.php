<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_ecl_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->string('version', 80);
            $t->date('effective_from');
            $t->string('currency', 3);
            $t->unsignedSmallInteger('stage2_dpd')->default(30);
            $t->unsignedSmallInteger('stage3_dpd')->default(90);
            $t->json('scenarios');
            $t->unsignedSmallInteger('discount_bps')->default(0);
            $t->string('methodology_reference', 255);
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['institution_id', 'version']);
        });
        // Credit-risk ownership must be independently signed BEFORE provider disbursement.
        Schema::create('credit_offer_ecl_ownerships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_offer_id')->unique()->constrained('credit_offers')->restrictOnDelete();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->string('owner_type', 20);
            $t->unsignedSmallInteger('opfin_share_bps');
            $t->string('agreement_reference', 255);
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('loan_ecl_ownerships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->unique()->constrained('loans')->restrictOnDelete();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->string('owner_type', 20); // opfin, partner, shared
            $t->unsignedSmallInteger('opfin_share_bps');
            $t->string('agreement_reference', 255);
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('institution_ecl_import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->date('as_of_date');
            $t->string('currency', 3);
            $t->string('source_evidence', 255);
            $t->char('file_hash', 64);
            $t->unsignedInteger('loan_count');
            $t->unsignedBigInteger('principal_total_minor');
            $t->string('status', 20)->default('staged');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['institution_id', 'as_of_date', 'file_hash'], 'ecl_batch_source_unique');
        });
        Schema::create('institution_ecl_external_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->foreignId('import_batch_id')->nullable()->constrained('institution_ecl_import_batches')->restrictOnDelete();
            $t->string('external_loan_reference', 160);
            $t->date('as_of_date');
            $t->string('currency', 3);
            $t->unsignedBigInteger('principal_outstanding_minor');
            $t->unsignedSmallInteger('days_past_due');
            $t->boolean('sicr')->default(false);
            $t->boolean('credit_impaired')->default(false);
            $t->date('contractual_maturity_date');
            $t->char('source_hash', 64);
            $t->string('source_evidence', 255);
            $t->string('status', 20)->default('staged');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['institution_id', 'external_loan_reference', 'as_of_date'], 'ecl_external_source_unique');
        });
        Schema::create('institution_ecl_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->foreignId('policy_id')->constrained('institution_ecl_policies')->restrictOnDelete();
            $t->date('as_of_date');
            $t->unsignedSmallInteger('revision')->default(1);
            $t->string('currency', 3);
            $t->string('status', 20)->default('draft');
            $t->unsignedBigInteger('total_exposure_minor');
            $t->unsignedBigInteger('total_ecl_minor');
            $t->unsignedBigInteger('platform_ecl_minor');
            $t->bigInteger('ecl_movement_minor');
            $t->unsignedInteger('expected_external_count');
            $t->unsignedBigInteger('expected_external_principal_minor');
            $t->string('coverage_reference', 255);
            $t->char('content_hash', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->unique(['institution_id', 'as_of_date', 'revision'], 'institution_month_revision_unique');
        });
        Schema::create('institution_ecl_run_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('run_id')->constrained('institution_ecl_runs')->restrictOnDelete();
            $t->foreignId('loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $t->foreignId('external_snapshot_id')->nullable()->constrained('institution_ecl_external_snapshots')->restrictOnDelete();
            $t->string('loan_reference', 160);
            $t->string('owner_type', 20);
            $t->unsignedSmallInteger('opfin_share_bps');
            $t->unsignedBigInteger('gross_exposure_minor');
            $t->unsignedSmallInteger('days_past_due');
            $t->string('stage', 10);
            $t->unsignedBigInteger('expected_credit_loss_minor');
            $t->unsignedBigInteger('platform_ecl_minor');
            $t->bigInteger('movement_minor');
            $t->json('calculation_evidence');
            $t->foreignId('impairment_assessment_id')->nullable()->constrained('loan_impairment_assessments')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['run_id', 'loan_reference']);
        });
        $this->protectEvidence();
    }

    /**
     * Keep financial source and calculation facts immutable, while allowing explicitly
     * reviewed state changes (draft -> approved -> posted/finalised).
     */
    private function protectEvidence(): void
    {
        $protected = [
            'institution_ecl_policies' => [
                'institution_id', 'version', 'effective_from', 'currency', 'stage2_dpd',
                'stage3_dpd', 'scenarios', 'discount_bps', 'methodology_reference', 'created_by',
            ],
            'credit_offer_ecl_ownerships' => [
                'credit_offer_id', 'institution_id', 'owner_type', 'opfin_share_bps',
                'agreement_reference', 'created_by',
            ],
            'loan_ecl_ownerships' => [
                'loan_id', 'institution_id', 'owner_type', 'opfin_share_bps',
                'agreement_reference', 'created_by',
            ],
            'institution_ecl_import_batches' => [
                'institution_id', 'as_of_date', 'currency', 'source_evidence',
                'file_hash', 'loan_count', 'principal_total_minor', 'submitted_by',
            ],
            'institution_ecl_external_snapshots' => [
                'institution_id', 'import_batch_id', 'external_loan_reference', 'as_of_date', 'currency',
                'principal_outstanding_minor', 'days_past_due', 'sicr',
                'credit_impaired', 'contractual_maturity_date', 'source_hash',
                'source_evidence', 'submitted_by',
            ],
            'institution_ecl_runs' => [
                'institution_id', 'policy_id', 'as_of_date', 'revision', 'currency',
                'total_exposure_minor', 'total_ecl_minor', 'platform_ecl_minor',
                'ecl_movement_minor', 'content_hash', 'expected_external_count',
                'expected_external_principal_minor', 'coverage_reference', 'created_by',
            ],
            'institution_ecl_run_items' => [
                'run_id', 'loan_id', 'external_snapshot_id', 'loan_reference',
                'owner_type', 'opfin_share_bps', 'gross_exposure_minor',
                'days_past_due', 'stage', 'expected_credit_loss_minor',
                'platform_ecl_minor', 'movement_minor', 'calculation_evidence',
            ],
        ];
        $driver = DB::getDriverName();
        foreach ($protected as $table => $columns) {
            if ($driver === 'pgsql') {
                $changed = implode(' OR ', array_map(
                    fn (string $column) => in_array($column, ['scenarios', 'calculation_evidence'], true)
                        ? "OLD.{$column}::text IS DISTINCT FROM NEW.{$column}::text"
                        : "OLD.{$column} IS DISTINCT FROM NEW.{$column}", $columns
                ));
                $tag = chr(36).chr(36);
                DB::unprepared("CREATE FUNCTION {$table}_lock_ecl_source() RETURNS trigger LANGUAGE plpgsql AS {$tag} BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Institutional ECL evidence cannot be deleted'; END IF; IF {$changed} THEN RAISE EXCEPTION 'Institutional ECL source fields are immutable'; END IF; RETURN NEW; END; {$tag}");
                DB::unprepared("CREATE TRIGGER {$table}_lock_ecl_source BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_lock_ecl_source()");
            } elseif ($driver === 'sqlite') {
                $changed = implode(' OR ', array_map(
                    fn (string $column) => "OLD.{$column} IS NOT NEW.{$column}", $columns
                ));
                DB::unprepared("CREATE TRIGGER {$table}_ecl_update BEFORE UPDATE ON {$table} WHEN {$changed} BEGIN SELECT RAISE(ABORT, 'Institutional ECL source fields are immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_ecl_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Institutional ECL evidence cannot be deleted'); END");
            }
        }
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            foreach (['institution_ecl_run_items', 'institution_ecl_runs', 'institution_ecl_external_snapshots', 'institution_ecl_import_batches', 'loan_ecl_ownerships', 'credit_offer_ecl_ownerships', 'institution_ecl_policies'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new LogicException('Institutional ECL evidence exists; rollback requires a reviewed forward migration.');
                }
            }
        }
        foreach (['institution_ecl_run_items', 'institution_ecl_runs', 'institution_ecl_external_snapshots', 'institution_ecl_import_batches', 'loan_ecl_ownerships', 'credit_offer_ecl_ownerships', 'institution_ecl_policies'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            foreach (['institution_ecl_run_items', 'institution_ecl_runs', 'institution_ecl_external_snapshots', 'institution_ecl_import_batches', 'loan_ecl_ownerships', 'credit_offer_ecl_ownerships', 'institution_ecl_policies'] as $table) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$table}_lock_ecl_source()");
            }
        }
    }
};
