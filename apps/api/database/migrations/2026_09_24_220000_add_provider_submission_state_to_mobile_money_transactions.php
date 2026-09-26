<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->string('provider_submission_state')->nullable()->after('status')->index();
            $table->timestamp('provider_submission_started_at')->nullable()->after('provider_submission_state');
            $table->timestamp('provider_submission_resolved_at')->nullable()->after('provider_submission_started_at');
            $table->index(['provider_submission_state', 'status'], 'money_provider_submission_state_status');
        });

        DB::table('mobile_money_transactions')
            ->whereNotNull('provider_reference')
            ->update([
                'provider_submission_state' => 'provider_response_received',
                'provider_submission_resolved_at' => DB::raw('updated_at'),
            ]);

        DB::table('mobile_money_transactions')
            ->whereNull('provider_submission_state')
            ->update(['provider_submission_state' => 'legacy_unknown']);
    }

    public function down(): void
    {
        Schema::table('mobile_money_transactions', function (Blueprint $table) {
            $table->dropIndex('money_provider_submission_state_status');
            $table->dropColumn([
                'provider_submission_state',
                'provider_submission_started_at',
                'provider_submission_resolved_at',
            ]);
        });
    }
};
