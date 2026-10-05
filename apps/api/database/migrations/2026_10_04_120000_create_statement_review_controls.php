<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fi_statements', function (Blueprint $t): void {
            // A resubmitted statement links to the version it replaces; earlier versions are kept.
            $t->foreignId('supersedes_statement_id')->nullable()->constrained('fi_statements')->restrictOnDelete();
        });

        Schema::create('fi_statement_reviews', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('fi_statement_id')->constrained('fi_statements')->restrictOnDelete();
            $t->foreignId('financial_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('actor_role', 30);
            $t->string('decision', 40);
            $t->string('reason_code', 60);
            $t->string('evidence_reference', 160)->nullable();
            // Free-text notes can hold personal information, so they are encrypted at rest.
            $t->longText('notes_cipher')->nullable();
            $t->string('idempotency_key', 120);
            $t->timestamp('created_at');
            $t->unique(['fi_statement_id', 'idempotency_key']);
            $t->index(['financial_space_id', 'created_at']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION fi_statement_reviews_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Statement review decisions are append-only'; END; $$");
            DB::unprepared('CREATE TRIGGER fi_statement_reviews_immutable BEFORE UPDATE OR DELETE ON fi_statement_reviews FOR EACH ROW EXECUTE FUNCTION fi_statement_reviews_immutable()');
        } elseif ($driver === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $verb) {
                DB::unprepared('CREATE TRIGGER fi_statement_reviews_no_'.strtolower($verb)." BEFORE {$verb} ON fi_statement_reviews BEGIN SELECT RAISE(ABORT, 'Statement review decisions are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS fi_statement_reviews_immutable ON fi_statement_reviews');
            DB::unprepared('DROP FUNCTION IF EXISTS fi_statement_reviews_immutable()');
        }
        Schema::dropIfExists('fi_statement_reviews');
        Schema::table('fi_statements', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('supersedes_statement_id');
        });
    }
};
