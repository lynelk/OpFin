<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->string('name', 180);
            $table->string('family', 80);
            $table->string('rail', 24);
            $table->json('contract_types');
            // Bounds a partner product may not exceed, and where those bounds come from.
            $table->json('guardrails');
            $table->string('policy_reference', 200);
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
            $table->unique(['created_by', 'idempotency_key']);
        });

        Schema::table('financial_products', function (Blueprint $table): void {
            $table->foreignId('product_template_id')->nullable()->constrained('product_templates')->restrictOnDelete();
            $table->json('parameters')->nullable();
            // The parties a customer actually deals with, stated explicitly (PF-001).
            $table->string('lender_reference', 180)->nullable();
            $table->string('funder_reference', 180)->nullable();
            $table->string('principal_reference', 180)->nullable();
            $table->foreignId('supersedes_product_id')->nullable()->constrained('financial_products')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->unique(['created_by', 'idempotency_key']);
        });

        Schema::table('legal_product_passports', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 160)->nullable();
            $table->string('evidence_reference', 200)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['created_by', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('legal_product_passports', function (Blueprint $table): void {
            $table->dropUnique(['created_by', 'idempotency_key']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['idempotency_key', 'evidence_reference', 'revoked_at']);
        });
        Schema::table('financial_products', function (Blueprint $table): void {
            $table->dropUnique(['created_by', 'idempotency_key']);
            foreach (['product_template_id', 'supersedes_product_id', 'created_by', 'submitted_by', 'approved_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['parameters', 'lender_reference', 'funder_reference', 'principal_reference', 'idempotency_key',
                'submitted_at', 'approved_at', 'retired_at']);
        });
        Schema::dropIfExists('product_templates');
    }
};
