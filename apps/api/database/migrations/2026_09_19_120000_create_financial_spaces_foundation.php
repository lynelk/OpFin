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

        $personalSpaceByUser = [];

        DB::table('users')->orderBy('id')->chunkById(500, function ($users) use (&$personalSpaceByUser) {
            foreach ($users as $user) {
                $spaceId = DB::table('financial_spaces')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'type' => 'personal',
                    'name' => 'My Money',
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

                $personalSpaceByUser[(int) $user->id] = $spaceId;
            }
        });

        foreach (['financial_accounts', 'financial_budgets', 'financial_entries', 'financial_calendar_events'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('financial_space_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index(['financial_space_id', 'user_id']);
            });

            DB::table($tableName)->select(['id', 'user_id'])->orderBy('id')->chunkById(500, function ($rows) use ($tableName, $personalSpaceByUser) {
                foreach ($rows as $row) {
                    $spaceId = $personalSpaceByUser[(int) $row->user_id] ?? null;
                    if ($spaceId !== null) {
                        DB::table($tableName)->where('id', $row->id)->update(['financial_space_id' => $spaceId]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['financial_calendar_events', 'financial_entries', 'financial_budgets', 'financial_accounts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['financial_space_id']);
                $table->dropIndex([$table->getTable() === '' ? 'financial_space_id' : 'financial_space_id', 'user_id']);
                $table->dropColumn('financial_space_id');
            });
        }

        Schema::dropIfExists('financial_space_capabilities');
        Schema::dropIfExists('financial_space_invitations');
        Schema::dropIfExists('financial_space_memberships');
        Schema::dropIfExists('financial_spaces');
    }
};
