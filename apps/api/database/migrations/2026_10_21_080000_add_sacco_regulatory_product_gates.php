<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_regulatory_attestations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->char('jurisdiction', 2);
            $table->string('issuing_authority', 160);
            $table->string('licence_category', 120);
            $table->string('evidence_reference', 200);
            $table->json('permitted_product_types');
            $table->date('valid_from');
            $table->date('expires_on')->nullable();
            $table->string('review_status', 32)->default('pending_review');
            $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['financial_space_id', 'review_status', 'expires_on'], 'sacco_attestation_validity');
        });

        Schema::table('sacco_products', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users');
            $table->foreignId('activation_attestation_id')->nullable()
                ->constrained('sacco_regulatory_attestations');
            $table->timestamp('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sacco_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activation_attestation_id');
            $table->dropConstrainedForeignId('activated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('activated_at');
        });
        Schema::dropIfExists('sacco_regulatory_attestations');
    }
};
