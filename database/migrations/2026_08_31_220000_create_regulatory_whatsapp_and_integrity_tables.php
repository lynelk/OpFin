<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_report_runs', function (Blueprint $table) {
            $table->id();
            $table->string('report_type')->index();
            $table->string('regulator')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('generated')->index();
            $table->json('payload');
            $table->json('validation_results')->nullable();
            $table->string('payload_hash', 64);
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['report_type', 'period_start', 'period_end']);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wa_phone')->unique();
            $table->string('state')->default('unverified')->index();
            $table->string('journey')->nullable()->index();
            $table->string('session_nonce', 64)->nullable()->unique();
            $table->string('challenge_hash')->nullable();
            $table->unsignedTinyInteger('challenge_attempts')->default(0);
            $table->timestamp('challenge_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction')->index();
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('payload_hash', 64);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('data_breach_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_reference')->unique();
            $table->string('severity')->index();
            $table->string('category')->index();
            $table->unsignedInteger('affected_subjects')->default(0);
            $table->string('status')->default('open')->index();
            $table->text('description');
            $table->text('containment')->nullable();
            $table->text('remediation')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('notified_pdpo_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('financial_integrity_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running')->index();
            $table->string('scope')->default('platform');
            $table->unsignedInteger('ledger_transactions_checked')->default(0);
            $table->unsignedInteger('unbalanced_transactions')->default(0);
            $table->unsignedInteger('payment_exceptions')->default(0);
            $table->unsignedInteger('duplicate_references')->default(0);
            $table->unsignedInteger('orphan_entries')->default(0);
            $table->bigInteger('net_ledger_imbalance_minor')->default(0);
            $table->json('findings')->nullable();
            $table->string('evidence_hash', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_integrity_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('financial_integrity_runs')->cascadeOnDelete();
            $table->string('severity')->index();
            $table->string('type')->index();
            $table->string('reference')->nullable()->index();
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->json('evidence')->nullable();
            $table->json('resolution_evidence')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'type', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_integrity_alerts');
        Schema::dropIfExists('financial_integrity_runs');
        Schema::dropIfExists('data_breach_incidents');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('regulatory_report_runs');
    }
};
