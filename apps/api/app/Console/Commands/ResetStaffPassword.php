<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffCredentialService;
use Illuminate\Console\Command;

class ResetStaffPassword extends Command
{
    protected $signature = 'opfin:admin:reset-password
        {--email= : Email of the staff account}
        {--phone= : Phone of the staff account, if it has no email}';

    protected $description = 'Owner recovery: set a one-time password on a staff account, end its sessions and force a change at next sign-in';

    public function handle(StaffCredentialService $credentials): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));
        $phone = $this->option('phone') !== null ? BootstrapPlatformAdmin::normalisePhone((string) $this->option('phone')) : null;
        $user = match (true) {
            $email !== '' => User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$email])->first(),
            $phone !== null => User::withoutGlobalScopes()->where('phone', $phone)->first(),
            default => null,
        };
        if ($user === null || $user->trashed()) {
            $this->error('No active account matches. Give the --email (or --phone) of an existing staff account.');

            return self::FAILURE;
        }
        if (! StaffCredentialService::isStaff($user)) {
            $this->error('This is a customer account. Customers reset their PIN with the SMS code in the app.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New one-time password (hidden)');
        $problems = $credentials->oneTimePasswordProblems($password);
        if ($problems !== []) {
            $this->error('That one-time password is too weak. '.implode(' ', $problems));

            return self::FAILURE;
        }
        if ((string) $this->secret('Type it again') !== $password) {
            $this->error('The two entries did not match. Nothing changed.');

            return self::FAILURE;
        }

        $credentials->setOneTimePassword($user, $password, 'auth.admin_password_reset_by_owner', ['via' => 'opfin:admin:reset-password']);
        $this->info('One-time password set. Every existing session for this account has ended. A new password is required at the next sign-in.');

        return self::SUCCESS;
    }
}
