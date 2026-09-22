<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_economics_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commercial_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_code', 80)->index();
            $table->string('capability_code', 120)->nullable()->index();
            $table->string('provider', 120)->index();
            $table->string('route', 40)->index();
            $table->string('environment', 40)->default('PRODUCTION')->index();
            $table->string('request_reference', 200);
            $table->string('provider_reference', 200)->nullable()->index();
            $table->string('status', 40)->index();
            $table->char('currency', 3)->default('UGX');

            // Nullable means unknown/not yet supplied. A known zero must be stored as 0.
            $table->bigInteger('provider_gross_cost_minor')->nullable();
            $table->bigInteger('provider_discount_minor')->nullable();
            $table->bigInteger('provider_net_cost_minor')->nullable();
            $table->bigInteger('customer_service_charge_minor')->nullable();
            $table->bigInteger('customer_platform_fee_minor')->nullable();
            $table->bigInteger('partner_commission_minor')->nullable();
            $table->bigInteger('cito_platform_fee_minor')->nullable();
            $table->bigInteger('opfin_platform_fee_minor')->nullable();
            $table->bigInteger('tax_amount_minor')->nullable();
            $table->bigInteger('net_settlement_to_provider_minor')->nullable();
            $table->bigInteger('gross_revenue_minor')->nullable();
            $table->bigInteger('net_revenue_minor')->nullable();
            $table->bigInteger('gross_margin_minor')->nullable();

            $table->string('price_book_version', 120)->nullable();
            $table->string('contract_version', 120)->nullable();
            $table->string('reconciliation_reference', 200)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['service_code', 'request_reference'], 'service_economics_idempotency');
            $table->index(['provider', 'route', 'occurred_at'], 'service_economics_provider_route_time');
        });

        Schema::table('savings_products', function (Blueprint $table) {
            $table->json('economics_config')->nullable()->after('disclosures');
        });

        Schema::table('protection_products', function (Blueprint $table) {
            $table->json('economics_config')->nullable()->after('disclosure_payload');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('funding_pool_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('capital_mandates')
                ->nullOnDelete();
            $table->index(['funding_pool_id', 'status'], 'loans_funding_pool_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('protection_products', function (Blueprint $table) {
            $table->dropColumn('economics_config');
        });

        Schema::table('savings_products', function (Blueprint $table) {
            $table->dropColumn('economics_config');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['funding_pool_id']);
            $table->dropIndex('loans_funding_pool_status_idx');
            $table->dropColumn('funding_pool_id');
        });

        Schema::dropIfExists('service_economics_events');
    }
};
