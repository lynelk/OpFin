<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_passports', function (Blueprint $table): void {
            $table->string('sku', 64)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160)->nullable();
            $table->char('instruction_hash', 64)->nullable();
            // Internal reason a passport needs review (for example an identifier already active elsewhere).
            // Never shown to the registrant, so it cannot reveal another Space's asset.
            $table->string('review_reason', 80)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('supplier_profile_id')->nullable()->constrained('supplier_profiles')->nullOnDelete();
            $table->json('purchase')->nullable();
            $table->unique(['financial_space_id', 'idempotency_key']);
        });

        Schema::create('asset_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_passport_id')->constrained('asset_passports')->cascadeOnDelete();
            $table->string('identifier_type', 40);
            // Keyed HMAC of the normalised value: duplicate checks without storing raw IMEIs or VINs.
            $table->char('value_hmac', 64);
            $table->string('masked', 32);
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->index(['identifier_type', 'value_hmac']);
        });

        Schema::create('asset_passport_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_passport_id')->constrained('asset_passports')->restrictOnDelete();
            $table->string('event_type', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160);
            $table->string('reason', 240)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['asset_passport_id', 'idempotency_key']);
        });

        Schema::create('asset_encumbrances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('asset_passport_id')->constrained('asset_passports')->restrictOnDelete();
            $table->foreignId('financing_arrangement_id')->constrained('financing_arrangements')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 240)->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        // An identifier is active on at most one passport, and an asset carries at most one active lien.
        DB::statement('CREATE UNIQUE INDEX asset_identifiers_one_active ON asset_identifiers (identifier_type, value_hmac) WHERE active');
        DB::statement("CREATE UNIQUE INDEX asset_encumbrances_one_active ON asset_encumbrances (asset_passport_id) WHERE status = 'active'");
        $this->appendOnly('asset_passport_events');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS asset_passport_events_immutable ON asset_passport_events');
            DB::unprepared('DROP FUNCTION IF EXISTS asset_passport_events_immutable()');
        }
        Schema::dropIfExists('asset_encumbrances');
        Schema::dropIfExists('asset_passport_events');
        Schema::dropIfExists('asset_identifiers');
        Schema::table('asset_passports', function (Blueprint $table): void {
            $table->dropUnique(['financial_space_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('registered_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('supplier_profile_id');
            $table->dropColumn(['sku', 'idempotency_key', 'instruction_hash', 'review_reason', 'verified_at', 'purchase']);
        });
    }

    private function appendOnly(string $table): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::unprepared("CREATE FUNCTION {$table}_immutable() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Asset passport history is append-only'; END; $$");
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_immutable()");
        } elseif ($driver === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $verb) {
                DB::unprepared("CREATE TRIGGER {$table}_no_".strtolower($verb)." BEFORE {$verb} ON {$table} BEGIN SELECT RAISE(ABORT, 'Asset passport history is append-only'); END");
            }
        }
    }
};
