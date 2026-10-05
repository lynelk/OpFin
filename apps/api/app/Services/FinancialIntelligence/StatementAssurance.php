<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

/**
 * Independent assurance dimensions for an uploaded statement. They are reported separately so a
 * clean result on one (for example arithmetic) is never read as assurance on another (for example
 * issuer authenticity). Values are codes only; no statement content is stored here.
 */
final class StatementAssurance
{
    public const PENDING_STATUSES = ['queued_for_analysis', 'analysing'];

    public const NOT_PROCESSED_STATUSES = ['rejected_active_content', 'export_required_password_protected', 'rejected_processing_limits'];

    public static function from(string $status, ?array $analysis, array $signals = []): array
    {
        $pending = in_array($status, self::PENDING_STATUSES, true);
        $extraction = match (true) {
            $pending => 'pending',
            $status === 'layout_not_supported' => 'not_yet_supported',
            in_array($status, self::NOT_PROCESSED_STATUSES, true) => 'not_processed',
            $status === 'unreadable' || $analysis === null => 'unreadable',
            default => $analysis['extraction']['coverage'] ?? 'complete',
        };
        $consistency = match (true) {
            $pending => 'pending',
            $analysis === null => 'unable_to_assess',
            ($analysis['financial_checks'] ?? null) === 'consistent' => 'consistent',
            ($analysis['financial_checks'] ?? null) === 'review_required' => 'discrepancies',
            default => 'unable_to_assess',
        };
        $flagged = array_filter($analysis['findings'] ?? [], fn (array $finding): bool => ($finding['severity'] ?? null) === 'review') !== [];
        $review = match (true) {
            $pending => 'pending',
            in_array($extraction, ['not_yet_supported', 'not_processed', 'unreadable'], true) => 'not_applicable',
            $flagged || $signals !== [] || $extraction === 'partial' => 'needs_review',
            default => 'no_issues_detected_by_executed_checks',
        };

        return ['institution_eligibility' => 'approved_at_upload', 'account_authority' => 'declared_by_uploader',
            'extraction' => $extraction, 'financial_consistency' => $consistency, 'source_authenticity' => 'unconfirmed',
            'review' => $review, 'document_signals' => array_values(array_unique($signals))];
    }
}
