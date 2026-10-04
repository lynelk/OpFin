<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Review of uploaded statements by a Space's authorised officers (#128 §8). Decisions are reasoned,
 * append-only and never change source authenticity: a reviewer who clears an arithmetic or layout
 * concern cannot thereby confirm that the issuer produced the document. Document signals alone are
 * never a ground for rejection, and nothing here reports to a credit bureau.
 */
final class StatementReviews
{
    /** decision => [resulting review state, permitted reason codes] */
    public const DECISIONS = [
        'resolved_no_concern' => ['resolved_with_reasons', ['explained_by_customer', 'explained_by_layout', 'verified_with_original', 'other_documented']],
        'resubmission_requested' => ['resubmission_requested', ['unreadable_or_partial', 'period_or_account_mismatch', 'balances_do_not_reconcile']],
        'original_requested' => ['original_requested', ['edited_copy_suspected', 'signals_need_original']],
        'issuer_verification_requested' => ['issuer_verification_requested', ['material_discrepancy', 'high_impact_use']],
        'escalated' => ['escalated', ['needs_senior_review', 'possible_conflict']],
        'rejected' => ['rejected_with_reasons', ['issuer_confirmed_alteration', 'not_the_declared_account', 'uploader_withdrew', 'unreadable_after_resubmission']],
    ];

    /** Review states a reviewer may decide. */
    private const DECIDABLE = ['needs_review', 'escalated', 'appealed', 'issuer_verification_requested', 'resubmission_requested',
        'original_requested', 'no_issues_detected_by_executed_checks'];

    /** Review states that wait on a reviewer and therefore appear in the queue. */
    private const QUEUE = ['needs_review', 'escalated', 'appealed', 'issuer_verification_requested'];

    /** Review states a resubmission replaces. */
    private const REPLACEABLE = ['needs_review', 'resubmission_requested', 'original_requested', 'issuer_verification_requested',
        'escalated', 'appealed', 'no_issues_detected_by_executed_checks'];

    private const NEXT_ACTION = [
        'pending' => 'We are reading this statement. Check back in a few minutes.',
        'needs_review' => 'An authorised reviewer in this Space will look at the items found.',
        'resubmission_requested' => 'Upload a complete statement for the same account and period, and choose this statement as the one it replaces.',
        'original_requested' => 'Upload the statement exactly as your provider issued it, not an edited or re-saved copy.',
        'issuer_verification_requested' => 'The reviewer has asked for confirmation from the issuer. You do not need to do anything yet.',
        'escalated' => 'A senior reviewer in this Space is looking at this statement.',
        'appealed' => 'Your appeal is with a different reviewer.',
        'rejected_with_reasons' => 'This statement cannot be used. If you think this is wrong, you can appeal once.',
        'resolved_with_reasons' => 'Review complete.',
        'superseded_by_resubmission' => 'You replaced this statement with a newer upload.',
    ];

    public function __construct(private readonly Access $access, private readonly AuditLogger $audit) {}

    public function queue(FinancialSpace $space, User $actor, int $page): array
    {
        $role = $this->access->require($space, $actor, 'statement_review');
        $rows = DB::table('fi_statements')->where('financial_space_id', $space->id)->whereNull('revoked_at')
            ->whereNull('original_purged_at')->whereIn('assurance->review', self::QUEUE)->orderBy('id')->forPage($page, 25)->get();
        $this->audit->record('intelligence.statement_review_queue_viewed', $actor, $space, ['page' => $page]);

        return ['items' => $rows->map(fn (object $row): array => $this->queueItem($row, $actor, $role))->all(), 'page' => $page, 'page_size' => 25];
    }

