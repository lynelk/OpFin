<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people_biometric_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $table->foreignId('connector_id')->constrained('people_biometric_connectors')->restrictOnDelete();
            $table->foreignId('engagement_id')->constrained('people_engagements')->restrictOnDelete();
            $table->char('external_subject_hash', 64);
            $table->string('lawful_basis_reference', 160);
            $table->string('privacy_notice_reference', 160);
            $table->string('status', 24)->default('pending_review');
            $table->foreignId('registered_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['connector_id', 'external_subject_hash'], 'people_biometric_subject_dedupe');
        });

        Schema::create('people_biometric_event_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_space_id')->constrained('financial_spaces')->restrictOnDelete();
            $table->foreignId('connector_id')->constrained('people_biometric_connectors')->restrictOnDelete();
            $table->foreignId('engagement_id')->constrained('people_engagements')->restrictOnDelete();
            $table->foreignId('attendance_event_id')->unique()
                ->constrained('people_attendance_events')->restrictOnDelete();
            $table->char('vendor_event_hash', 64);
            $table->char('signed_payload_hash', 64);
            $table->string('ingestion_status', 30)->default('accepted_for_review');
            $table->timestamps();
            $table->unique(['connector_id', 'vendor_event_hash'], 'people_biometric_vendor_event_dedupe');
        });

        Schema::table('people_biometric_connectors', function (Blueprint $table): void {
            $table->string('privacy_notice_reference', 160)->nullable();
            $table->string('vendor_security_review_reference', 160)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('people_biometric_connectors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['privacy_notice_reference', 'vendor_security_review_reference']);
        });
        Schema::dropIfExists('people_biometric_event_receipts');
        Schema::dropIfExists('people_biometric_bindings');
    }
};
