<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
        });

        Schema::create('compliance_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format')->default('csv');
            $table->string('status')->default('generated')->index();
            $table->string('storage_path')->nullable();
            $table->json('manifest');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_exports');
        Schema::dropIfExists('support_case_notes');
    }
};
