<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use App\Services\LoanImpairmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

class AssessLoanImpairment extends Command
{
    protected $signature = 'opfin:impairment-assess
        {loan_id : Loan ID}
        {as_of : Assessment date YYYY-MM-DD}
        {ecl_minor : Approved expected credit loss in minor units}
        {stage : stage_1, stage_2 or stage_3}
        {policy_version : Approved finance/risk policy version}
        {actor_id : Platform admin or operations user ID}
        {--evidence= : JSON object containing model/review evidence}';

    protected $description = 'Record and post a finance/risk-approved loan impairment assessment.';

    public function handle(LoanImpairmentService $impairment): int
    {
        try {
            $actor = User::withoutGlobalScopes()->findOrFail((int) $this->argument('actor_id'));
            $this->assertFinanceActor($actor);
            $loan = Loan::withoutGlobalScopes()->findOrFail((int) $this->argument('loan_id'));
            $evidence = $this->decodeEvidence((string) $this->option('evidence'));

            $assessment = $impairment->assess(
                $loan,
                Carbon::parse((string) $this->argument('as_of')),
                (int) $this->argument('ecl_minor'),
                (string) $this->argument('stage'),
                (string) $this->argument('policy_version'),
                $evidence,
                $actor,
            );

            $this->info(
                'Recorded impairment assessment '.$assessment->id.
                ' with ECL '.$assessment->expected_credit_loss_minor.' '.$assessment->currency.'.'
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
            throw new \InvalidArgumentException('Impairment assessment requires --evidence JSON.');
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Invalid impairment evidence JSON.', previous: $exception);
        }

        if (! is_array($decoded) || $decoded === []) {
            throw new \InvalidArgumentException('Impairment evidence must be a non-empty JSON object.');
        }

        return $decoded;
    }

    private function assertFinanceActor(User $actor): void
    {
        if (! in_array($actor->role, [User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS], true)) {
            throw new \InvalidArgumentException('Impairment assessment requires a platform admin or operations actor.');
        }
    }
}
