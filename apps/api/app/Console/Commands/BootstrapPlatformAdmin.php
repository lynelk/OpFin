<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StaffCredentialService;
use Illuminate\Console\Command;

class BootstrapPlatformAdmin extends Command
{
    protected $signature = 'opfin:admin:bootstrap
        {--email= : Email address the administrator signs in with}
        {--phone= : Phone number (0XXXXXXXXX, 256XXXXXXXXX or +256XXXXXXXXX)}
        {--first-name= : First name}
        {--last-name= : Last name}
        {--force : Set a new one-time password even if the account is already a platform administrator}';

    protected $description = 'Create or promote the owner platform administrator with a one-time password typed at a hidden prompt';

    public function handle(StaffCredentialService $credentials): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give a valid --email.');

            return self::FAILURE;
        }
        $phone = $this->option('phone') !== null ? self::normalisePhone((string) $this->option('phone')) : null;
        if ($this->option('phone') !== null && $phone === null) {
            $this->error('Give the phone as 0XXXXXXXXX, 256XXXXXXXXX or +256XXXXXXXXX.');

            return self::FAILURE;
        }

        $byEmail = User::withoutGlobalScopes()->whereRaw('lower(email) = ?', [$email])->first();
        $byPhone = $phone !== null ? User::withoutGlobalScopes()->where('phone', $phone)->first() : null;
        if ($byEmail && $byPhone && $byEmail->id !== $byPhone->id) {
            $this->error('That email and that phone belong to two different accounts. Resolve this before continuing.');

            return self::FAILURE;
        }
        $user = $byEmail ?? $byPhone;
        if ($user !== null && $user->email !== null && mb_strtolower($user->email) !== $email) {
            $this->error('The account with this phone already uses a different email. Run the command with that email, or correct it first.');

            return self::FAILURE;
        }
        if ($user === null && $phone === null) {
            $this->error('No account has this email. Give --phone to create one: every OpFin account needs a phone number.');

            return self::FAILURE;
        }
        if ($user?->trashed()) {
            $this->error('That account was deleted. Use a different email and phone.');

            return self::FAILURE;
        }
        if ($user !== null && $user->role === User::ROLE_PLATFORM_ADMIN && ! $this->option('force')) {
            $this->info('This account is already a platform administrator. Nothing changed. Use --force, or opfin:admin:reset-password, to set a new one-time password.');

            return self::SUCCESS;
        }

        $password = $this->readOneTimePassword($credentials);
        if ($password === null) {
            return self::FAILURE;
        }

        $created = $user === null;
        $previousRole = $user?->role;
        if ($created) {
            $first = trim((string) $this->option('first-name'));
            $last = trim((string) $this->option('last-name'));
            $user = new User;
            $user->forceFill([
                'name' => trim($first.' '.$last) !== '' ? trim($first.' '.$last) : 'OpFin administrator',
                'first_name' => $first !== '' ? $first : null,
                'last_name' => $last !== '' ? $last : null,
                'phone' => $phone,
                'email' => $email,
            ]);
        } elseif ($user->email === null) {
            $user->email = $email;
        }
        $user->role = User::ROLE_PLATFORM_ADMIN;
        $user->password = $password;
        $user->save();

        $credentials->setOneTimePassword($user, $password, 'auth.admin_bootstrapped', [
            'created' => $created, 'previous_role' => $previousRole, 'via' => 'opfin:admin:bootstrap',
        ]);

        $this->info(($created ? 'Created' : 'Updated').' platform administrator '.$email.'. Sign in with this email and the one-time password; you will be asked to choose a new password straight away.');

        return self::SUCCESS;
    }

    private function readOneTimePassword(StaffCredentialService $credentials): ?string
    {
        $password = (string) $this->secret('One-time password (hidden)');
        $problems = $credentials->oneTimePasswordProblems($password);
        if ($problems !== []) {
            $this->error('That one-time password is too weak. '.implode(' ', $problems));

            return null;
        }
        if ((string) $this->secret('Type it again') !== $password) {
            $this->error('The two entries did not match. Nothing changed.');

            return null;
        }

        return $password;
    }

    /** Same canonical form as the mobile app: 256 followed by nine digits. */
    public static function normalisePhone(string $raw): ?string
    {
        $phone = preg_replace('/[\s\-()]/', '', $raw) ?? '';
        if (preg_match('/^\+?256(\d{9})$/', $phone, $match) === 1) {
            return '256'.$match[1];
        }
        if (preg_match('/^0(\d{9})$/', $phone, $match) === 1) {
            return '256'.$match[1];
        }

        return null;
    }
}
