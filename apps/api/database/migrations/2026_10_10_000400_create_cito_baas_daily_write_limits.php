<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cito_baas_daily_write_limits', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 16);
            $table->date('business_date');
            $table->unsignedInteger('reserved_calls')->default(0);
            $table->timestamps();
            $table->unique(['environment', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cito_baas_daily_write_limits');
    }
};
