<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use InvalidArgumentException;

/** Pure analytics. Does not amend contracts, post payments, price loans or query other Spaces. */
final class PortfolioEngine
{
    public const VERSION = 'opfin.fi.portfolio.v1';
    public const ENGINE_VERSION = '1.0.0';
    public const MAX_LOANS = 50000;

    public function normalise(array $input): array
    {
        Values::keys($input, ['schema_version', 'as_of', 'source_system', 'population', 'expected_loan_count', 'control_totals_minor', 'loans', 'financials'], 'Portfolio');
        if (($input['schema_version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported portfolio schema version.');
        }
        $asOf = Values::date($input['as_of'] ?? null, 'as_of');
        $source = Values::text($input['source_system'] ?? null, 'source_system', 80);
        $population = Values::text($input['population'] ?? null, 'population', 120);
        $loans = $input['loans'] ?? null;
        if (! is_array($loans) || ! array_is_list($loans) || count($loans) > self::MAX_LOANS) {
            throw new InvalidArgumentException('loans must be a list containing at most 50,000 observations.');
        }
        if (! is_int($input['expected_loan_count'] ?? null) || $input['expected_loan_count'] !== count($loans)) {
            throw new InvalidArgumentException('The source record count does not match the submitted population.');
        }
        $normalised = [];
        $ids = [];
        $totals = [];
        foreach ($loans as $position => $loan) {
            if (! is_array($loan)) {
                throw new InvalidArgumentException('Each loan must be an object.');
            }
            $row = $this->loan($loan, $asOf, $position + 1);
            $key = 'loan:'.$row['loan_ref'];
            if (isset($ids[$key])) {
                throw new InvalidArgumentException('A source loan reference occurs more than once. No rows were silently merged.');
            }
            $ids[$key] = true;
            $currency = $row['currency'];
            $totals[$currency] = Values::add($totals[$currency] ?? 0, $row['principal_outstanding_minor']);
            $normalised[] = $row;
        }
        $controls = $input['control_totals_minor'] ?? null;
        if (! is_array($controls) || ($controls !== [] && array_is_list($controls))) {
            throw new InvalidArgumentException('Provide source control totals separately for each currency.');
        }
        $expected = [];
        foreach ($controls as $currency => $amount) {
            $expected[Values::currency($currency)] = Values::amount($amount, 'control_totals_minor');
        }
        ksort($totals);
        ksort($expected);
        if ($totals !== $expected) {
            throw new InvalidArgumentException('The currency-separated principal totals do not reconcile to the source controls.');
        }
        usort($normalised, fn (array $a, array $b): int => strcmp($a['loan_ref'], $b['loan_ref']));
        $financials = $this->financials($input['financials'] ?? []);
        foreach ($financials as $financial) {
            if ($financial['period_end'] > $asOf) {
                throw new InvalidArgumentException('Accounting observations must not extend beyond the reporting date.');
            }
        }

        return ['schema_version' => self::VERSION, 'as_of' => $asOf, 'source_system' => $source, 'population' => $population,
            'expected_loan_count' => count($normalised), 'control_totals_minor' => $totals, 'loans' => $normalised, 'financials' => $financials];
    }

    private function loan(array $loan, string $asOf, int $line): array
    {
        Values::keys($loan, ['loan_ref', 'borrower_ref', 'currency', 'principal_outstanding_minor', 'original_principal_minor', 'originated_on',
            'product', 'branch', 'officer', 'sector', 'funding_source', 'guarantor_ref', 'instalments', 'restructured', 'unlikely_to_pay',
            'unlikely_to_pay_evidence_ref', 'written_off_minor', 'regulatory_npl', 'regulatory_classification_ref'], "Loan {$line}");
        $result = [
            'loan_ref' => Values::text($loan['loan_ref'] ?? null, 'loan_ref'),
            'borrower_ref' => Values::text($loan['borrower_ref'] ?? null, 'borrower_ref'),
            'currency' => Values::currency($loan['currency'] ?? null),
            'principal_outstanding_minor' => Values::amount($loan['principal_outstanding_minor'] ?? null, 'principal_outstanding_minor'),
            'original_principal_minor' => Values::amount($loan['original_principal_minor'] ?? null, 'original_principal_minor'),
            'originated_on' => Values::date($loan['originated_on'] ?? null, 'originated_on'),
            'restructured' => Values::flag($loan['restructured'] ?? false, 'restructured'),
            'unlikely_to_pay' => Values::flag($loan['unlikely_to_pay'] ?? false, 'unlikely_to_pay'),
            'unlikely_to_pay_evidence_ref' => null,
            'written_off_minor' => Values::amount($loan['written_off_minor'] ?? 0, 'written_off_minor'),
            'regulatory_npl' => null,
            'regulatory_classification_ref' => null,
        ];
        if ($result['originated_on'] > $asOf || $result['principal_outstanding_minor'] > $result['original_principal_minor']) {
            throw new InvalidArgumentException('Origination cannot follow the reporting date and outstanding principal cannot exceed original principal. Use a separately identified facility for capitalised/rebooked principal.');
        }
        if ($result['unlikely_to_pay']) {
            $result['unlikely_to_pay_evidence_ref'] = Values::text($loan['unlikely_to_pay_evidence_ref'] ?? null, 'unlikely_to_pay_evidence_ref');
        }
        if (array_key_exists('regulatory_npl', $loan) && $loan['regulatory_npl'] !== null) {
            $result['regulatory_npl'] = Values::flag($loan['regulatory_npl'], 'regulatory_npl');
            $result['regulatory_classification_ref'] = Values::text($loan['regulatory_classification_ref'] ?? null, 'regulatory_classification_ref');
        }
        foreach (['product', 'branch', 'officer', 'sector', 'funding_source', 'guarantor_ref'] as $dimension) {
            $result[$dimension] = isset($loan[$dimension]) ? Values::text($loan[$dimension], $dimension) : null;
        }
        $instalments = $loan['instalments'] ?? null;
        $result['instalments'] = null;
        $result['dpd'] = null;
        $result['arrears_minor'] = null;
        if ($instalments !== null) {
            if (! is_array($instalments) || ! array_is_list($instalments) || count($instalments) > 1200) {
                throw new InvalidArgumentException('instalments must be a complete list of at most 1,200 instalments, or null for unavailable data.');
            }
            $remaining = 0;
            $arrears = 0;
            $dpd = 0;
            $schedule = [];
            $seen = [];
            foreach ($instalments as $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('Every instalment must be an object.');
                }
                Values::keys($item, ['instalment_ref', 'due_on', 'principal_due_minor', 'interest_due_minor', 'principal_paid_minor', 'interest_paid_minor'], 'Instalment');
                $id = Values::text($item['instalment_ref'] ?? null, 'instalment_ref');
                if (isset($seen['id:'.$id])) {
                    throw new InvalidArgumentException('Duplicate instalment reference.');
                }
                $seen['id:'.$id] = true;
                $due = Values::date($item['due_on'] ?? null, 'due_on');
                if ($due < $result['originated_on']) {
                    throw new InvalidArgumentException('An instalment cannot be due before origination.');
                }
                $parsed = ['instalment_ref' => $id, 'due_on' => $due];
                foreach (['principal_due_minor', 'interest_due_minor', 'principal_paid_minor', 'interest_paid_minor'] as $field) {
                    $parsed[$field] = Values::amount($item[$field] ?? null, $field);
                }
                if ($parsed['principal_paid_minor'] > $parsed['principal_due_minor'] || $parsed['interest_paid_minor'] > $parsed['interest_due_minor']) {
                    throw new InvalidArgumentException('Paid allocations cannot exceed the corresponding due allocations.');
                }
                $unpaidPrincipal = $parsed['principal_due_minor'] - $parsed['principal_paid_minor'];
                $unpaid = Values::add($unpaidPrincipal, $parsed['interest_due_minor'] - $parsed['interest_paid_minor']);
                $remaining = Values::add($remaining, $unpaidPrincipal);
                if ($due < $asOf && $unpaid > 0) {
                    $arrears = Values::add($arrears, $unpaid);
                    $dpd = max($dpd, Values::days($due, $asOf));
                }
                $schedule[] = $parsed;
            }
            if ($remaining !== $result['principal_outstanding_minor']) {
                throw new InvalidArgumentException('The complete remaining principal schedule does not reconcile with outstanding principal.');
            }
            usort($schedule, fn (array $a, array $b): int => [$a['due_on'], $a['instalment_ref']] <=> [$b['due_on'], $b['instalment_ref']]);
            $result['instalments'] = $schedule;
            $result['dpd'] = $dpd;
            $result['arrears_minor'] = $arrears;
        }

        return $result;
    }

