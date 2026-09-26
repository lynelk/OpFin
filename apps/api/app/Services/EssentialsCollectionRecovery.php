<?php

namespace App\Services;

use App\Models\EssentialsCollectionInstruction;
use App\Models\EssentialsRepayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Recovery reads and cancellation only before any provider submission. */
class EssentialsCollectionRecovery
{
    public function __construct(private readonly EssentialsCustomerMutex $mutex, private readonly AuditLogger $audit) {}

    public function status(User $user, int $repaymentId): array
    {
        $repayment = EssentialsRepayment::where('user_id', $user->id)->findOrFail($repaymentId);
        $instruction = EssentialsCollectionInstruction::where('repayment_id', $repaymentId)
            ->where('user_id', $user->id)->first();
        return ['repayment_id' => $repayment->id, 'repayment_reference' => $repayment->reference,
            'advance_id' => $repayment->advance_id, 'amount_minor' => $repayment->amount_minor,
            'currency' => $repayment->currency, 'repayment_status' => $repayment->status,
            'instruction_status' => $instruction?->status ?? 'legacy_evidence_required',
            'submitted_at' => $instruction?->submitted_at?->toIso8601String(),
            'observed_at' => $instruction?->observed_at?->toIso8601String(),
            'applied_at' => $instruction?->applied_at?->toIso8601String(),
            'can_cancel_unsubmitted' => $instruction?->status === 'prepared' && $instruction->submitted_at === null,
            'requires_reconciliation' => $instruction === null || ! in_array($instruction->status, ['applied', 'failed', 'reversed'], true)
                || ($instruction->status === 'reversed' && $repayment->status !== 'reversed'),
            'collection_finality_is_not_inferred_from_http_status' => true];
    }

    public function cancelUnsubmitted(User $user, int $repaymentId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Provide a cancellation reason of 1–500 characters.');
        }
        return $this->mutex->run($user->id, function (User $current) use ($repaymentId, $reason): array {
            DB::transaction(function () use ($current, $repaymentId, $reason): void {
                $repayment = EssentialsRepayment::where('user_id', $current->id)->lockForUpdate()->findOrFail($repaymentId);
                $instruction = EssentialsCollectionInstruction::where('repayment_id', $repaymentId)
                    ->where('user_id', $current->id)->lockForUpdate()->firstOrFail();
                if ($instruction->status === 'failed' && $instruction->exception_code === 'cancelled_before_submission') {
                    return;
                }
                if ($instruction->status !== 'prepared' || $instruction->submitted_at !== null) {
                    throw new InvalidArgumentException('A submitted or uncertain collection cannot be cancelled locally. Reconcile its original provider instruction.');
                }
                $instruction->update(['status' => 'failed', 'exception_code' => 'cancelled_before_submission']);
                $repayment->update(['status' => 'failed', 'metadata' => array_merge((array) $repayment->metadata,
                    ['cancelled_before_submission' => true, 'cancellation_reason' => $reason])]);
                $this->audit->record('essentials.collection.cancelled_before_submission', $current, $repayment,
                    ['instruction_id' => $instruction->id, 'reason' => $reason]);
            });
            return $this->status($current, $repaymentId);
        });
    }
}
