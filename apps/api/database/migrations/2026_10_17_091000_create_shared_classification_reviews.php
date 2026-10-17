<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_shared_classification_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('classification_id')->constrained('institution_shared_classifications')->restrictOnDelete();
            $t->string('capacity', 35);
            $t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $t->string('review_reference', 255);
            $t->char('review_document_sha256', 64);
            $t->text('technical_conclusion');
            $t->timestamp('reviewed_at');
            $t->timestamps();
            $t->unique(['classification_id', 'capacity'], 'shared_classification_capacity_once');
            $t->unique(['classification_id', 'reviewed_by'], 'shared_classification_distinct_signers');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION institution_shared_classification_reviews_append_only() RETURNS trigger LANGUAGE plpgsql AS \$assurance\$ BEGIN RAISE EXCEPTION 'Independent contractual classification reviews are append-only'; END; \$assurance\$");
            DB::unprepared('CREATE TRIGGER shared_review_append_only BEFORE UPDATE OR DELETE ON institution_shared_classification_reviews FOR EACH ROW EXECUTE FUNCTION institution_shared_classification_reviews_append_only()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER shared_review_immutable BEFORE UPDATE ON institution_shared_classification_reviews BEGIN SELECT RAISE(ABORT,'Independent classification reviews are immutable'); END");
            DB::unprepared("CREATE TRIGGER shared_review_append_only BEFORE DELETE ON institution_shared_classification_reviews BEGIN SELECT RAISE(ABORT,'Independent classification reviews are append-only'); END");
        }
    }

    public function down(): void
    {
        if (! app()->environment('testing') && Schema::hasTable('institution_shared_classification_reviews')
            && DB::table('institution_shared_classification_reviews')->exists()) {
            throw new LogicException('Signed contractual classification cannot be destroyed.');
        }
        Schema::dropIfExists('institution_shared_classification_reviews');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS institution_shared_classification_reviews_append_only()');
        }
    }
};
