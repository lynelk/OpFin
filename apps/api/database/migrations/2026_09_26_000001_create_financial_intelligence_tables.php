<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fi_sources', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->string('name', 80);
            $t->string('population', 120);
            $t->string('country', 2);
            $t->string('lawful_basis_reference', 255);
            $t->string('status', 24)->default('active');
            $t->unsignedBigInteger('current_import_id')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['financial_space_id', 'name', 'population']);
        });
        Schema::create('fi_grants', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('role', 24);
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['financial_space_id', 'user_id']);
        });
        Schema::create('fi_imports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('source_id')->constrained('fi_sources')->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 120);
            $t->char('instruction_hash', 64);
            $t->char('source_hash', 64);
            $t->string('engine_version', 24);
            $t->date('as_of');
            $t->unsignedInteger('row_count');
            $t->longText('payload_cipher');
            $t->longText('analysis_cipher');
            $t->string('status', 24)->default('staged');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_reason_cipher')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['source_id', 'idempotency_key']);
            $t->index(['financial_space_id', 'source_id', 'as_of']);
        });
        Schema::create('fi_publications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('source_id')->constrained('fi_sources')->restrictOnDelete();
            $t->foreignId('import_id')->unique()->constrained('fi_imports')->restrictOnDelete();
            $t->unsignedBigInteger('previous_import_id')->nullable();
            $t->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('fi_cases', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('source_id')->constrained('fi_sources')->restrictOnDelete();
            $t->foreignId('import_id')->constrained('fi_imports')->restrictOnDelete();
            $t->char('fingerprint', 64);
            $t->string('signal', 40);
            $t->string('status', 24)->default('open');
            $t->string('priority', 16);
            $t->longText('evidence_cipher');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->date('due_on')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['source_id', 'fingerprint']);
            $t->index(['financial_space_id', 'status', 'assigned_to']);
        });
        Schema::create('fi_case_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('case_id')->constrained('fi_cases')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 120);
            $t->char('instruction_hash', 64);
            $t->string('action', 24);
            $t->unsignedInteger('result_version');
            $t->longText('event_cipher');
            $t->timestamp('created_at');
            $t->unique(['case_id', 'idempotency_key']);
        });
        Schema::create('fi_reports', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('import_id')->constrained('fi_imports')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->char('content_hash', 64);
            $t->longText('report_cipher');
            $t->timestamp('created_at');
        });
        Schema::create('fi_issuer_versions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('issuer_code', 80);
            $t->string('legal_name');
            $t->string('country', 2);
            $t->string('product_type', 40);
            $t->string('regulator', 120);
            $t->string('licence_reference', 120);
            $t->string('evidence_reference', 255);
            $t->date('valid_from');
            $t->date('valid_until');
            $t->date('review_due_on');
            $t->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['country', 'issuer_code', 'approved_at']);
        });
        Schema::create('fi_statements', function (Blueprint $t): void {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('issuer_version_id')->constrained('fi_issuer_versions')->restrictOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 120);
            $t->char('instruction_hash', 64);
            $t->char('file_hash', 64);
            $t->string('storage_path', 255);
            $t->string('media_type', 80);
            $t->unsignedInteger('bytes');
            $t->string('status', 40);
            $t->date('period_start');
            $t->date('period_end');
            $t->timestamp('authority_expires_at');
            $t->longText('authority_cipher');
            $t->longText('analysis_cipher')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['financial_space_id', 'idempotency_key']);
        });
        Schema::create('fi_network_grants', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('recipient_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('report_id')->constrained('fi_reports')->restrictOnDelete();
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->unique(['report_id', 'recipient_space_id']);
        });
        // No issuer, subscription, live integration or historical record is fabricated here.
        $this->protectEvidence();
    }

    private function protectEvidence(): void
    {
        $driver = DB::getDriverName();
        foreach (['fi_publications', 'fi_case_events', 'fi_reports'] as $table) {
            if ($driver === 'pgsql') {
                DB::unprepared("CREATE FUNCTION {$table}_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Financial intelligence evidence is append-only'; END; $$");
                DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_immutable()");
            } elseif ($driver === 'sqlite') {
                foreach (['UPDATE', 'DELETE'] as $verb) {
                    DB::unprepared("CREATE TRIGGER {$table}_no_".strtolower($verb)." BEFORE {$verb} ON {$table} BEGIN SELECT RAISE(ABORT, 'Financial intelligence evidence is append-only'); END");
                }
            }
        }
        $protected = [
            'fi_sources' => ['public_id', 'financial_space_id', 'name', 'population', 'country', 'lawful_basis_reference', 'created_by', 'created_at'],
            'fi_imports' => ['public_id', 'financial_space_id', 'source_id', 'submitted_by', 'idempotency_key', 'instruction_hash', 'source_hash', 'engine_version', 'as_of', 'row_count', 'payload_cipher', 'analysis_cipher', 'created_at'],
            'fi_issuer_versions' => ['public_id', 'issuer_code', 'legal_name', 'country', 'product_type', 'regulator', 'licence_reference', 'evidence_reference', 'valid_from', 'valid_until', 'review_due_on', 'proposed_by', 'created_at'],
            'fi_statements' => ['public_id', 'financial_space_id', 'issuer_version_id', 'uploaded_by', 'idempotency_key', 'instruction_hash', 'file_hash', 'storage_path', 'media_type', 'bytes', 'period_start', 'period_end', 'authority_expires_at', 'authority_cipher', 'created_at'],
        ];
        foreach ($protected as $table => $fields) {
            if ($driver === 'pgsql') {
                $changed = implode(' OR ', array_map(fn ($field) => "OLD.{$field} IS DISTINCT FROM NEW.{$field}", $fields));
                DB::unprepared("CREATE FUNCTION {$table}_protect_source() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Source evidence cannot be deleted'; END IF; IF {$changed} THEN RAISE EXCEPTION 'Source evidence fields are immutable'; END IF; RETURN NEW; END; $$");
                DB::unprepared("CREATE TRIGGER {$table}_protect_source BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_protect_source()");
            } elseif ($driver === 'sqlite') {
                $changed = implode(' OR ', array_map(fn ($field) => "OLD.{$field} IS NOT NEW.{$field}", $fields));
                DB::unprepared("CREATE TRIGGER {$table}_protect_source BEFORE UPDATE ON {$table} WHEN {$changed} BEGIN SELECT RAISE(ABORT, 'Source evidence fields are immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_protect_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Source evidence cannot be deleted'); END");
            }
        }
    }

    public function down(): void
    {
        // Never silently destroy populated analytical evidence in a production rollback.
        if (! app()->environment('testing')) {
            foreach (['fi_sources', 'fi_imports', 'fi_statements', 'fi_issuer_versions', 'fi_reports'] as $table) {
                if (Schema::hasTable($table) && DB::table($table)->exists()) {
                    throw new LogicException('Financial Intelligence evidence exists. Use an approved forward migration, not destructive rollback.');
                }
            }
        }
        foreach (['fi_network_grants', 'fi_statements', 'fi_issuer_versions', 'fi_reports', 'fi_case_events', 'fi_cases', 'fi_publications', 'fi_imports', 'fi_grants', 'fi_sources'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            foreach (['fi_publications', 'fi_case_events', 'fi_reports'] as $table) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$table}_immutable()");
            }
            foreach (['fi_sources', 'fi_imports', 'fi_issuer_versions', 'fi_statements'] as $table) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$table}_protect_source()");
            }
        }
    }
};
