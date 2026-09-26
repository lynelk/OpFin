<?php

declare(strict_types=1);

// Standalone regression suite for deterministic engines. Not a substitute for Laravel/DB/client gates.
$root = dirname(__DIR__).'/apps/api/app/Services/FinancialIntelligence/';
foreach (['Values', 'PortfolioEngine', 'StatementEngine', 'CsvImportReader'] as $file) { require_once $root.$file.'.php'; }

use App\Services\FinancialIntelligence\CsvImportReader;
use App\Services\FinancialIntelligence\PortfolioEngine;
use App\Services\FinancialIntelligence\StatementEngine;
use App\Services\FinancialIntelligence\Values;

$assertions = 0;
function same(mixed $actual, mixed $expected, string $message = ''): void {
    global $assertions; $assertions++;
    if ($actual !== $expected) { throw new RuntimeException($message.' expected '.var_export($expected, true).', got '.var_export($actual, true)); }
}
function fails(callable $work): void {
    global $assertions; $assertions++;
    try { $work(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Expected strict validation failure.');
}
function loan(string $id = 'L1', ?int $days = 0, int $amount = 10000, string $currency = 'UGX'): array {
    $due = (new DateTimeImmutable('2026-09-25'))->modify('-'.($days ?? 0).' days')->format('Y-m-d');
    return ['loan_ref' => $id, 'borrower_ref' => 'B'.$id, 'currency' => $currency, 'original_principal_minor' => $amount, 'principal_outstanding_minor' => $amount,
        'originated_on' => '2025-01-01', 'product' => 'Working capital', 'branch' => 'Test branch', 'regulatory_npl' => ($days ?? 0) >= 90, 'regulatory_classification_ref' => 'synthetic-policy-v1',
        'instalments' => $days === null ? null : [['instalment_ref' => 'I1', 'due_on' => $due, 'principal_due_minor' => $amount,
            'interest_due_minor' => 0, 'principal_paid_minor' => 0, 'interest_paid_minor' => 0]]];
}
function portfolio(array $loans): array {
    $totals = []; foreach ($loans as $loan) { $totals[$loan['currency']] = ($totals[$loan['currency']] ?? 0) + $loan['principal_outstanding_minor']; }
    return ['schema_version' => PortfolioEngine::VERSION, 'as_of' => '2026-09-25', 'source_system' => 'Synthetic MIS', 'population' => 'Synthetic full loan book',
        'expected_loan_count' => count($loans), 'control_totals_minor' => $totals, 'loans' => $loans, 'financials' => []];
}
function statement(): array {
    return ['currency' => 'UGX', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'opening_balance_minor' => 1000, 'closing_balance_minor' => 1250,
        'transactions' => [['date' => '2026-09-02', 'reference' => 'R1', 'description' => 'Salary', 'direction' => 'credit', 'amount_minor' => 500, 'balance_minor' => 1500],
            ['date' => '2026-09-03', 'reference' => 'R2', 'description' => 'Rent', 'direction' => 'debit', 'amount_minor' => 250, 'balance_minor' => 1250]]];
}
$engine = new PortfolioEngine;
$tests = [];
$tests['strict integer minor units and overflow'] = function (): void {
    same(Values::amount('900000000000000', 'x'), Values::MAX_MINOR);
    same(Values::amount('-12', 'x', true), -12);
    foreach ([1.0, '1.0', '01', '1e3', '1,000', null, true, -1, 900000000000001] as $v) { fails(fn () => Values::amount($v, 'x')); }
    fails(fn () => Values::add(Values::MAX_MINOR, 1)); same(Values::bps(1, 3), 3333); same(Values::bps(0, 0), null);
};
$tests['strict dates, booleans, canonical fingerprints'] = function (): void {
    same(Values::date('2024-02-29', 'd'), '2024-02-29');
    foreach (['2026-02-29', '2026-9-1', '2026-13-01', 'yesterday'] as $d) { fails(fn () => Values::date($d, 'd')); }
    same(Values::hash(['a' => 1, 'b' => 2]), Values::hash(['b' => 2, 'a' => 1]));
    fails(fn () => Values::hash(['amount' => 1.1])); fails(fn () => Values::flag('false', 'x'));
};
$tests['PAR strict thresholds and management NPL inclusive threshold'] = function () use ($engine): void {
    $data = $engine->normalise(portfolio([loan('A', 0), loan('B', 7), loan('C', 30), loan('D', 31), loan('E', 90), loan('F', 91)]));
    $m = $engine->analyse($data)['currency_metrics']['UGX'];
    same($m['gross_principal_minor'], 60000); same($m['par0_principal_minor'], 50000); same($m['par7_principal_minor'], 40000);
    same($m['par30_principal_minor'], 30000); same($m['par90_principal_minor'], 10000); same($m['management_npl_principal_minor'], 20000);
    same($m['par30_bps'], 5000); same($m['source_reported_npl_bps'], 3333);
};
$tests['privacy counts borrowers rather than multiple loans to one borrower'] = function () use ($engine): void {
    $loans = []; foreach (range(1, 8) as $i) { $l = loan('S'.$i); $l['borrower_ref'] = 'ONE-BORROWER'; $loans[] = $l; }
    $m = $engine->analyse($engine->normalise(portfolio($loans)))['currency_metrics']['UGX'];
    same($m['loan_count'], 8); same($m['distinct_borrower_count'], 1);
};
$tests['unknown schedules cannot become performing loans'] = function () use ($engine): void {
    $m = $engine->analyse($engine->normalise(portfolio([loan('A', null), loan('B', 31)])))['currency_metrics']['UGX'];
    same($m['unknown_delinquency_count'], 1); same($m['par30_principal_minor'], 10000); same($m['par30_bps'], null); same($m['management_npl_bps'], null);
};
$tests['regulatory source classification is independently unknown'] = function () use ($engine): void {
    $a = loan(); unset($a['regulatory_npl'], $a['regulatory_classification_ref']);
    $m = $engine->analyse($engine->normalise(portfolio([$a])))['currency_metrics']['UGX']; same($m['par30_bps'], 0); same($m['source_reported_npl_bps'], null);
    $a['regulatory_npl'] = false; fails(fn () => $engine->normalise(portfolio([$a])));
};
$tests['evidenced unlikeliness to pay is not inferred from being current'] = function () use ($engine): void {
    $a = loan(); $a['unlikely_to_pay'] = true; fails(fn () => $engine->normalise(portfolio([$a])));
    $a['unlikely_to_pay_evidence_ref'] = 'review-1'; $m = $engine->analyse($engine->normalise(portfolio([$a])))['currency_metrics']['UGX'];
    same($m['par30_bps'], 0); same($m['management_npl_bps'], 10000);
};
$tests['record count, duplicate identity and source totals are blocking controls'] = function () use ($engine): void {
    $p = portfolio([loan()]); $p['expected_loan_count'] = 2; fails(fn () => $engine->normalise($p));
    $p = portfolio([loan()]); $p['control_totals_minor']['UGX'] = 9999; fails(fn () => $engine->normalise($p));
    fails(fn () => $engine->normalise(portfolio([loan(), loan()])));
    $p = portfolio([loan()]); $p['control_totals_minor']['USD'] = 0; fails(fn () => $engine->normalise($p));
};
$tests['complete schedule and allocation reconciliation'] = function () use ($engine): void {
    $a = loan(); $a['instalments'][0]['principal_paid_minor'] = 1; fails(fn () => $engine->normalise(portfolio([$a])));
    $a['principal_outstanding_minor']--; same($engine->normalise(portfolio([$a]))['loans'][0]['dpd'], 0);
    $a['instalments'][0]['principal_paid_minor'] = 10001; fails(fn () => $engine->normalise(portfolio([$a])));
    $a = loan(); $a['instalments'] = []; fails(fn () => $engine->normalise(portfolio([$a])));
};
$tests['interest arrears count even with paid principal allocations'] = function () use ($engine): void {
    $a = loan('A', 12); $a['instalments'][0]['interest_due_minor'] = 100; $a['instalments'][0]['principal_paid_minor'] = 10000;
    $a['instalments'][] = ['instalment_ref' => 'I2', 'due_on' => '2026-10-01', 'principal_due_minor' => 10000, 'interest_due_minor' => 0, 'principal_paid_minor' => 0, 'interest_paid_minor' => 0];
    $a['original_principal_minor'] = 20000; $r = $engine->normalise(portfolio([$a]))['loans'][0]; same($r['dpd'], 12); same($r['arrears_minor'], 100);
};
$tests['no demographic or undeclared source fields accepted'] = function () use ($engine): void {
    $a = loan(); $a['gender'] = 'X'; fails(fn () => $engine->normalise(portfolio([$a])));
    $p = portfolio([loan()]); $p['hidden_score'] = 99; fails(fn () => $engine->normalise($p));
};
$tests['currency separation and empty portfolio'] = function () use ($engine): void {
    $a = $engine->analyse($engine->normalise(portfolio([loan('A', 31, 20000, 'UGX'), loan('B', 0, 100, 'USD')])));
    same($a['currency_metrics']['UGX']['par30_bps'], 10000); same($a['currency_metrics']['USD']['par30_bps'], 0);
    same($engine->analyse($engine->normalise(portfolio([])))['currency_metrics'], []);
};
$tests['snapshot missing loans are not called cured or repaid'] = function () use ($engine): void {
    $old = $engine->normalise(portfolio([loan('A', 40)])); $p = portfolio([]); $p['as_of'] = '2026-09-26';
    $result = $engine->compare($old, $engine->normalise($p)); same($result['roll_matrix'][0]['to'], 'not_currently_reported');
    same($result['par30_bridge']['UGX']['not_reported_minor'], 10000); same($result['par30_bridge']['UGX']['exited_minor'], 0);
};
$tests['snapshot identity and chronology must be stable'] = function () use ($engine): void {
    $p = portfolio([loan()]); $a = $engine->normalise($p); fails(fn () => $engine->compare($a, $a));
    $p['as_of'] = '2026-09-26'; $p['loans'][0]['borrower_ref'] = 'another'; $b = $engine->normalise($p); fails(fn () => $engine->compare($a, $b));
};
$tests['accounting costs must exist before product contribution'] = function () use ($engine): void {
    $p = portfolio([loan()]); $p['financials'] = [['currency' => 'UGX', 'period_start' => '2026-09-01', 'period_end' => '2026-09-25', 'source_reference' => 'synthetic-trial-balance',
        'revenue_minor' => 1000, 'funding_cost_minor' => 100, 'operating_cost_minor' => 300, 'credit_loss_minor' => 200, 'cash_minor' => 2000, 'inflows_30d_minor' => 1000, 'outflows_30d_minor' => 500]];
    $d = $engine->normalise($p); same($engine->analyse($d)['financials'][0]['contribution_minor'], 400);
    $stress = $engine->stress($d['financials'], 2000, 1000); same($stress['results'][0]['projected_cash_30d_minor'], 2250);
    unset($p['financials'][0]['credit_loss_minor']); same($engine->analyse($engine->normalise($p))['financials'][0]['contribution_minor'], null);
    $p['financials'][0]['period_end'] = '2026-09-26'; fails(fn () => $engine->normalise($p));
};
$tests['statement arithmetic does not authenticate source or income'] = function (): void {
    $r = (new StatementEngine)->analyse(statement()); same($r['financial_checks'], 'consistent');
    same($r['source_authenticity'], 'unconfirmed'); same($r['account_ownership'], 'unconfirmed'); same($r['credit_decision_eligible'], false); same($r['verified_income_minor'], null);
};
$tests['statement tamper concerns remain findings, not fraud verdicts'] = function (): void {
    $s = statement(); $s['transactions'][0]['amount_minor'] = 501;
    $r = (new StatementEngine)->analyse($s); same($r['financial_checks'], 'review_required');
    same(in_array('running_balance_mismatch', array_column($r['findings'], 'code'), true), true);
    $s = statement(); $s['transactions'][1]['reference'] = 'R1'; $r = (new StatementEngine)->analyse($s);
    same(in_array('conflicting_reference', array_column($r['findings'], 'code'), true), true);
};
$tests['statement dates and missing balances'] = function (): void {
    $s = statement(); unset($s['opening_balance_minor']); $r = (new StatementEngine)->analyse($s); same($r['financial_checks'], 'incomplete');
    $s = statement(); $s['transactions'] = array_reverse($s['transactions']); fails(fn () => (new StatementEngine)->analyse($s));
    $s = statement(); $s['transactions'][0]['date'] = '2026-08-01'; fails(fn () => (new StatementEngine)->analyse($s));
};
$tests['CSV strictness, mapping, UTF-8 and row count'] = function (): void {
    $c = new CsvImportReader; same($c->rows("X,Y\n1,2\n", ['a' => 'Y']), [['a' => '2']]);
    fails(fn () => $c->rows("A,a\n1,2\n")); fails(fn () => $c->rows("a,b\n1\n")); fails(fn () => $c->rows("a\n\xFF\n"));
    fails(fn () => $c->rows("a\n1\n", ['b' => 'missing']));
    same($c->rows("a,b\n\"x,y\",\"z\"\n"), [['a' => 'x,y', 'b' => 'z']]);
};
$tests['CSV protects exported formula prefixes'] = function (): void {
    foreach (['=1+1', '+SUM(A1)', '-1', '@formula', '  =bad'] as $s) { same(Values::csvCell($s), "'".$s); }
    same(Values::csvCell('plain'), 'plain'); same(Values::csvCell(null), '');
};
$tests['deterministic randomised exact arithmetic and PAR partition properties'] = function () use ($engine): void {
    mt_srand(260925);
    for ($n = 0; $n < 100; $n++) {
        $loans = []; $sum = 0; $p30 = 0;
        for ($i = 0; $i < 10; $i++) { $days = mt_rand(0, 120); $amount = mt_rand(1, 10000000); $loans[] = loan('L'.$i, $days, $amount); $sum += $amount; if ($days > 30) { $p30 += $amount; } }
        $d = $engine->normalise(portfolio($loans)); $m = $engine->analyse($d)['currency_metrics']['UGX'];
        same($m['gross_principal_minor'], $sum); same($m['par30_principal_minor'], $p30); same($m['par30_bps'], intdiv($p30 * 10000, $sum));
        same($m['par90_principal_minor'] <= $m['par30_principal_minor'] && $m['par30_principal_minor'] <= $m['par7_principal_minor'] && $m['par7_principal_minor'] <= $m['par0_principal_minor'], true);
        $p = portfolio(array_reverse($loans)); same(Values::hash($d), Values::hash($engine->normalise($p)));
    }
};
$failed = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo "PASS {$name}\n"; } catch (Throwable $error) { $failed++; echo "FAIL {$name}: {$error->getMessage()}\n"; }
}
echo json_encode(['suite' => 'standalone-financial-intelligence', 'tests' => count($tests), 'failed' => $failed, 'assertions' => $assertions, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR).PHP_EOL;
exit($failed > 0 ? 1 : 0);
