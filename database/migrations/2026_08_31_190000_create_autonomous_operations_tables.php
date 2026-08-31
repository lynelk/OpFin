<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autopilot_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running')->index();
            $table->string('trigger')->default('scheduled');
            $table->unsignedInteger('observations')->default(0);
            $table->unsignedInteger('actions_executed')->default(0);
            $table->unsignedInteger('exceptions_created')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('autopilot_work_items', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('type')->index();
            $table->string('severity')->default('medium')->index();
            $table->string('status')->default('open')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_reference')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('recommended_action')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('automation_tier')->default('A1');
            $table->boolean('requires_human')->default(true)->index();
            $table->json('context')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->foreignId('autopilot_run_id')->nullable()->constrained('autopilot_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['domain', 'type', 'subject_type', 'subject_reference', 'status'],
                'autopilot_item_lookup'
            );
        });

        Schema::create('autopilot_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autopilot_run_id')->nullable()->constrained('autopilot_runs')->nullOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained('autopilot_work_items')->nullOnDelete();
            $table->string('domain')->index();
            $table->string('action');
            $table->string('outcome')->index();
            $table->string('automation_tier')->default('A2');
            $table->json('context')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autopilot_action_logs');
        Schema::dropIfExists('autopilot_work_items');
        Schema::dropIfExists('autopilot_runs');
    }
};
