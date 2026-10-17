<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('institution_finance_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->foreignId('ecl_run_id')->constrained('institution_ecl_runs')->restrictOnDelete();
            $t->date('as_of_date');
            $t->string('currency', 3);
            $t->json('provider_statements');
            $t->unsignedBigInteger('external_payable_minor');
            $t->unsignedBigInteger('external_principal_minor');
            $t->unsignedInteger('external_loan_count');
            $t->string('lender_ledger_reference', 255);
            $t->char('lender_ledger_sha256', 64);
            $t->json('control_results');
            $t->char('content_hash', 64);
            $t->string('status', 48)->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['institution_id', 'as_of_date', 'currency', 'ecl_run_id'], 'finance_cut_unique');
        });

        Schema::create('institution_finance_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reconciliation_id')->constrained('institution_finance_reconciliations')->restrictOnDelete();
            $t->string('capacity', 35);
            $t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $t->char('report_sha256', 64);
            $t->string('report_reference', 255);
            $t->string('signed_statement', 255);
            $t->timestamp('reviewed_at');
            $t->timestamps();
            $t->unique(['reconciliation_id', 'capacity'], 'finance_capacity_unique');
            $t->unique(['reconciliation_id', 'reviewed_by'], 'finance_distinct_reviewers');
        });

        Schema::create('institution_shared_classifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_offer_id')->unique()->constrained('credit_offers')->restrictOnDelete();
            $t->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $t->foreignId('reconciliation_id')->constrained('institution_finance_reconciliations')->restrictOnDelete();
            $t->string('contract_form', 40);
            $t->unsignedSmallInteger('opfin_share_bps');
            $t->string('contract_reference', 255);
            $t->char('signed_contract_sha256', 64);
            $t->string('accounting_policy_reference', 255);
            $t->char('technical_memo_sha256', 64);
            $t->string('status', 64)->default('proposed_pending_legal_review');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'institution_finance_reconciliations' => ['institution_id','ecl_run_id','as_of_date','currency','provider_statements','external_payable_minor','external_principal_minor','external_loan_count','lender_ledger_reference','lender_ledger_sha256','control_results','content_hash','created_by'],
                'institution_finance_reviews' => ['reconciliation_id','capacity','reviewed_by','report_sha256','report_reference','signed_statement','reviewed_at'],
                'institution_shared_classifications' => ['credit_offer_id','institution_id','reconciliation_id','contract_form','opfin_share_bps','contract_reference','signed_contract_sha256','accounting_policy_reference','technical_memo_sha256','created_by'],
            ] as $table => $fields) {
                $checks = implode(' OR ', array_map(fn ($field) => "OLD.{$field} IS DISTINCT FROM NEW.{$field}", $fields));
                DB::unprepared("CREATE OR REPLACE FUNCTION {$table}_guard_original() RETURNS trigger LANGUAGE plpgsql AS \$ecl\$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Institutional assurance records are append-only'; END IF; IF {$checks} THEN RAISE EXCEPTION 'Institutional assurance source and approvals are immutable'; END IF; RETURN NEW; END; \$ecl\$");
                DB::unprepared("CREATE TRIGGER {$table}_guard_original BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_guard_original()");
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach ([
                'institution_finance_reconciliations' => ['institution_id','ecl_run_id','as_of_date','currency','provider_statements','external_payable_minor','external_principal_minor','external_loan_count','lender_ledger_reference','lender_ledger_sha256','control_results','content_hash','created_by'],
                'institution_finance_reviews' => ['reconciliation_id','capacity','reviewed_by','report_sha256','report_reference','signed_statement','reviewed_at'],
                'institution_shared_classifications' => ['credit_offer_id','institution_id','reconciliation_id','contract_form','opfin_share_bps','contract_reference','signed_contract_sha256','accounting_policy_reference','technical_memo_sha256','created_by'],
            ] as $table => $fields) {
                $checks = implode(' OR ', array_map(fn ($field) => "OLD.{$field} IS NOT NEW.{$field}", $fields));
                DB::unprepared("CREATE TRIGGER {$table}_guard_original BEFORE UPDATE ON {$table} WHEN {$checks} BEGIN SELECT RAISE(ABORT,'Institutional assurance records are immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_guard_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT,'Institutional assurance records are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        foreach (['institution_shared_classifications','institution_finance_reviews','institution_finance_reconciliations'] as $table) {
            if (! app()->environment('testing') && Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('Signed financial assurance evidence cannot be destroyed by rollback.');
            }
        }
        foreach (['institution_shared_classifications','institution_finance_reviews','institution_finance_reconciliations'] as $table) {
            Schema::dropIfExists($table);
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("DROP FUNCTION IF EXISTS {$table}_guard_original()");
            }
        }
    }
};
