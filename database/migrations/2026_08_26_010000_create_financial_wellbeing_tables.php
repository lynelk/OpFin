<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('account_type')->default('other');
            $table->bigInteger('balance_minor')->default(0);
            $table->string('currency', 3)->default('UGX');
            $table->string('confidence')->default('user_reported');
            $table->string('source_reference')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'active']);
            $table->index(['institution_id', 'user_id']);
        });

        Schema::create('financial_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->unsignedBigInteger('monthly_limit_minor');
            $table->string('currency', 3)->default('UGX');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedTinyInteger('alert_threshold_percent')->default(80);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'active', 'effective_from']);
            $table->index(['institution_id', 'user_id']);
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->string('category')->default('Other');
            $table->string('description')->nullable();
            $table->string('source')->default('manual');
            $table->string('source_reference')->nullable();
            $table->timestamp('occurred_at');
            $table->boolean('category_overridden')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'category', 'occurred_at']);
            $table->index(['institution_id', 'user_id']);
        });

        Schema::create('financial_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('event_type')->default('other');
            $table->string('direction');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('UGX');
            $table->timestamp('scheduled_for');
            $table->string('certainty')->default('scheduled');
            $table->string('status')->default('upcoming');
            $table->string('recurrence')->nullable();
            $table->string('category')->nullable();
            $table->string('source')->default('manual');
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'scheduled_for']);
            $table->index(['institution_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_calendar_events');
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('financial_budgets');
        Schema::dropIfExists('financial_accounts');
    }
};
