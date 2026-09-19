<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_spaces', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type')->index();
            $table->string('name');
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->char('country', 2)->default('UG')->index();
            $table->char('currency', 3)->default('UGX');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_space_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member')->index();
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['financial_space_id', 'user_id'], 'fsm_space_user_unique');
            $table->index(['user_id', 'status'], 'fsm_user_status_idx');
        });

        Schema::create('financial_space_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_phone')->nullable()->index();
            $table->string('recipient_email')->nullable()->index();
            $table->string('intended_role')->default('member');
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_space_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_space_id')->constrained()->cascadeOnDelete();
            $table->string('capability_key');
            $table->boolean('enabled')->default(false);
            $table->string('status')->default('configured');
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['financial_space_id', 'capability_key'], 'fsc_space_capability_unique');
        });

        // Bootstrap exactly one Personal Space for every current user.
        DB::table('users')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $spaceId = DB::table('financial_spaces')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'type' => 'personal',
                    'name' => trim((string) ($user->first_name ?? '')) ?: 'My Money',
                    'country' => 'UG',
                    'currency' => 'UGX',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('financial_space_memberships')->insert([
                    'financial_space_id' => $spaceId,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        foreach (['financial_accounts', 'financial_budgets', 'financial_entries', 'financial_calendar_events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('financial_space_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index(['financial_space_id', 'user_id']);
            });

            DB::statement("UPDATE {$tableName} target
                SET financial_space_id = (
                    SELECT fsm.financial_space_id
                    FROM financial_space_memberships fsm
                    JOIN financial_spaces fs ON fs.id = fsm.financial_space_id
                    WHERE fsm.user_id = target.user_id
                      AND fs.type = 'personal'
                      AND fsm.role = 'owner'
                    ORDER BY fsm.id ASC
                    LIMIT 1
                )
                WHERE financial_space_id IS NULL");
        }
    }

    public function down(): void
    {
        foreach (['financial_calendar_events', 'financial_entries', 'financial_budgets', 'financial_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign([$table->getTable() === '' ? 'financial_space_id' : 'financial_space_id']);
                $table->dropColumn('financial_space_id');
            });
        }

        Schema::dropIfExists('financial_space_capabilities');
        Schema::dropIfExists('financial_space_invitations');
        Schema::dropIfExists('financial_space_memberships');
        Schema::dropIfExists('financial_spaces');
    }
};
