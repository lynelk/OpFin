<?php

declare(strict_types=1);

require __DIR__.'/../apps/api/app/Domain/Essentials/AccountingRules.php';

use App\Domain\Essentials\AccountingRules as R;

$count = 0;
function check(bool $ok, string $label): void
{
    global $count;
    if (! $ok) { throw new RuntimeException('FAIL: '.$label); }
    $count++;
}
function rejects(callable $call, string $label): void
{
    try { $call(); } catch (InvalidArgumentException|RuntimeException $e) { check(true, $label); return; }
    throw new RuntimeException('Expected rejection: '.$label);
}
function item(int $id, string $date, int $p, int $i, int $f): array
{
    return ['id'=>$id, 'due_date'=>$date, 'principal_original_minor'=>$p, 'interest_original_minor'=>$i, 'fees_original_minor'=>$f,
        'principal_outstanding_minor'=>$p, 'interest_outstanding_minor'=>$i, 'fees_outstanding_minor'=>$f,
        'total_outstanding_minor'=>$p+$i+$f];
}
for ($p = 1; $p <= 12; $p++) {
    for ($i = 0; $i <= 4; $i++) {
        for ($f = 0; $f <= 4; $f++) {
            $first = item(1, '2026-10-01', $p, $i, $f);
            $second = item(2, '2026-11-01', $p+1, $i+1, $f+1);
            $total = $first['total_outstanding_minor']+$second['total_outstanding_minor'];
            for ($amount = 1; $amount <= $total; $amount++) {
                $result = R::allocate([$second,$first], $amount);
                check($result['principal_minor']+$result['interest_minor']+$result['fees_minor']===$amount, 'exact collected amount');
                check(array_sum(array_column($result['rows'], 'total_outstanding_minor'))===$total-$amount, 'exact remaining debt');
                check(R::reverse($result['rows'], $result['allocations'], $amount)===[$first,$second], 'reversal restores original allocation');
                check($result['policy']===R::ALLOCATION_VERSION, 'versioned policy');
            }
        }
    }
}
check(R::collectionCapacity(100,[['reference'=>'A','status'=>'pending','amount_minor'=>80]])===20, 'pending holds after request ends');
check(R::collectionCapacity(100,[['reference'=>'A','status'=>'exception','amount_minor'=>100]])===0, 'exception stays reserved');
check(R::collectionCapacity(100,[['reference'=>'A','status'=>'future_state','amount_minor'=>100]])===0, 'future state is conservative');
check(R::collectionCapacity(100,[['reference'=>'A','status'=>'failed','amount_minor'=>100]])===100, 'definitive failed collection releases capacity');
rejects(fn()=>R::assertCanCollect(21,100,[['reference'=>'A','status'=>'pending','amount_minor'=>80]]), 'no overlapping overcollection');
rejects(fn()=>R::collectionCapacity(100,[['reference'=>'A','status'=>'pending','amount_minor'=>101]]), 'inconsistent reservations surfaced');
rejects(fn()=>R::collectionCapacity(100,[['reference'=>'A','status'=>'pending','amount_minor'=>1],['reference'=>'A','status'=>'pending','amount_minor'=>1]]), 'duplicate reservation rejected');
foreach ([1.0, '1', true, null, -1] as $invalid) { rejects(fn()=>R::amount($invalid), 'strict money type'); }
rejects(fn()=>R::add(PHP_INT_MAX,1),'overflow rejected');
rejects(fn()=>R::allocate([item(1,'2026-02-30',1,0,0)],1),'invalid real date');
rejects(fn()=>R::allocate([item(1,'2026-02-28',1,0,0)],2),'overpayment rejected');
rejects(fn()=>R::reverse([item(1,'2026-10-01',1,0,0)],[['schedule_item_id'=>1,'principal_minor'=>1,'interest_minor'=>0,'fees_minor'=>0]],1),'no double reversal');
check(R::providerState('funding_release','SUCCESS',true)==='pending','funding success lookup is not a release');
check(R::providerState('funding_release','RELEASED',true)==='success','explicit release finality');
check(R::providerState('collection','REVERSED')==='reversed','reversal preserved');
check(R::providerState('collection','REFUNDED')==='reversed','refund preserved');
check(R::providerState('funding','AUTHORISED')==='success','certified funding authority');
check(R::transition('success','pending')==='success','stale pending cannot undo finality');
check(R::transition('success','failed')==='exception','contradiction retained');
check(R::transition('success','reversed')==='reversed','explicit reversal accepted');
check(R::transition('reversed','success')==='exception','reversed cannot resurrect');
check(R::transition('failed','success')==='exception','late success requires reconciliation');
check(R::transition('exception','success')==='exception','exception needs reviewed resolution');
$exp=R::exposure([
 ['status'=>'lender_funding_pending','principal_minor'=>100,'outstanding_minor'=>0,'schedule'=>[]],
 ['status'=>'lender_reversal_pending','principal_minor'=>50,'outstanding_minor'=>0,'schedule'=>[]],
 ['status'=>'active','principal_minor'=>60,'outstanding_minor'=>65,'schedule'=>[item(1,'2026-09-01',60,3,2)]],
 ['status'=>'fulfilment_failed','principal_minor'=>1000,'outstanding_minor'=>1000,'schedule'=>[]]
], '2026-09-26');
check($exp===['reserved_minor'=>150,'debt_minor'=>65,'exposure_minor'=>215,'due_minor'=>65,'next_due_date'=>'2026-09-01'],'pending and debt remain distinct');
check(R::canonical(['b'=>1,'a'=>['z'=>2,'c'=>3]])===R::canonical(['a'=>['c'=>3,'z'=>2],'b'=>1]),'stable identity');
rejects(fn()=>R::canonical(['amount'=>1.0]),'no floating instruction identity');
echo "PASS: {$count} Essentials arithmetic/state assertions\n";
