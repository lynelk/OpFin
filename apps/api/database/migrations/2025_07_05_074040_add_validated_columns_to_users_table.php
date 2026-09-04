<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id')->after('phone')->nullable();
            $table->string('date_of_birth')->after('national_id')->nullable();
            $table->string('nin_status')->after('date_of_birth')->nullable();
            $table->string('api_status')->after('nin_status')->nullable();
            $table->string('validated_at')->after('api_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('national_id');
            $table->dropColumn('date_of_birth');
        });
    }
};
