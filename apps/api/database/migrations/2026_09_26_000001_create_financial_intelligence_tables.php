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
    }

    public function down(): void
    {
        // This rollback is for empty disposable test databases, never production evidence removal.
        foreach (['fi_network_grants', 'fi_statements', 'fi_issuer_versions', 'fi_reports', 'fi_case_events', 'fi_cases', 'fi_publications', 'fi_imports', 'fi_grants', 'fi_sources'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            foreach (['fi_publications', 'fi_case_events', 'fi_reports'] as $table) {
                DB::unprepared("DROP FUNCTION IF EXISTS {$table}_immutable()");
            }
        }
    }
};
