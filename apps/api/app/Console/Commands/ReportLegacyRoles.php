<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report for the legacy role normalisation. Run it before deploying
 * the migration to see what will change, and afterwards to work through the
 * institution administrators waiting for a reviewed staff role.
 */
class ReportLegacyRoles extends Command
{
    protected $signature = 'opfin:legacy-roles {--json : Emit machine-readable JSON}';

    protected $description = 'Report legacy user roles, their planned mapping and staff awaiting access review.';

    private const MAP = [
        'Super' => User::ROLE_PLATFORM_ADMIN,
        'Client' => User::ROLE_CUSTOMER,
        'Member' => User::ROLE_CUSTOMER,
        'Admin' => User::ROLE_STAFF_PENDING_REVIEW,
    ];

    public function handle(): int
    {
        $known = array_merge(User::ROLES, [User::ROLE_STAFF_PENDING_REVIEW]);
        $counts = DB::table('users')->select('role', DB::raw('count(*) as total'))->groupBy('role')->orderBy('role')->get();

        $roles = $counts->map(fn ($row): array => [
            'role' => $row->role,
            'users' => (int) $row->total,
            'maps_to' => self::MAP[$row->role] ?? null,
            'status' => isset(self::MAP[$row->role]) ? 'legacy' : (in_array($row->role, $known, true) ? 'current' : 'unrecognised'),
        ])->values()->all();

        $pending = DB::table('users')
            ->whereIn('role', ['Admin', User::ROLE_STAFF_PENDING_REVIEW])
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'email', 'institution_id', 'role'])
            ->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_last4' => $user->phone ? substr((string) $user->phone, -4) : null,
                'email' => $user->email,
                'institution_id' => $user->institution_id,
                'role' => $user->role,
            ])->all();

        if ($this->option('json')) {
            $this->line(json_encode(['roles' => $roles, 'staff_access_review' => $pending], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Role', 'Users', 'Maps to', 'Status'], array_map(fn (array $row): array => [
            $row['role'], $row['users'], $row['maps_to'] ?? '—', $row['status'],
        ], $roles));

        $this->newLine();
        $this->info('Institution administrators awaiting a reviewed staff role: '.count($pending));
        if ($pending !== []) {
            $this->table(['ID', 'Name', 'Phone (last 4)', 'Email', 'Institution', 'Role'], array_map(fn (array $user): array => array_values($user), $pending));
        }

        if (collect($roles)->contains('status', 'unrecognised')) {
            $this->warn('Some roles are not recognised and are not changed by the migration. Review them before deploying.');
        }

        return self::SUCCESS;
    }
}