    private function financials(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items) || count($items) > 100) {
            throw new InvalidArgumentException('financials must be a bounded list of source-referenced accounting summaries.');
        }
        $out = [];
        $seen = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each accounting summary must be an object.');
            }
            Values::keys($item, ['currency', 'product', 'period_start', 'period_end', 'source_reference', 'cash_minor', 'inflows_30d_minor', 'outflows_30d_minor',
                'revenue_minor', 'funding_cost_minor', 'operating_cost_minor', 'credit_loss_minor'], 'Accounting summary');
            $row = ['currency' => Values::currency($item['currency'] ?? null), 'product' => isset($item['product']) ? Values::text($item['product'], 'product') : null,
                'period_start' => Values::date($item['period_start'] ?? null, 'period_start'), 'period_end' => Values::date($item['period_end'] ?? null, 'period_end'),
                'source_reference' => Values::text($item['source_reference'] ?? null, 'source_reference')];
            if ($row['period_start'] > $row['period_end']) {
                throw new InvalidArgumentException('Accounting period is reversed.');
            }
            $identity = Values::hash([$row['currency'], $row['product']]);
            if (isset($seen[$identity])) {
                throw new InvalidArgumentException('Only one accounting summary per currency/product is allowed in a snapshot.');
            }
            $seen[$identity] = true;
            foreach (['cash_minor', 'inflows_30d_minor', 'outflows_30d_minor', 'revenue_minor', 'funding_cost_minor', 'operating_cost_minor', 'credit_loss_minor'] as $field) {
                $row[$field] = isset($item[$field]) ? Values::amount($item[$field], $field) : null;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function analyse(array $dataset, int $managementNplDays = 90): array
    {
        if ($managementNplDays < 1 || $managementNplDays > 365) {
            throw new InvalidArgumentException('Management NPL threshold must be between 1 and 365 days.');
        }
        $currencies = [];
        $concentrations = [];
        $vintages = [];
        foreach ($dataset['loans'] as $loan) {
            $currency = $loan['currency'];
            $amount = $loan['principal_outstanding_minor'];
            $m = $currencies[$currency] ?? $this->emptyMetrics();
            $m['loan_count']++;
            $m['gross_principal_minor'] = Values::add($m['gross_principal_minor'], $amount);
            $m['written_off_minor'] = Values::add($m['written_off_minor'], $loan['written_off_minor']);
            if ($loan['restructured']) {
                $m['restructured_principal_minor'] = Values::add($m['restructured_principal_minor'], $amount);
            }
            if ($loan['dpd'] === null && $amount > 0) {
                $m['unknown_delinquency_count']++;
                $m['unknown_delinquency_principal_minor'] = Values::add($m['unknown_delinquency_principal_minor'], $amount);
            } else {
                $m['arrears_minor'] = Values::add($m['arrears_minor'], $loan['arrears_minor'] ?? 0);
                foreach ([0, 7, 30, 90] as $threshold) {
                    if ($loan['dpd'] !== null && $loan['dpd'] > $threshold) {
                        $m['par'.$threshold.'_principal_minor'] = Values::add($m['par'.$threshold.'_principal_minor'], $amount);
                    }
                }
            }
            if ($loan['unlikely_to_pay'] || ($loan['dpd'] !== null && $loan['dpd'] >= $managementNplDays)) {
                $m['management_npl_principal_minor'] = Values::add($m['management_npl_principal_minor'], $amount);
            }
            if ($loan['regulatory_npl'] === null && $amount > 0) {
                $m['unknown_regulatory_classification_count']++;
            } elseif ($loan['regulatory_npl'] === true) {
                $m['source_reported_npl_principal_minor'] = Values::add($m['source_reported_npl_principal_minor'], $amount);
            }
            $currencies[$currency] = $m;
            foreach (['product', 'branch', 'officer', 'sector', 'funding_source', 'borrower_ref', 'guarantor_ref'] as $dimension) {
                $value = $loan[$dimension] ?? '(unreported)';
                $key = Values::hash([$currency, $dimension, $value]);
                $group = $concentrations[$key] ?? ['currency' => $currency, 'dimension' => $dimension, 'value' => $value, 'loan_count' => 0, 'principal_minor' => 0, 'par30_principal_minor' => 0, 'unknown_count' => 0];
                $group['loan_count']++;
                $group['principal_minor'] = Values::add($group['principal_minor'], $amount);
                if ($loan['dpd'] === null && $amount > 0) {
                    $group['unknown_count']++;
                } elseif ($loan['dpd'] > 30) {
                    $group['par30_principal_minor'] = Values::add($group['par30_principal_minor'], $amount);
                }
                $concentrations[$key] = $group;
            }
            $cohort = substr($loan['originated_on'], 0, 7);
            $key = $currency.':'.$cohort;
            $v = $vintages[$key] ?? ['currency' => $currency, 'origination_month' => $cohort, 'loan_count' => 0, 'observed_original_principal_minor' => 0, 'current_principal_minor' => 0,
                'par30_principal_minor' => 0, 'unknown_count' => 0, 'interpretation' => 'Observed snapshot cohort; not a lifetime default or loss rate.'];
            $v['loan_count']++;
            $v['observed_original_principal_minor'] = Values::add($v['observed_original_principal_minor'], $loan['original_principal_minor']);
            $v['current_principal_minor'] = Values::add($v['current_principal_minor'], $amount);
            if ($loan['dpd'] === null && $amount > 0) {
                $v['unknown_count']++;
            } elseif ($loan['dpd'] > 30) {
                $v['par30_principal_minor'] = Values::add($v['par30_principal_minor'], $amount);
            }
            $vintages[$key] = $v;
        }
        foreach ($currencies as &$m) {
            foreach ([0, 7, 30, 90] as $threshold) {
                $m['par'.$threshold.'_bps'] = $m['unknown_delinquency_count'] > 0 ? null : Values::bps($m['par'.$threshold.'_principal_minor'], $m['gross_principal_minor']);
            }
            $m['management_npl_bps'] = $m['unknown_delinquency_count'] > 0 ? null : Values::bps($m['management_npl_principal_minor'], $m['gross_principal_minor']);
            $m['source_reported_npl_bps'] = $m['unknown_regulatory_classification_count'] > 0 ? null : Values::bps($m['source_reported_npl_principal_minor'], $m['gross_principal_minor']);
        }
        unset($m);
        ksort($currencies);
        $groups = array_values($concentrations);
        usort($groups, fn (array $a, array $b): int => [$a['currency'], $a['dimension'], -$a['principal_minor'], $a['value']] <=> [$b['currency'], $b['dimension'], -$b['principal_minor'], $b['value']]);
        ksort($vintages);
        $financials = [];
        foreach ($dataset['financials'] as $row) {
            $complete = count(array_filter(['revenue_minor', 'funding_cost_minor', 'operating_cost_minor', 'credit_loss_minor'], fn (string $key): bool => $row[$key] === null)) === 0;
            $row['contribution_minor'] = $complete ? Values::add(Values::add($row['revenue_minor'], -$row['funding_cost_minor']), Values::add(-$row['operating_cost_minor'], -$row['credit_loss_minor'])) : null;
            $row['projected_cash_30d_minor'] = $row['cash_minor'] !== null && $row['inflows_30d_minor'] !== null && $row['outflows_30d_minor'] !== null
                ? Values::add(Values::add($row['cash_minor'], $row['inflows_30d_minor']), -$row['outflows_30d_minor']) : null;
            $financials[] = $row;
        }

        return ['engine_version' => self::ENGINE_VERSION, 'as_of' => $dataset['as_of'], 'source_system' => $dataset['source_system'], 'population' => $dataset['population'],
            'source_hash' => Values::hash($dataset), 'currency_metrics' => $currencies, 'concentrations' => $groups, 'vintages' => array_values($vintages), 'financials' => $financials,
            'definitions' => ['par' => 'Outstanding principal of loans with DPD strictly greater than the named threshold / gross outstanding principal.',
                'dpd' => 'Calendar days since the oldest unpaid instalment due before the reporting date; due today is not overdue.',
                'management_npl' => "DPD >= {$managementNplDays} or an evidenced unlikeliness-to-pay flag. Management indicator, not regulatory certification.",
                'source_reported_npl' => 'Classification supplied by the institution with its classification reference; not independently certified by OpFin.',
                'unknown' => 'A null ratio means missing evidence or a zero denominator. Unknown schedules never become zero arrears.',
                'written_off_minor' => 'Source-reported cumulative written-off amount, not current-period loss.',
                'authority' => 'Read-only analytical evidence. The source MIS remains authoritative. No accounting or credit decision has been posted.']];
    }

    private function emptyMetrics(): array
    {
        return array_fill_keys(['loan_count', 'gross_principal_minor', 'arrears_minor', 'par0_principal_minor', 'par7_principal_minor', 'par30_principal_minor', 'par90_principal_minor',
            'management_npl_principal_minor', 'source_reported_npl_principal_minor', 'unknown_delinquency_count', 'unknown_delinquency_principal_minor',
            'unknown_regulatory_classification_count', 'restructured_principal_minor', 'written_off_minor'], 0);
    }

    public function compare(array $previous, array $current): array
    {
        if ($previous['source_system'] !== $current['source_system'] || $previous['population'] !== $current['population'] || $previous['as_of'] >= $current['as_of']) {
            throw new InvalidArgumentException('Comparison requires the same source/population and a strictly later reporting date.');
        }
        $old = [];
        $new = [];
        foreach ($previous['loans'] as $row) {
            $old['loan:'.$row['loan_ref']] = $row;
        }
        foreach ($current['loans'] as $row) {
            $new['loan:'.$row['loan_ref']] = $row;
        }
        $matrix = [];
        $bridge = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($a !== null && $b !== null && ($a['currency'] !== $b['currency'] || $a['borrower_ref'] !== $b['borrower_ref'] || $a['originated_on'] !== $b['originated_on'])) {
                throw new InvalidArgumentException('A reused source loan reference changed currency, borrower or origination. Correct the source identity before comparing.');
            }
            $currency = ($b ?? $a)['currency'];
            $from = $a === null ? 'not_previously_reported' : $this->bucket($a);
            $to = $b === null ? 'not_currently_reported' : $this->bucket($b);
            $mk = $currency.':'.$from.':'.$to;
            $entry = $matrix[$mk] ?? ['currency' => $currency, 'from' => $from, 'to' => $to, 'loan_count' => 0, 'opening_principal_minor' => 0, 'closing_principal_minor' => 0];
            $entry['loan_count']++;
            $entry['opening_principal_minor'] = Values::add($entry['opening_principal_minor'], $a['principal_outstanding_minor'] ?? 0);
            $entry['closing_principal_minor'] = Values::add($entry['closing_principal_minor'], $b['principal_outstanding_minor'] ?? 0);
            $matrix[$mk] = $entry;
            $x = $bridge[$currency] ?? array_fill_keys(['opening_identified_par30_minor', 'closing_identified_par30_minor', 'entered_minor', 'newly_reported_minor', 'exited_minor',
                'not_reported_minor', 'continuing_balance_change_minor', 'unknown_observation_count'], 0);
            $ar = $a !== null && $a['dpd'] !== null && $a['dpd'] > 30;
            $br = $b !== null && $b['dpd'] !== null && $b['dpd'] > 30;
            $av = $ar ? $a['principal_outstanding_minor'] : 0;
            $bv = $br ? $b['principal_outstanding_minor'] : 0;
            $x['opening_identified_par30_minor'] = Values::add($x['opening_identified_par30_minor'], $av);
            $x['closing_identified_par30_minor'] = Values::add($x['closing_identified_par30_minor'], $bv);
            if (($a !== null && $a['dpd'] === null) || ($b !== null && $b['dpd'] === null)) {
                $x['unknown_observation_count']++;
            }
            if ($ar && $br) {
                $x['continuing_balance_change_minor'] = Values::add($x['continuing_balance_change_minor'], $bv - $av);
            } elseif ($br) {
                $field = $a === null ? 'newly_reported_minor' : 'entered_minor';
                $x[$field] = Values::add($x[$field], $bv);
            } elseif ($ar) {
                $field = $b === null ? 'not_reported_minor' : 'exited_minor';
                $x[$field] = Values::add($x[$field], $av);
            }
            $bridge[$currency] = $x;
        }
        ksort($bridge);
        ksort($matrix);

        return ['from' => $previous['as_of'], 'to' => $current['as_of'], 'roll_matrix' => array_values($matrix), 'par30_bridge' => $bridge,
            'interpretation' => 'Exit from an identified bucket is not proof of cash recovery. Missing loans, write-offs, rebooking and unknown schedules require source investigation. No causal NPL reduction is claimed.'];
    }

    public function bucket(array $loan): string
    {
        if ($loan['principal_outstanding_minor'] === 0) {
            return $loan['written_off_minor'] > 0 ? 'zero_balance_with_writeoff' : 'zero_principal';
        }
        $dpd = $loan['dpd'];
        return match (true) {
            $dpd === null => 'unknown', $dpd === 0 => 'current', $dpd <= 7 => '1_7', $dpd <= 30 => '8_30', $dpd <= 60 => '31_60', $dpd <= 90 => '61_90', default => '91_plus',
        };
    }

    public function stress(array $financials, int $inflowReductionBps, int $outflowIncreaseBps): array
    {
        if ($inflowReductionBps < 0 || $inflowReductionBps > 10000 || $outflowIncreaseBps < 0 || $outflowIncreaseBps > 10000) {
            throw new InvalidArgumentException('Scenario changes must be between 0 and 10,000 basis points.');
        }
        $results = [];
        foreach ($financials as $row) {
            $available = $row['cash_minor'] !== null && $row['inflows_30d_minor'] !== null && $row['outflows_30d_minor'] !== null;
            $inflows = $available ? intdiv($row['inflows_30d_minor'] * (10000 - $inflowReductionBps), 10000) : null;
            $outflows = $available ? Values::add($row['outflows_30d_minor'], intdiv($row['outflows_30d_minor'] * $outflowIncreaseBps, 10000)) : null;
            $results[] = ['currency' => $row['currency'], 'product' => $row['product'], 'projected_cash_30d_minor' => $available ? Values::add(Values::add($row['cash_minor'], $inflows), -$outflows) : null,
                'source_reference' => $row['source_reference'], 'inputs_available' => $available];
        }
        return ['scenario_only' => true, 'inflow_reduction_bps' => $inflowReductionBps, 'outflow_increase_bps' => $outflowIncreaseBps, 'results' => $results,
            'interpretation' => 'A deterministic sensitivity test, not a prediction, accounting entry or regulatory liquidity calculation.'];
    }
}
