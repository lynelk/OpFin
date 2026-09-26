<?php

declare(strict_types=1);

require __DIR__.'/../apps/api/app/Domain/Essentials/AccountingRules.php';

use App\Domain\Essentials\AccountingRules;

$row = ['id' => 1, 'due_date' => '2026-10-01', 'principal_original_minor' => 10,
    'interest_original_minor' => 5, 'fees_original_minor' => 5,
    'principal_outstanding_minor' => 10, 'interest_outstanding_minor' => 5,
    'fees_outstanding_minor' => 5, 'total_outstanding_minor' => 20];
$default = AccountingRules::allocate([$row], 3);
if ($default['interest_minor'] !== 3 || $default['fees_minor'] !== 0) {
    throw new RuntimeException('The accepted interest-first policy changed.');
}
$explicit = AccountingRules::allocate([$row], 3, 'oldest_due_fees_interest_principal_v1');
if ($explicit['fees_minor'] !== 3) {
    throw new RuntimeException('The explicitly selected alternative policy was not applied.');
}
$rejected = false;
try { AccountingRules::allocate([$row], 3, 'unknown'); }
catch (InvalidArgumentException) { $rejected = true; }
if (! $rejected) { throw new RuntimeException('An unknown allocation policy must not be guessed.'); }
echo "PASS: 3 allocation-policy compatibility assertions\n";
