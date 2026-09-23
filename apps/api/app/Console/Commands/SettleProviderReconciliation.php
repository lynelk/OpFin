<?php

namespace App\Console\Commands;

use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\ProviderSettlementService;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

class SettleProviderReconciliation extends Command
{
    protected $signature = 'opfin:provider-settle
        {run_id : Completed reconciliation run ID}
        {currency : Settlement currency}
        {provider_fee_minor : Provider fee in minor units}
        {bank_net_minor : Signed net amount received from or paid to bank}
        {bank_reference : Bank statement/reference identifier}
        {actor_id : Platform admin or operations user ID}
        {--evidence= : JSON object containing bank/provider settlement evidence}';

    protected $description = 'Clear an exception-free provider reconciliation run into evidenced bank settlement.';

    public function handle(ProviderSettlementService $settlements): int
    {
        try {
            $actor = User::withoutGlobalScopes()->findOrFail((int) $this->argument('actor_id'));
            $this->assertFinanceActor($actor);
            $run = ReconciliationRun::query()->findOrFail((int) $this->argument('run_id'));
            $evidence = $this->decodeEvidence((string) $this->option('evidence'));

            $batch = $settlements->settle(
                $run,
                (string) $this->argument('currency'),
                (int) $this->argument('provider_fee_minor'),
                (int) $this->argument('bank_net_minor'),
                (string) $this->argument('bank_reference'),
                $evidence,
                $actor,
            );

            $this->info(
                'Posted provider settlement batch '.$batch->id.
                ' with bank net '.$batch->bank_net_settlement_minor.' '.$batch->currency.'.'
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
            throw new \InvalidArgumentException('Provider settlement requires --evidence JSON.');
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Invalid provider-settlement evidence JSON.', previous: $exception);
        }

        if (! is_array($decoded) || $decoded === []) {
            throw new \InvalidArgumentException('Provider settlement evidence must be a non-empty JSON object.');
        }

        return $decoded;
    }

    private function assertFinanceActor(User $actor): void
    {
        if (! in_array($actor->role, [User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS], true)) {
            throw new \InvalidArgumentException('Provider settlement requires a platform admin or operations actor.');
        }
    }
}
