<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_contexts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('subject_type', 64)->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 80)->index();
            $table->string('source', 40)->index();
            $table->string('precision_level', 32)->default('locality')->index();
            $table->string('place_name')->nullable();
            $table->string('formatted_address')->nullable();
            $table->string('google_place_id')->nullable()->index();
            $table->string('plus_code')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('admin_area_1')->nullable()->index();
            $table->string('admin_area_2')->nullable()->index();
            $table->string('locality')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_metres')->nullable();
            $table->string('consent_purpose', 120);
            $table->string('verification_status', 32)->default('user_declared')->index();
            $table->boolean('credit_decision_eligible')->default(false)->index();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['subject_type', 'subject_id', 'purpose', 'deleted_at'],
                'location_context_subject_purpose_idx'
            );
            $table->index(
                ['country_code', 'admin_area_1', 'admin_area_2'],
                'location_context_geography_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_contexts');
    }
};