    public function decide(FinancialSpace $space, User $actor, int $statementId, array $data, string $key): array
    {
        $role = $this->access->require($space, $actor, 'statement_review');
        abort_if(trim($key) === '', 422, 'An Idempotency-Key header is required.');
        $key = Values::text($key, 'Idempotency-Key');
        [$state, $reasons] = self::DECISIONS[$data['decision']] ?? abort(422, 'This review decision is not supported.');
        abort_unless(in_array($data['reason_code'], $reasons, true), 422, 'Choose a reason that supports this decision.');
        abort_if($data['decision'] === 'rejected' && $data['reason_code'] === 'issuer_confirmed_alteration' && trim((string) ($data['evidence_reference'] ?? '')) === '',
            422, 'Record the reference of the issuer confirmation.');
        abort_if($data['decision'] === 'resolved_no_concern' && trim((string) ($data['notes'] ?? '')) === '', 422, 'Explain how the concern was resolved.');

        return DB::transaction(function () use ($space, $actor, $role, $statementId, $data, $key, $state): array {
            $row = $this->lockedStatement($space, $statementId);
            $replay = DB::table('fi_statement_reviews')->where('fi_statement_id', $row->id)->where('idempotency_key', $key)->first();
            if ($replay !== null) {
                abort_unless($replay->decision === $data['decision'] && (int) $replay->actor_id === (int) $actor->id, 409, 'This Idempotency-Key was used for a different review.');

                return $this->outcome($row->id, $replay->decision);
            }
            abort_if($row->revoked_at !== null, 409, 'Analysis permission for this statement has been withdrawn.');
            abort_if((int) $row->uploaded_by === (int) $actor->id, 403, 'You cannot review a statement you uploaded.');
            $current = $this->assurance($row)['review'];
            abort_unless(in_array($current, self::DECIDABLE, true), 409, 'This statement is not open for review.');
            abort_if($current === 'escalated' && $role !== 'administrator', 403, 'An escalated review needs a Space administrator.');
            abort_if($current === 'appealed' && $this->lastRejecter($row->id) === (int) $actor->id, 403, 'A different reviewer must decide an appeal.');
            $this->record($row, $actor, $role, $data['decision'], $data['reason_code'], $data['evidence_reference'] ?? null, $data['notes'] ?? null, $key);
            $this->setReview($row, $state);
            $this->audit->record('intelligence.statement_reviewed', $actor, $space,
                ['statement_id' => $row->id, 'decision' => $data['decision'], 'reason_code' => $data['reason_code']]);

            return $this->outcome($row->id, $data['decision']);
        });
    }

    public function appeal(FinancialSpace $space, User $actor, int $statementId, array $data, string $key): array
    {
        $this->access->require($space, $actor, 'statement');
        abort_if(trim($key) === '', 422, 'An Idempotency-Key header is required.');
        $key = Values::text($key, 'Idempotency-Key');

        return DB::transaction(function () use ($space, $actor, $statementId, $data, $key): array {
            $row = $this->lockedStatement($space, $statementId);
            $replay = DB::table('fi_statement_reviews')->where('fi_statement_id', $row->id)->where('idempotency_key', $key)->first();
            if ($replay !== null) {
                abort_unless($replay->decision === 'appealed', 409, 'This Idempotency-Key was used for a different review.');

                return $this->outcome($row->id, 'appealed');
            }
            abort_unless((int) $row->uploaded_by === (int) $actor->id, 403, 'Only the person who uploaded this statement can appeal.');
            abort_unless($this->assurance($row)['review'] === 'rejected_with_reasons', 409, 'Only a rejected statement can be appealed.');
            abort_if(DB::table('fi_statement_reviews')->where('fi_statement_id', $row->id)->where('decision', 'appealed')->exists(), 409,
                'This statement has already been appealed once.');
            $this->record($row, $actor, 'uploader', 'appealed', 'uploader_appeal', null, $data['reason'], $key);
            $this->setReview($row, 'appealed');
            $this->audit->record('intelligence.statement_appealed', $actor, $space, ['statement_id' => $row->id]);

            return $this->outcome($row->id, 'appealed');
        });
    }

    /** Called when the uploader resubmits: the replaced statement keeps its history and leaves the queue. */
    public function supersede(object $previous, User $actor, string $key): void
    {
        if (! in_array($this->assurance($previous)['review'], self::REPLACEABLE, true)) {
            return;
        }
        $this->record($previous, $actor, 'uploader', 'superseded', 'resubmitted', null, null, 'supersede:'.substr(hash('sha256', $key), 0, 48));
        $this->setReview($previous, 'superseded_by_resubmission');
    }

    /** Review history and next step for the statement detail view. Reviewers also see notes and roles. */
    public function forDetail(object $row, User $actor, string $role): array
    {
        $reviewer = in_array($role, ['administrator', 'reviewer'], true) && (int) $row->uploaded_by !== (int) $actor->id;
        $state = $this->assurance($row)['review'];
        $history = DB::table('fi_statement_reviews')->where('fi_statement_id', $row->id)->orderBy('id')->get()
            ->map(fn (object $review): array => ['decision' => $review->decision, 'reason_code' => $review->reason_code, 'decided_at' => $review->created_at]
                + ($reviewer ? ['actor_role' => $review->actor_role, 'evidence_reference' => $review->evidence_reference,
                    'notes' => $review->notes_cipher !== null ? Crypt::decryptString($review->notes_cipher) : null] : []))->all();
        $appealable = (int) $row->uploaded_by === (int) $actor->id && $state === 'rejected_with_reasons'
            && ! DB::table('fi_statement_reviews')->where('fi_statement_id', $row->id)->where('decision', 'appealed')->exists();

        return ['reviews' => $history, 'next_action' => self::NEXT_ACTION[$state] ?? null, 'can_appeal' => $appealable,
            'permitted_decisions' => $reviewer ? $this->permitted($row, $actor, $role, $state) : []];
    }

