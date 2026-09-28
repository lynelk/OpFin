<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('financial_space_brands', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->unique()->constrained()->cascadeOnDelete();
   $t->string('display_name'); $t->string('short_name')->nullable(); $t->string('logo_url')->nullable();
   $t->string('primary_colour',20)->nullable(); $t->string('accent_colour',20)->nullable();
   $t->string('support_email')->nullable(); $t->string('support_phone')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
  });
  Schema::create('financial_space_domains', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
   $t->string('hostname')->unique(); $t->string('status')->default('pending_verification')->index();
   $t->string('verification_token_hash'); $t->timestamp('verified_at')->nullable(); $t->timestamp('activated_at')->nullable();
   $t->timestamp('disabled_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
  });
  Schema::create('sacco_products', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $t->string('code'); $t->string('name');
   $t->string('product_type')->index(); $t->char('currency',3)->default('UGX'); $t->string('status')->default('draft')->index();
   $t->boolean('withdrawable')->default(false); $t->boolean('mandatory')->default(false); $t->unsignedBigInteger('minimum_balance_minor')->default(0);
   $t->unsignedInteger('annual_rate_bps')->default(0); $t->json('rules')->nullable(); $t->timestamps(); $t->unique(['financial_space_id','code']);
  });
  Schema::create('sacco_member_accounts', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->foreignId('sacco_product_id')->constrained('sacco_products')->restrictOnDelete(); $t->string('account_number');
   $t->bigInteger('balance_minor')->default(0); $t->bigInteger('held_minor')->default(0); $t->string('status')->default('active')->index(); $t->timestamps();
   $t->unique(['financial_space_id','account_number']); $t->unique(['user_id','sacco_product_id']);
  });
  Schema::create('sacco_share_register', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->unsignedBigInteger('shares_micro')->default(0); $t->unsignedBigInteger('paid_up_capital_minor')->default(0); $t->char('currency',3)->default('UGX'); $t->timestamps();
   $t->unique(['financial_space_id','user_id']);
  });
  Schema::create('sacco_guarantees', function(Blueprint $t){
   $t->id(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete(); $t->foreignId('guarantor_user_id')->constrained('users');
   $t->foreignId('borrower_user_id')->constrained('users'); $t->unsignedBigInteger('loan_id')->nullable()->index(); $t->unsignedBigInteger('amount_minor');
   $t->string('status')->default('proposed')->index(); $t->timestamp('accepted_at')->nullable(); $t->timestamp('released_at')->nullable(); $t->timestamps();
  });
  Schema::create('sacco_dividend_runs', function(Blueprint $t){
   $t->id(); $t->uuid('public_id')->unique(); $t->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
   $t->string('run_type')->index(); $t->date('record_date'); $t->unsignedInteger('rate_bps'); $t->unsignedBigInteger('gross_amount_minor')->default(0);
   $t->string('status')->default('draft')->index(); $t->foreignId('prepared_by_user_id')->constrained('users');
   $t->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('approved_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
  });
  Schema::create('sacco_dividend_allocations', function(Blueprint $t){
   $t->id(); $t->foreignId('sacco_dividend_run_id')->constrained()->cascadeOnDelete(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->unsignedBigInteger('basis_minor'); $t->unsignedBigInteger('gross_amount_minor'); $t->unsignedBigInteger('withholding_minor')->default(0);
   $t->unsignedBigInteger('net_amount_minor'); $t->string('status')->default('allocated')->index(); $t->timestamps(); $t->unique(['sacco_dividend_run_id','user_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('sacco_dividend_allocations'); Schema::dropIfExists('sacco_dividend_runs'); Schema::dropIfExists('sacco_guarantees');
  Schema::dropIfExists('sacco_share_register'); Schema::dropIfExists('sacco_member_accounts'); Schema::dropIfExists('sacco_products');
  Schema::dropIfExists('financial_space_domains'); Schema::dropIfExists('financial_space_brands');
 }
};
