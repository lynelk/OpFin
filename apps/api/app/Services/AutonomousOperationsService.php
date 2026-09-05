<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutonomousOperationsService
{
    private const BATCH_SIZE = 200;

    public function run(string $trigger = 'scheduled'): array
    {
        $runId = DB::table('autopilot_runs')->insertGetId([
            'status' => 'running',
            'trigger' => $trigger,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $observations = 0;
        $exceptions = 0;
        $actions = 0;

        [$count, $created] = $this->scanKyc($runId);
        $observations += $count;
        $exceptions += $created;

        [$count, $created] = $this->scanConsents($runId);
        $observations += $count;
        $exceptions += $created;

        [$count, $created] = $this->scanPayments($runId);
        $observations += $count;
        $exceptions += $created;

        [$count, $created] = $this->scanReconciliation($runId);
        $observations += $count;
        $exceptions += $created;

        [$count, $created] = $this->scanSupport($runId);
        $observations += $count;
        $exceptions += $created;

        [$count, $created] = $this->scanHardship($runId);
        $observations += $count;
        $exceptions += $created;

        $actions += $this->executeSafeActions($runId);

        $summary = $this->summary();

        DB::table('autopilot_runs')->where('id', $runId)->update([
            'status' => 'completed',
            'observations' => $observations,
            'actions_executed' => $actions,
            'exceptions_created' => $exceptions,
            'summary' => json_encode($summary),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return ['run_id' => $runId] + $summary;
    }

    public function summary(): array
    {
        if (! Schema::hasTable('autopilot_work_items')) {
            return $this->emptySummary();
        }

        $open = DB::table('autopilot_work_items')->where('status', 'open');
        $openCount = (clone $open)->count();
        $automatic = (clone $open)->where('requires_human', false)->count();
        $human = (clone $open)->where('requires_human', true)->count();

        return [
            'autonomy_rate' => $openCount === 0 ? 100.0 : round(($automatic / max(1, $openCount)) * 100, 1),
            'open_exceptions' => $human,
            'open_automatic_items' => $automatic,
            'by_domain' => (clone $open)
                ->select('domain', DB::raw('count(*) as total'))
                ->groupBy('domain')
                ->orderByDesc('total')
                ->get(),
            'by_severity' => (clone $open)
                ->select('severity', DB::raw('count(*) as total'))
                ->groupBy('severity')
                ->orderByDesc('total')
                ->get(),
            // Keep summaries bounded. Never recursively embed the previous run's JSON summary.
            'last_run' => Schema::hasTable('autopilot_runs')
                ? DB::table('autopilot_runs')
                    ->where('status', 'completed')
                    ->orderByDesc('id')
                    ->first(['id', 'status', 'trigger', 'started_at', 'completed_at', 'observations', 'actions_executed', 'exceptions_created'])
                : null,
        ];
    }

    public function workQueue(?string $domain = null): array
    {
        $query = DB::table('autopilot_work_items')->where('status', 'open')->where('requires_human', true);
        if ($domain) {
            $query->where('domain', $domain);
        }

        return $query->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($item) => $this->decodeItem($item))
            ->all();
    }

    public function resolve(int $id, string $actor, string $resolution = 'resolved'): object
    {
        DB::table('autopilot_work_items')->where('id', $id)->where('status', 'open')->update([
            'status' => $resolution,
            'resolved_at' => now(),
            'resolved_by' => $actor,
            'updated_at' => now(),
        ]);

        return DB::table('autopilot_work_items')->find($id);
    }

    private function scanKyc(int $runId): array
    {
        if (! Schema::hasTable('kyc_cases')) {
            return [0, 0];
        }

        $cases = DB::table('kyc_cases')->whereIn('status', ['submitted', 'pending', 'manual_review'])->limit(self::BATCH_SIZE)->get();
        $created = 0;
        foreach ($cases as $case) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'kyc',
                'type' => 'kyc_review_required',
                'severity' => $case->status === 'manual_review' ? 'high' : 'medium',
                'subject_type' => 'kyc_case',
                'subject_reference' => (string) $case->id,
                'title' => 'Identity verification needs review',
                'description' => 'A KYC case is waiting for a reviewer or a provider result.',
                'recommended_action' => 'Review identity evidence and either verify, request correction, or reject with a reason.',
                'confidence' => 1,
                'automation_tier' => 'A1',
                'requires_human' => true,
                'due_at' => now()->addHours(4),
                'context' => ['status' => $case->status, 'user_id' => $case->user_id ?? null],
            ]);
        }

        return [$cases->count(), $created];
    }

    private function scanConsents(int $runId): array
    {
        if (! Schema::hasTable('consents')) {
            return [0, 0];
        }

        $expiring = DB::table('consents')
            ->where('status', 'granted')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(14)])
            ->limit(self::BATCH_SIZE)
            ->get();
        $created = 0;
        foreach ($expiring as $consent) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'consent',
                'type' => 'consent_expiring',
                'severity' => 'low',
                'subject_type' => 'consent',
                'subject_reference' => (string) $consent->id,
                'title' => 'Customer consent is expiring',
                'description' => 'A granted permission will expire soon and may interrupt a product journey.',
                'recommended_action' => 'Prompt the customer contextually for re-consent before the permission expires.',
                'confidence' => 1,
                'automation_tier' => 'A2',
                'requires_human' => false,
                'due_at' => $consent->expires_at,
                'context' => ['purpose' => $consent->purpose ?? null, 'user_id' => $consent->user_id ?? null],
            ]);
        }

        return [$expiring->count(), $created];
    }

    private function scanPayments(int $runId): array
    {
        if (! Schema::hasTable('mobile_money_transactions')) {
            return [0, 0];
        }

        $stale = DB::table('mobile_money_transactions')
            ->whereIn('status', ['pending', 'processing', 'unknown'])
            ->where('updated_at', '<=', now()->subMinutes(15))
            ->limit(self::BATCH_SIZE)
            ->get();
        $created = 0;
        foreach ($stale as $transaction) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'payments',
                'type' => 'payment_status_stale',
                'severity' => 'high',
                'subject_type' => 'mobile_money_transaction',
                'subject_reference' => (string) $transaction->id,
                'title' => 'Payment outcome is ambiguous',
                'description' => 'A money movement has remained unresolved beyond the normal processing window.',
                'recommended_action' => 'Refresh provider status and reconcile before any retry or customer-facing finality message.',
                'confidence' => 1,
                'automation_tier' => 'A3',
                'requires_human' => true,
                'due_at' => now()->addMinutes(30),
                'context' => ['status' => $transaction->status, 'provider' => $transaction->provider ?? null],
            ]);
        }

        return [$stale->count(), $created];
    }

    private function scanReconciliation(int $runId): array
    {
        if (! Schema::hasTable('reconciliation_items')) {
            return [0, 0];
        }

        $items = DB::table('reconciliation_items')->whereIn('status', ['open', 'mismatch', 'unmatched'])->limit(self::BATCH_SIZE)->get();
        $created = 0;
        foreach ($items as $item) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'reconciliation',
                'type' => 'reconciliation_exception',
                'severity' => 'high',
                'subject_type' => 'reconciliation_item',
                'subject_reference' => (string) $item->id,
                'title' => 'Reconciliation exception needs attention',
                'description' => 'Provider and internal records do not yet agree.',
                'recommended_action' => 'Inspect provider evidence and resolve using the documented correction path. Never overwrite the ledger.',
                'confidence' => 1,
                'automation_tier' => 'A1',
                'requires_human' => true,
                'due_at' => now()->addHours(2),
                'context' => ['status' => $item->status],
            ]);
        }

        return [$items->count(), $created];
    }

    private function scanSupport(int $runId): array
    {
        if (! Schema::hasTable('support_cases')) {
            return [0, 0];
        }

        $cases = DB::table('support_cases')
            ->whereIn('status', ['open', 'new', 'escalated'])
            ->where('created_at', '<=', now()->subHours(24))
            ->limit(self::BATCH_SIZE)
            ->get();
        $created = 0;
        foreach ($cases as $case) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'support',
                'type' => 'support_sla_risk',
                'severity' => $case->status === 'escalated' ? 'high' : 'medium',
                'subject_type' => 'support_case',
                'subject_reference' => (string) $case->id,
                'title' => 'Support case is approaching or beyond SLA',
                'description' => 'A customer case has remained unresolved for more than 24 hours.',
                'recommended_action' => 'Review the full customer context, respond, and escalate only if specialist judgment is required.',
                'confidence' => 1,
                'automation_tier' => 'A1',
                'requires_human' => true,
                'due_at' => now()->addHours(1),
                'context' => ['status' => $case->status, 'user_id' => $case->user_id ?? null],
            ]);
        }

        return [$cases->count(), $created];
    }

    private function scanHardship(int $runId): array
    {
        if (! Schema::hasTable('hardship_cases')) {
            return [0, 0];
        }

        $cases = DB::table('hardship_cases')->where('status', 'submitted')->limit(self::BATCH_SIZE)->get();
        $created = 0;
        foreach ($cases as $case) {
            $created += $this->upsertWorkItem($runId, [
                'domain' => 'hardship',
                'type' => 'hardship_review_required',
                'severity' => 'high',
                'subject_type' => 'hardship_case',
                'subject_reference' => (string) $case->id,
                'title' => 'Customer hardship request needs review',
                'description' => 'A customer has requested repayment relief.',
                'recommended_action' => 'Review affordability and requested relief using maker-checker approval. Do not automate the final adverse or relief decision.',
                'confidence' => 1,
                'automation_tier' => 'A5',
                'requires_human' => true,
                'due_at' => now()->addHours(4),
                'context' => ['user_id' => $case->user_id ?? null],
            ]);
        }

        return [$cases->count(), $created];
    }

    private function executeSafeActions(int $runId): int
    {
        $executed = 0;
        $items = DB::table('autopilot_work_items')->where('status', 'open')->where('requires_human', false)->limit(self::BATCH_SIZE)->get();
        foreach ($items as $item) {
            if ($item->type !== 'consent_expiring') {
                continue;
            }

            DB::transaction(function () use ($runId, $item): void {
                $current = DB::table('autopilot_work_items')->where('id', $item->id)->lockForUpdate()->first();
                if (! $current || $current->status !== 'open') {
                    return;
                }

                DB::table('autopilot_action_logs')->insert([
                    'autopilot_run_id' => $runId,
                    'work_item_id' => $item->id,
                    'domain' => $item->domain,
                    'action' => 'queue_contextual_reconsent_prompt',
                    'outcome' => 'queued',
                    'automation_tier' => 'A2',
                    'context' => $item->context,
                    'executed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('autopilot_work_items')->where('id', $item->id)->update([
                    'status' => 'automated',
                    'resolved_at' => now(),
                    'resolved_by' => 'SYSTEM:autopilot',
                    'updated_at' => now(),
                ]);
            });
            $executed++;
        }

        return $executed;
    }

    private function upsertWorkItem(int $runId, array $data): int
    {
        $key = [
            'domain' => $data['domain'],
            'type' => $data['type'],
            'subject_type' => $data['subject_type'],
            'subject_reference' => $data['subject_reference'],
            'status' => 'open',
        ];

        $exists = DB::table('autopilot_work_items')->where($key)->exists();
        DB::table('autopilot_work_items')->updateOrInsert($key, [
            'severity' => $data['severity'],
            'title' => $data['title'],
            'description' => $data['description'],
            'recommended_action' => $data['recommended_action'],
            'confidence' => $data['confidence'],
            'automation_tier' => $data['automation_tier'],
            'requires_human' => $data['requires_human'],
            'context' => json_encode($data['context'] ?? []),
            'due_at' => $data['due_at'] ?? null,
            'autopilot_run_id' => $runId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $exists ? 0 : 1;
    }

    private function decodeItem(object $item): object
    {
        $item->context = $item->context ? json_decode($item->context, true) : [];

        return $item;
    }

    private function emptySummary(): array
    {
        return [
            'autonomy_rate' => 0,
            'open_exceptions' => 0,
            'open_automatic_items' => 0,
            'by_domain' => [],
            'by_severity' => [],
            'last_run' => null,
        ];
    }
}
