<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_engagements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('user_id')->constrained('users');
            $table->string('engagement_type', 40);
            $table->string('workforce_number', 60);
            $table->string('job_title', 160);
            $table->string('department', 120)->nullable();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['business_space_id', 'workforce_number'], 'people_org_workforce_number');
            $table->index(['business_space_id', 'user_id', 'status'], 'people_engagement_member_idx');
        });

        Schema::create('people_leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->string('leave_type', 60);
            $table->date('effective_from');
            $table->unsignedInteger('annual_days');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['business_space_id', 'leave_type', 'effective_from'], 'people_leave_policy_effective');
        });

        Schema::create('people_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->string('leave_type', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('requested_days');
            $table->string('status', 24)->default('requested');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['business_space_id', 'status', 'starts_on'], 'people_leave_due');
        });

        Schema::create('people_attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->string('direction', 16);
            $table->timestamp('occurred_at');
            $table->string('source', 30);
            $table->string('idempotency_key', 120);
            $table->string('device_reference_hash', 64)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['business_space_id', 'idempotency_key'], 'people_attendance_idempotency');
        });

        Schema::create('people_biometric_connectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->string('vendor', 80);
            $table->string('external_device_reference', 160);
            $table->string('status', 24)->default('pending_review');
            $table->string('processing_approval_reference', 160)->nullable();
            $table->timestamp('processing_approved_at')->nullable();
            $table->foreignId('configured_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['business_space_id', 'vendor', 'external_device_reference'], 'people_bio_device_external');
        });

        Schema::create('people_payroll_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->string('policy_version', 80);
            $table->date('effective_from');
            $table->string('country', 2);
            $table->char('currency', 3);
            $table->json('statutory_rules');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['business_space_id', 'policy_version'], 'people_payroll_policy_version');
        });

        Schema::create('people_payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('payroll_policy_id')->nullable()->constrained('people_payroll_policies');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['business_space_id', 'period_start', 'period_end'], 'people_payroll_period_unique');
        });

        Schema::create('people_payroll_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('people_payroll_periods');
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->unsignedBigInteger('gross_minor');
            $table->unsignedBigInteger('deductions_minor');
            $table->unsignedBigInteger('net_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->foreignId('entered_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['payroll_period_id', 'engagement_id'], 'people_payroll_one_line_per_worker');
        });

        Schema::create('people_asset_custodies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->foreignId('financial_asset_id')->constrained('financial_assets');
            $table->string('status', 24)->default('issued');
            $table->date('issued_on');
            $table->date('returned_on')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users');
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['business_space_id', 'financial_asset_id', 'status'], 'people_asset_custody_lookup');
        });

        Schema::create('people_kpi_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->string('metric', 160);
            $table->string('unit', 50);
            $table->decimal('target_value', 16, 4);
            $table->decimal('actual_value', 16, 4)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('evidence_reference', 160)->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('people_appraisals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->date('period_start');
            $table->date('period_end');
            $table->text('self_assessment')->nullable();
            $table->text('manager_assessment')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('people_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->string('reward_type', 40);
            $table->string('recognition', 500);
            $table->unsignedBigInteger('proposed_amount_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 24)->default('nominated');
            $table->foreignId('nominated_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('people_field_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('engagement_id')->constrained('people_engagements');
            $table->string('title', 160);
            $table->string('instructions', 1000)->nullable();
            $table->date('due_on')->nullable();
            $table->string('evidence_reference', 160)->nullable();
            $table->unsignedBigInteger('claimed_expense_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 24)->default('assigned');
            $table->foreignId('assigned_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('people_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces');
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('action', 100);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_space_id', 'subject_type', 'subject_id'], 'people_audit_subject');
        });
    }

    public function down(): void
    {
        foreach ([
            'people_audit_events', 'people_field_assignments', 'people_rewards',
            'people_appraisals', 'people_kpi_entries', 'people_asset_custodies',
            'people_payroll_lines', 'people_payroll_periods', 'people_payroll_policies',
            'people_biometric_connectors', 'people_attendance_events',
            'people_leave_requests', 'people_leave_policies', 'people_engagements',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
