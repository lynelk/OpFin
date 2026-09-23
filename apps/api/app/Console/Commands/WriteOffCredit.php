<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use App\Services\CreditWriteOffService;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

class WriteOffCredit extends Command
{
    protected $signature = 'opfin:credit-write-off
        {loan_id : Loan ID}
        {policy_version : Approved write-off policy version}
        {actor_id : Platform admin or operations user ID}
        {--evidence= : JSON object containing approval and recovery evidence}';

    protected $description = 'Write off fully provisioned credit without forgiving the legal obligation.';

    public function handle(CreditWriteOffService $writeOffs): int
    {
        try {
            $actor = User::withoutGlobalScopes()->findOrFail((int) $this->argument('actor_id'));
            if (! in_array($actor->role, [User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS], true)) {
                throw new \InvalidArgumentException('Credit write-off requires a platform admin or operations actor.');
            }

            $loan = Loan::withoutGlobalScopes()->findOrFail((int) $this->argument('loan_id'));
            $evidence = $this->decodeEvidence((string) $this->option('evidence'));
            $writeOff = $writeOffs->writeOff(
                $loan,
                (string) $this->argument('policy_version'),
                $evidence,
                $actor,
            );

            $this->info(
                'Written off principal '.$writeOff->principal_written_off_minor.' '.$writeOff->currency.
                ' for loan '.$writeOff->loan_id.'; legal obligation remains recoverable.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function decodeEvidence(string $value): array
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('Credit write-off requires --evidence JSON.');
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Invalid write-off evidence JSON.', previous: $exception);
        }

        if (! is_array($decoded) || $decoded === []) {
            throw new \InvalidArgumentException('Write-off evidence must be a non-empty JSON object.');
        }

        return $decoded;
    }
}
