<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('transactions_frozen')->default(false);
            $table->boolean('login_alerts')->default(true);
            $table->boolean('payment_alerts')->default(true);
            $table->string('changed_by')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('event_type', 80);
            $table->string('severity', 20)->default('info');
            $table->string('source', 80)->default('opfin');
            $table->string('ip_address', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('credit_builder_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('goal', 255)->nullable();
            $table->integer('baseline_score')->nullable();
            $table->integer('target_score')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('actions')->nullable();
            $table->date('review_due_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('hardship_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('submitted');
            $table->bigInteger('monthly_income_minor')->default(0);
            $table->bigInteger('essential_expenses_minor')->default(0);
            $table->bigInteger('debt_commitments_minor')->default(0);
            $table->json('requested_relief')->nullable();
            $table->json('approved_relief')->nullable();
            $table->string('requested_by');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('financial_passport_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('content');
            $table->json('provenance');
            $table->string('confidence', 30);
            $table->char('content_hash', 64)->unique();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['user_id', 'generated_at']);
        });

        Schema::create('product_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 80);
            $table->unsignedInteger('version');
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->json('definition');
            $table->string('created_by');
            $table->string('submitted_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['product_code', 'version']);
            $table->index(['status', 'product_code']);
        });

        Schema::create('decision_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 80);
            $table->unsignedInteger('version');
            $table->string('name');
            $table->integer('priority')->default(100);
            $table->string('status', 30)->default('draft');
            $table->json('conditions');
            $table->json('actions');
            $table->string('created_by');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['rule_code', 'version']);
            $table->index(['status', 'priority']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_code', 80);
            $table->unsignedInteger('version');
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->json('states');
            $table->json('transitions');
            $table->string('created_by');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_code', 'version']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 80);
            $table->string('subject_reference', 160);
            $table->string('current_state', 80);
            $table->string('status', 30)->default('running');
            $table->json('context')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_reference']);
        });

        Schema::create('workflow_transition_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->string('from_state', 80);
            $table->string('to_state', 80);
            $table->string('actor');
            $table->json('context')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'workflow_transition_events', 'workflow_runs', 'workflow_definitions', 'decision_rules',
            'product_definitions', 'financial_passport_snapshots', 'hardship_cases', 'credit_builder_plans',
            'security_events', 'security_controls',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
