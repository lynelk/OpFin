<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const STAFF_ROLES = ['platform_admin', 'operations', 'support', 'employer_admin', 'programme_partner', 'partner_api', 'staff_pending_review'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'password_change_required')) {
                $table->boolean('password_change_required')->default(false);
            }
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
        });

        // The original users table defaulted every new row to the password "password". Rows created
        // without a credential must never be able to sign in with a value anyone can guess.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->change();
        });

        // Staff accounts still holding that default lose it: a random unusable password, revoked
        // sessions and a forced change after recovery by SMS code, email code or the owner command.
        DB::table('users')->whereIn('role', self::STAFF_ROLES)->orderBy('id')->select(['id', 'password'])
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    if (! is_string($user->password) || ! Hash::check('password', $user->password)) {
                        continue;
                    }
                    DB::table('users')->where('id', $user->id)->update([
                        'password' => Hash::make(Str::random(64)),
                        'password_change_required' => true,
                        'updated_at' => now(),
                    ]);
                    DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')
                        ->where('tokenable_id', $user->id)->delete();
                    DB::table('audit_logs')->insert([
                        'event' => 'auth.default_password_removed',
                        'actor_type' => null,
                        'actor_id' => null,
                        'subject_type' => 'App\\Models\\User',
                        'subject_id' => $user->id,
                        'metadata' => json_encode(['reason' => 'staff account still used the legacy default password']),
                        'created_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['password_change_required', 'password_changed_at']);
        });
        // The insecure "password" default is deliberately not restored.
    }
};
