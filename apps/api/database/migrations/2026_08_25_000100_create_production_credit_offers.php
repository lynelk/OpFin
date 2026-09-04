<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_decisions', function (Blueprint $table) {
            $table->string('policy_version')->nullable()->after('estimated_obligation_minor');
        });

        Schema::create('credit_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_decision_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('offer_reference')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('offered')->index();
            $table->string('currency', 3)->default('UGX');
            $table->unsignedBigInteger('principal_amount_minor');
            $table->unsignedBigInteger('interest_amount_minor');
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('net_disbursement_minor');
            $table->unsignedBigInteger('total_repayment_minor');
            $table->unsignedInteger('duration_days');
            $table->decimal('interest_rate_percent', 12, 6);
            $table->string('interest_cycle');
            $table->string('interest_type');
            $table->string('repayment_frequency');
            $table->string('fee_treatment')->default('financed');
            $table->string('policy_version');
            $table->json('pricing_snapshot');
            $table->json('disclosure_snapshot');
            $table->timestamp('offered_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->json('acceptance_metadata')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'version']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->foreignId('credit_offer_id')->nullable()->after('transaction_id')->constrained('credit_offers')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->after('credit_offer_id')->constrained('loans')->nullOnDelete();
            $table->index(['credit_offer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->dropIndex(['credit_offer_id', 'status']);
            $table->dropConstrainedForeignId('loan_id');
            $table->dropConstrainedForeignId('credit_offer_id');
        });

        Schema::dropIfExists('credit_offers');

        Schema::table('credit_decisions', function (Blueprint $table) {
            $table->dropColumn('policy_version');
        });
    }
};
