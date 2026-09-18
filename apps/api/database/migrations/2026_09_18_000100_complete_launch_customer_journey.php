<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('other_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('other_name');
            $table->string('preferred_language', 16)->default('en')->after('last_name');
            $table->json('accessibility_preferences')->nullable()->after('preferred_language');
        });

        Schema::table('kyc_cases', function (Blueprint $table) {
            $table->string('national_id_front_path')->nullable()->after('national_id');
            $table->string('national_id_back_path')->nullable()->after('national_id_front_path');
            $table->string('selfie_with_id_path')->nullable()->after('national_id_back_path');
            $table->string('liveness_status')->default('not_checked')->after('selfie_with_id_path');
            $table->string('face_match_status')->default('not_checked')->after('liveness_status');
            $table->string('nin_phone_link_status')->default('not_checked')->after('face_match_status');
            $table->timestamp('evidence_complete_at')->nullable()->after('nin_phone_link_status');
        });

        Schema::create('customer_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->unique();
            $table->string('kind')->default('secondary')->index();
            $table->string('provider')->nullable();
            $table->string('ownership_name_match_status')->default('not_checked');
            $table->unsignedInteger('sim_tenure_days')->nullable();
            $table->timestamp('verified_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind', 'verified_at']);
        });

        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('customer_phone_numbers')->nullOnDelete();
            $table->string('provider')->default('mobile_money');
            $table->string('msisdn');
            $table->string('status')->default('active')->index();
            $table->boolean('is_default_disbursement')->default(false);
            $table->boolean('is_default_repayment')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'msisdn']);
        });

        Schema::create('credit_score_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('customer_phone_numbers')->nullOnDelete();
            $table->string('source')->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('weight_percent', 6, 2)->default(0);
            $table->string('source_reference')->nullable();
            $table->json('reason_codes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'source', 'status']);
        });

        Schema::create('credit_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->decimal('composite_score', 6, 2)->nullable();
            $table->string('band')->nullable();
            $table->decimal('coverage_percent', 6, 2)->default(0);
            $table->unsignedBigInteger('credit_limit_minor')->default(0);
            $table->unsignedBigInteger('current_exposure_minor')->default(0);
            $table->unsignedBigInteger('available_to_borrow_minor')->default(0);
            $table->unsignedBigInteger('amount_due_minor')->default(0);
            $table->unsignedBigInteger('total_outstanding_minor')->default(0);
            $table->date('next_due_date')->nullable();
            $table->string('model_version')->default('composite-v1');
            $table->json('component_breakdown')->nullable();
            $table->json('reason_codes')->nullable();
            $table->json('customer_explanations')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_profiles');
        Schema::dropIfExists('credit_score_components');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('customer_phone_numbers');

        Schema::table('kyc_cases', function (Blueprint $table) {
            $table->dropColumn([
                'national_id_front_path',
                'national_id_back_path',
                'selfie_with_id_path',
                'liveness_status',
                'face_match_status',
                'nin_phone_link_status',
                'evidence_complete_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'other_name',
                'last_name',
                'preferred_language',
                'accessibility_preferences',
            ]);
        });
    }
};
