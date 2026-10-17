<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_aspirations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('financial_space_id')->constrained('financial_spaces');
            $table->foreignId('institution_id')->nullable()->constrained('institutions');
            $table->string('title', 120);
            $table->string('category', 32)->default('other');
            $table->unsignedBigInteger('target_amount_minor')->nullable();
            $table->char('currency', 3)->default('UGX');
            $table->date('target_date')->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('is_priority')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'financial_space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_aspirations');
    }
};
