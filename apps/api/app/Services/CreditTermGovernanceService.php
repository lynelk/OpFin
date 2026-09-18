<?php

namespace App\Services;

use App\Models\LoanProductTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditTermGovernanceService
{
    public function requestChange(LoanProductTerm $term, User $actor, array $proposed, string $reason): object
    {
        $current = [
            'interest_rate' => (float) $term->interest_rate,
            'interest_type' => $term->interest_type,
            'interest_cycle' => $term->interest_cycle,
            'repayment_frequency' => $term->repayment_frequency,
            'duration' => (int) $term->duration,
            'default_interest_rate' => (float) ($term->default_interest_rate ?? 0),
            'default_interest_cycle' => $term->default_interest_cycle ?? 'monthly',
        ];

        $id = DB::table('credit_term_change_requests')->insertGetId([
            'loan_product_term_id' => $term->id,
            'requested_by' => $actor->id,
            'status' => 'pending_approval',
            'current_terms' => json_encode($current, JSON_THROW_ON_ERROR),
            'proposed_terms' => json_encode($proposed, JSON_THROW_ON_ERROR),
            'reason' => $reason,
            'customer_consent_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('credit_term_change_requests')->find($id);
    }

    public function approve(int $requestId, User $actor, ?string $umraReference, ?string $umraApprovedAt): object
    {
        return DB::transaction(function () use ($requestId, $actor, $umraReference, $umraApprovedAt) {
            $request = DB::table('credit_term_change_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request) {
                throw new InvalidArgumentException('Credit-term change request not found.');
            }
            if ($request->status !== 'pending_approval') {
                throw new InvalidArgumentException('Only pending credit-term changes can be approved.');
            }
            if ($request->requested_by && (int) $request->requested_by === (int) $actor->id) {
                throw new InvalidArgumentException('Maker-checker requires another officer to approve this change.');
            }

            $current = json_decode($request->current_terms, true, 512, JSON_THROW_ON_ERROR);
            $proposed = json_decode($request->proposed_terms, true, 512, JSON_THROW_ON_ERROR);
            $interestChanged = array_key_exists('interest_rate', $proposed)
                && (float) $proposed['interest_rate'] !== (float) ($current['interest_rate'] ?? 0);

            if ($interestChanged && (blank($umraReference) || blank($umraApprovedAt))) {
                throw new InvalidArgumentException('Prior written UMRA approval reference and date are required before an interest-rate change can be approved.');
            }

            DB::table('credit_term_change_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'umra_approval_reference' => $umraReference,
                'umra_approved_at' => $umraApprovedAt,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('credit_term_change_requests')->find($requestId);
        });
    }

    public function apply(int $requestId, User $actor): LoanProductTerm
    {
        return DB::transaction(function () use ($requestId, $actor) {
            $request = DB::table('credit_term_change_requests')->where('id', $requestId)->lockForUpdate()->first();
            if (! $request || $request->status !== 'approved') {
                throw new InvalidArgumentException('Only approved term changes can be applied.');
            }

            $term = LoanProductTerm::query()->lockForUpdate()->findOrFail($request->loan_product_term_id);
            $proposed = json_decode($request->proposed_terms, true, 512, JSON_THROW_ON_ERROR);
            $current = json_decode($request->current_terms, true, 512, JSON_THROW_ON_ERROR);
            $interestChanged = array_key_exists('interest_rate', $proposed)
                && (float) $proposed['interest_rate'] !== (float) ($current['interest_rate'] ?? 0);

            if ($interestChanged && blank($request->umra_approval_reference)) {
                throw new InvalidArgumentException('Interest-rate changes cannot be applied without recorded prior UMRA approval.');
            }

            $allowed = array_intersect_key($proposed, array_flip([
                'interest_rate',
                'interest_type',
                'interest_cycle',
                'repayment_frequency',
                'duration',
                'default_interest_rate',
                'default_interest_cycle',
            ]));

            if ($interestChanged) {
                $allowed['umra_interest_approval_reference'] = $request->umra_approval_reference;
                $allowed['umra_interest_approved_at'] = $request->umra_approved_at;
            }

            $term->forceFill($allowed)->save();

            DB::table('credit_term_change_requests')->where('id', $requestId)->update([
                'status' => 'applied',
                'effective_at' => now(),
                'applied_at' => now(),
                'updated_at' => now(),
            ]);

            app(AuditLogger::class)->record('credit.term_change.applied', $actor, $term, [
                'change_request_id' => $requestId,
                'customer_consent_required_for_existing_contracts' => true,
                'existing_accepted_offers_unchanged' => true,
                'umra_approval_reference' => $request->umra_approval_reference,
            ]);

            return $term->fresh();
        });
    }
}
