<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Float top-ups were approved by the person recording them and changed the
 * Disbursement balance immediately. They now start as pending and need a
 * different staff member to approve. Existing rows keep their recorded status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('float_topups', function (Blueprint $table) {
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('float_topups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('recorded_by_user_id');
        });
    }
};
