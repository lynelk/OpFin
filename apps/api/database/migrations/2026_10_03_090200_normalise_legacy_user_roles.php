<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy role names are not recognised by the current authorisation model, so
 * affected accounts could not use the Web back office. Platform super users and
 * customers map directly. Institution administrators have no lender-scoped
 * equivalent: they move to a no-access holding role, lose existing API tokens
 * and wait for a platform administrator's reviewed decision.
 *
 * Run `php artisan opfin:legacy-roles` before deploying to see what will change.
 */
return new class extends Migration
{
    private const MAP = [
        'Super' => 'platform_admin',
        'Client' => 'customer',
        'Member' => 'customer',
        'Admin' => 'staff_pending_review',
    ];

    public function up(): void
    {
        foreach (self::MAP as $legacy => $role) {
            DB::table('users')->where('role', $legacy)->orderBy('id')->select('id')->chunkById(500, function ($users) use ($legacy, $role): void {
                $ids = $users->pluck('id')->all();
                DB::table('users')->whereIn('id', $ids)->update(['role' => $role, 'updated_at' => now()]);

                if ($role === 'staff_pending_review') {
                    DB::table('personal_access_tokens')
                        ->where('tokenable_type', 'App\\Models\\User')
                        ->whereIn('tokenable_id', $ids)
                        ->delete();
                }

                DB::table('audit_logs')->insert(array_map(fn (int $id): array => [
                    'event' => 'user.role.legacy_normalised',
                    'subject_type' => 'App\\Models\\User',
                    'subject_id' => $id,
                    'metadata' => json_encode(['from' => $legacy, 'to' => $role]),
                    'created_at' => now(),
                ], $ids));
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->change();
        });
    }

    public function down(): void
    {
        // Mapped roles are not reversed: two legacy names collapse into
        // `customer`, and the audit log records each account's original role.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Client')->change();
        });
    }
};
