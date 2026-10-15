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
        Schema::create('institution_ecl_external_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
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
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            foreach (['institution_ecl_run_items', 'institution_ecl_runs', 'loan_ecl_ownerships', 'institution_ecl_external_snapshots', 'institution_ecl_policies'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new LogicException('Institutional ECL evidence exists; rollback requires a reviewed forward migration.');
                }
            }
        }
        foreach (['institution_ecl_run_items', 'institution_ecl_runs', 'institution_ecl_external_snapshots', 'loan_ecl_ownerships', 'institution_ecl_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