    private function queueItem(object $row, User $actor, string $role): array
    {
        $assurance = $this->assurance($row);
        $analysis = $row->analysis_cipher !== null ? json_decode(Crypt::decryptString($row->analysis_cipher), true, 512, JSON_THROW_ON_ERROR) : null;
        $issuer = DB::table('fi_issuer_versions')->where('id', $row->issuer_version_id)->first(['legal_name', 'country', 'product_type']);
        $digits = preg_replace('/\D/', '', (string) DB::table('users')->where('id', $row->uploaded_by)->value('phone'));

        return [
            'statement_id' => $row->id,
            'uploader' => $digits !== '' ? 'Member with phone ending '.substr($digits, -4) : 'Member',
            'uploaded_by_you' => (int) $row->uploaded_by === (int) $actor->id,
            'issuer' => $issuer !== null ? (array) $issuer : null,
            'period_start' => $row->period_start, 'period_end' => $row->period_end,
            'assurance' => $assurance,
            'coverage' => $analysis['extraction'] ?? null,
            'findings' => array_map(fn (array $finding): array => ['code' => $finding['code'], 'severity' => $finding['severity'],
                'source_line' => $finding['source_line'] ?? $finding['row'] ?? null], $analysis['findings'] ?? []),
            'permitted_decisions' => $this->permitted($row, $actor, $role, $assurance['review']),
        ];
    }

    private function permitted(object $row, User $actor, string $role, string $state): array
    {
        if ((int) $row->uploaded_by === (int) $actor->id || $row->revoked_at !== null || ! in_array($state, self::DECIDABLE, true)
            || ($state === 'escalated' && $role !== 'administrator')
            || ($state === 'appealed' && $this->lastRejecter($row->id) === (int) $actor->id)) {
            return [];
        }

        return array_map(fn (string $decision): array => ['decision' => $decision, 'reason_codes' => self::DECISIONS[$decision][1]], array_keys(self::DECISIONS));
    }

    private function record(object $row, User $actor, string $role, string $decision, string $reason, ?string $evidence, ?string $notes, string $key): void
    {
        $notes = $notes !== null && trim($notes) !== '' ? Crypt::encryptString(trim($notes)) : null;
        DB::table('fi_statement_reviews')->insert(['fi_statement_id' => $row->id, 'financial_space_id' => $row->financial_space_id,
            'actor_id' => $actor->id, 'actor_role' => $role, 'decision' => $decision, 'reason_code' => $reason,
            'evidence_reference' => $evidence !== null && trim($evidence) !== '' ? trim($evidence) : null, 'notes_cipher' => $notes,
            'idempotency_key' => $key, 'created_at' => now()]);
    }

    /** Only the review dimension changes; source authenticity stays unconfirmed. */
    private function setReview(object $row, string $state): void
    {
        $assurance = $this->assurance($row);
        $assurance['review'] = $state;
        $assurance['source_authenticity'] = 'unconfirmed';
        DB::table('fi_statements')->where('id', $row->id)->update(['assurance' => json_encode($assurance), 'updated_at' => now()]);
    }

    private function assurance(object $row): array
    {
        $assurance = $row->assurance !== null ? json_decode($row->assurance, true, 8, JSON_THROW_ON_ERROR) : null;

        return is_array($assurance) ? $assurance : StatementAssurance::from($row->status, null);
    }

    private function lockedStatement(FinancialSpace $space, int $statementId): object
    {
        $row = DB::table('fi_statements')->where('financial_space_id', $space->id)->where('id', $statementId)->lockForUpdate()->first();
        abort_unless($row !== null, 404);

        return $row;
    }

    private function lastRejecter(int $statementId): ?int
    {
        $actor = DB::table('fi_statement_reviews')->where('fi_statement_id', $statementId)->where('decision', 'rejected')->orderByDesc('id')->value('actor_id');

        return $actor !== null ? (int) $actor : null;
    }

    private function outcome(int $statementId, string $decision): array
    {
        $review = DB::table('fi_statements')->where('id', $statementId)->value('assurance');

        return ['statement_id' => $statementId, 'decision' => $decision,
            'review' => json_decode((string) $review, true)['review'] ?? null, 'source_authenticity' => 'unconfirmed'];
    }
}
