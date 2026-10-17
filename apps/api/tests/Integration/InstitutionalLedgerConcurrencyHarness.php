<?php
/**
 * Run ONLY with isolated PostgreSQL QA: OPFIN_QA_ALLOW_CONCURRENCY=1
 * DB_DATABASE must equal opfin_qa. Launch AFTER php artisan migrate:fresh.
 * Two independent child processes attempt the same 80% withdrawal at once.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (getenv('OPFIN_QA_ALLOW_CONCURRENCY') !== '1'
    || ! app()->environment('testing')
    || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'opfin_qa'
    || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Refusing to operate outside explicit disposable PostgreSQL QA.\n");
    exit(40);
}

$rounds = (int) (getenv('OPFIN_QA_CONCURRENCY_ROUNDS') ?: 20);
if ($rounds < 1 || $rounds > 100) {
    exit(41);
}
$owner = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_PLATFORM_ADMIN]);
$accounting = app(\App\Services\InstitutionalExposureAccountingService::class);
$totalCorrect = 0;
for ($i = 0; $i < $rounds; $i++) {
    $institution = \App\Models\Institution::create([
        'name' => 'Disposable Concurrent Lender '.$i,
        'address' => 'Synthetic', 'phone' => '256700001'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'email' => 'concurrency-'.$i.'@example.test',
        'authority_basis' => 'licensed', 'authority_reference' => 'TEST-ONLY',
        'regulator_code' => 'TEST',
    ]);
    $account = $accounting->partnerFundsAccount($institution->id, 'UGX');
    $cash = $accounting->partnerProviderCashAccount($institution->id, 'qa_provider', 'UGX');
    app(\App\Services\ProductionLedgerService::class)->post(
        'stress:test:funding:'.$i, 'partner.funds.received', $owner,
        [
            ['account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 100000],
            ['account_id' => $account->id, 'direction' => 'credit', 'amount_minor' => 100000],
        ], $owner, 'UGX'
    );
    $children = [];
    for ($j = 0; $j < 2; $j++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Fork failed');
        }
        if ($pid === 0) {
            // Never use a PostgreSQL connection inherited by a forked child.
            \Illuminate\Support\Facades\DB::purge('pgsql');
            \Illuminate\Support\Facades\DB::reconnect('pgsql');
            try {
                app(\App\Services\ProductionLedgerService::class)->post(
                    'stress:test:withdraw:'.$i.':'.$j, 'partner.funds.disbursement',
                    \App\Models\User::findOrFail($owner->id),
                    [
                        ['account_id' => $account->id, 'direction' => 'debit', 'amount_minor' => 80000],
                        ['account_id' => $cash->id, 'direction' => 'credit', 'amount_minor' => 80000],
                    ], null, 'UGX'
                );
                exit(0);
            } catch (\InvalidArgumentException $e) {
                if (str_contains($e->getMessage(), 'cannot be overdrawn')
                    || str_contains($e->getMessage(), 'cash cannot be spent')) {
                    exit(10);
                }
                fwrite(STDERR, $e->getMessage()."\n");
                exit(11);
            } catch (\Throwable $e) {
                fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
                exit(12);
            }
        }
        $children[] = $pid;
    }
    $statuses = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $statuses[] = pcntl_wexitstatus($status);
    }
    sort($statuses);
    \Illuminate\Support\Facades\DB::purge('pgsql');
    \Illuminate\Support\Facades\DB::reconnect('pgsql');
    $balance = (int) \Illuminate\Support\Facades\DB::table('ledger_entries')
        ->where('ledger_account_id', $account->id)
        ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) AS balance")
        ->value('balance');
    if ($statuses !== [0, 10] || $balance !== 20000) {
        fwrite(STDERR, 'Concurrency FAIL round '.$i.' statuses='.json_encode($statuses).' held='.$balance."\n");
        exit(15);
    }
    $totalCorrect++;
}
echo 'PASS: '.($totalCorrect * 2).' overlapping withdrawal attempts in '.$totalCorrect
    .' isolated lender rounds. Exactly one posted each round; no custody overdrafts.'.PHP_EOL;
