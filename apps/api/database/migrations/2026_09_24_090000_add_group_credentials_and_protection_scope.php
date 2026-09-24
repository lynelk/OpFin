<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_space_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('credential_type', 100);
            $table->string('issuer_code', 100);
            $table->string('issuer_name', 160);
            $table->string('credential_value', 255);
            $table->char('jurisdiction_country', 2)->default('UG')->index();
            $table->string('verification_status', 32)->default('declared')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_reference')->nullable();
            $table->string('verification_evidence_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['financial_space_id', 'credential_type', 'issuer_code'],
                'financial_space_credential_identity_unique'
            );
            $table->unique(
                ['jurisdiction_country', 'issuer_code', 'credential_type', 'credential_value'],
                'financial_space_credential_external_unique'
            );
        });

        Schema::table('protection_products', function (Blueprint $table) {
            $table->string('audience_scope', 24)->default('personal')->after('product_type')->index();
        });

        Schema::table('protection_policies', function (Blueprint $table) {
            $table->foreignId('financial_space_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('financial_spaces')
                ->nullOnDelete();
            $table->string('coverage_scope', 24)->default('personal')->after('financial_space_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('protection_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_space_id');
            $table->dropColumn('coverage_scope');
        });

        Schema::table('protection_products', function (Blueprint $table) {
            $table->dropColumn('audience_scope');
        });

        Schema::dropIfExists('financial_space_credentials');
    }
};
