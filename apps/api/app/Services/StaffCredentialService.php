<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One-time passwords for staff accounts. A one-time password is set only by the owner commands
 * (opfin:admin:bootstrap, opfin:admin:reset-password) and always forces a change at the next
 * sign-in, where the full strong-password rule applies.
 */
class StaffCredentialService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function isStaff(User $user): bool
    {
        return $user->role !== User::ROLE_CUSTOMER;
    }

    /** @return list<string> plain-language problems with a one-time password */
    public function oneTimePasswordProblems(string $password): array
    {
        $problems = [];
        if (mb_strlen($password) < 8) {
            $problems[] = 'Use at least 8 characters.';
        }
        if (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password)) {
            $problems[] = 'Use both upper- and lower-case letters.';
        }
        if (! preg_match('/\d/', $password)) {
            $problems[] = 'Include a number.';
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $problems[] = 'Include a symbol.';
        }

        return $problems;
    }

    /** Sets a one-time password, forces a change at next sign-in and ends every existing session. */
    public function setOneTimePassword(User $user, string $password, string $event, array $metadata = []): void
    {
        DB::transaction(function () use ($user, $password, $event, $metadata): void {
            $user->forceFill(['password' => Hash::make($password), 'password_change_required' => true])->save();
            $user->tokens()->delete();
            $this->audit->record($event, null, $user, $metadata);
        });
    }
}
