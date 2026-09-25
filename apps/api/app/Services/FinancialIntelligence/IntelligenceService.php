<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Institution-owned analytical evidence. Never writes to the core loan or accounting tables. */
final class IntelligenceService
{
    public function __construct(private readonly Access $access, private readonly PortfolioEngine $engine, private readonly AuditLogger $audit) {}

    public function context(FinancialSpace $space, User $actor): array
    {
        if ($space->type === 'personal') {
            $this->access->require($space, $actor, 'statement');
            return ['space_id' => $space->id, 'space_name' => $space->name, 'space_type' => 'personal',
                'role' => 'personal', 'permissions' => ['statement'], 'country' => $space->country];
        }
        $role = $this->access->role($space, $actor);
        return ['space_id' => $space->id, 'space_name' => $space->name, 'space_type' => $space->type,
            'role' => $role, 'permissions' => $this->access->permissions($role), 'country' => $space->country];
    }

    public function members(FinancialSpace $space, User $actor): array
    {
        $this->access->require($space, $actor, 'grant');
        $members = FinancialSpaceMembership::query()->where('financial_space_id', $space->id)->where('status', 'active')
            ->whereHas('user')->orderBy('user_id')->get(['user_id', 'role']);
        $grants = DB::table('fi_grants')->where('financial_space_id', $space->id)->get(['user_id', 'role', 'expires_at', 'revoked_at']);
        $this->audit->record('intelligence.access_register_viewed', $actor, $space);
        return ['members' => $members->toArray(), 'grants' => $grants->toArray()];
    }

    public function reports(FinancialSpace $space, User $actor, int $page): array
    {
        $this->access->require($space, $actor, 'report');
        $query = DB::table('fi_reports')->where('financial_space_id', $space->id);
        $total = (clone $query)->count();
        $this->audit->record('intelligence.report_register_viewed', $actor, $space, ['page' => $page]);
        return ['items' => $query->orderByDesc('id')->forPage($page, 25)->get(['id', 'public_id', 'import_id', 'content_hash', 'created_at'])->all(),
            'page' => $page, 'page_size' => 25, 'total' => $total];
    }

    public function overview(FinancialSpace $space, User $actor): array
    {
        $role = $this->access->require($space, $actor, 'overview');
        $sources = DB::table('fi_sources')->where('financial_space_id', $space->id)->orderBy('id')->get();
        $items = [];
        foreach ($sources as $source) {
            $latest = $source->current_import_id ? $this->import($space, (int) $source->current_import_id) : null;
            $items[] = ['id' => $source->id, 'public_id' => $source->public_id, 'name' => $source->name, 'population' => $source->population,
                'country' => $source->country, 'status' => $source->status, 'current_import_id' => $latest?->id,
                'as_of' => $latest?->as_of, 'last_published_at' => $latest?->reviewed_at,
                'stale' => $latest ? now()->greaterThan(\Illuminate\Support\Carbon::parse($latest->as_of)->endOfDay()->addHours((int) config('financial_intelligence.stale_after_hours'))) : null,
                'analysis' => $latest ? $this->aggregate($this->decode($latest->analysis_cipher)) : null];
        }
        $this->audit->record('intelligence.overview_viewed', $actor, $space);

        return ['space_id' => $space->id, 'space_name' => $space->name, 'role' => $role, 'permissions' => $this->access->permissions($role),
            'sources' => $items, 'currency_conversion' => 'none', 'credit_decision_eligible' => false];
    }

    public function createSource(FinancialSpace $space, User $actor, array $data): array
    {
        $this->access->require($space, $actor, 'source');
        abort_unless($data['country'] === $space->country, 422, 'Source jurisdiction must match this institutional Space.');
        return DB::transaction(function () use ($space, $actor, $data): array {
            $id = DB::table('fi_sources')->insertGetId(['public_id' => (string) Str::uuid(), 'financial_space_id' => $space->id,
                'name' => Values::text($data['name'], 'name', 80), 'population' => Values::text($data['population'], 'population'),
                'country' => $data['country'], 'lawful_basis_reference' => Values::text($data['lawful_basis_reference'], 'lawful_basis_reference', 255),
                'status' => 'active', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record('intelligence.source_created', $actor, $space, ['source_id' => $id]);
            return ['source_id' => $id, 'status' => 'active'];
        });
    }

    public function stage(FinancialSpace $space, User $actor, int $sourceId, array $input, string $key, ?array $fileHashes = null): array
    {
        $this->access->require($space, $actor, 'import');
        $key = Values::text($key, 'Idempotency-Key');
        $dataset = $this->engine->normalise($input);
        abort_if($dataset['as_of'] > now()->toDateString(), 422, 'A reporting observation cannot be dated in the future.');
        $hash = Values::hash(['input' => $input, 'files' => $fileHashes]);
        $analysis = $this->engine->analyse($dataset, (int) config('financial_intelligence.management_npl_days', 90));
        return DB::transaction(function () use ($space, $actor, $sourceId, $key, $dataset, $analysis, $hash): array {
            $source = $this->source($space, $sourceId, true);
            abort_unless($source->status === 'active', 409, 'Source is not active.');
            abort_unless($source->name === $dataset['source_system'] && $source->population === $dataset['population'], 422, 'The source and population must match the registered import contract.');
            $existing = DB::table('fi_imports')->where('source_id', $sourceId)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->instruction_hash, $hash), 409, 'This idempotency key belongs to different source data.');
                return $this->importSummary($existing);
            }
            $id = DB::table('fi_imports')->insertGetId(['public_id' => (string) Str::uuid(), 'financial_space_id' => $space->id,
                'source_id' => $sourceId, 'submitted_by' => $actor->id, 'idempotency_key' => $key, 'instruction_hash' => $hash,
                'source_hash' => Values::hash($dataset), 'engine_version' => PortfolioEngine::ENGINE_VERSION, 'as_of' => $dataset['as_of'],
                'row_count' => count($dataset['loans']), 'payload_cipher' => $this->encode($dataset), 'analysis_cipher' => $this->encode($analysis),
                'status' => 'staged', 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record('intelligence.import_staged', $actor, $space, ['import_id' => $id, 'source_id' => $sourceId, 'instruction_hash' => $hash]);
            return $this->importSummary($this->import($space, $id));
        });
    }

    public function imports(FinancialSpace $space, User $actor, int $sourceId, int $page): array
    {
        $this->access->require($space, $actor, 'detail');
        $this->source($space, $sourceId);
        $query = DB::table('fi_imports')->where('financial_space_id', $space->id)->where('source_id', $sourceId);
        return ['items' => $query->orderByDesc('id')->forPage($page, 25)->get()->map(fn ($i) => $this->importSummary($i))->all(),
            'page' => $page, 'page_size' => 25, 'total' => DB::table('fi_imports')->where('financial_space_id', $space->id)->where('source_id', $sourceId)->count()];
    }

    public function detail(FinancialSpace $space, User $actor, int $importId, int $page = 1): array
    {
        $this->access->require($space, $actor, 'detail');
        $import = $this->import($space, $importId);
        $data = $this->decode($import->payload_cipher);
        $this->audit->record('intelligence.import_viewed', $actor, $space, ['import_id' => $importId, 'page' => $page]);
        return ['import' => $this->importSummary($import), 'analysis' => $this->decode($import->analysis_cipher),
            'loans' => array_slice($data['loans'], ($page - 1) * 50, 50), 'page' => $page, 'page_size' => 50, 'total' => count($data['loans'])];
    }

    public function publish(FinancialSpace $space, User $actor, int $importId, string $decision, string $reason): array
    {
        $this->access->require($space, $actor, 'publish');
        $reason = Values::text($reason, 'review reason', 1000);
        $initial = $this->import($space, $importId);
        return DB::transaction(function () use ($space, $actor, $importId, $initial, $decision, $reason): array {
            $source = $this->source($space, (int) $initial->source_id, true);
            $import = $this->import($space, $importId, true);
            abort_if((int) $import->submitted_by === (int) $actor->id, 403, 'A different authorised person must review this import.');
            if ($import->status === $decision) {
                abort_unless((int) $import->reviewed_by === (int) $actor->id && hash_equals(Crypt::decryptString($import->review_reason_cipher), $reason), 409, 'A completed review cannot be replaced.');
                return $this->importSummary($import);
            }
            abort_unless($import->status === 'staged' && in_array($decision, ['published', 'rejected'], true), 409, 'Import is no longer awaiting review.');
            abort_unless($source->status === 'active', 409, 'The data source is not active.');
            if ($decision === 'published') {
                $prior = $source->current_import_id ? $this->import($space, (int) $source->current_import_id) : null;
                abort_if($prior && $import->as_of < $prior->as_of, 409, 'Publish historical snapshots chronologically. A correction must retain the same reporting date or be later.');
                DB::table('fi_publications')->insert(['financial_space_id' => $space->id, 'source_id' => $source->id, 'import_id' => $importId,
                    'previous_import_id' => $prior?->id, 'published_by' => $actor->id, 'created_at' => now()]);
                DB::table('fi_sources')->where('id', $source->id)->update(['current_import_id' => $importId, 'updated_at' => now()]);
                $this->observeCases($space, $actor, $source, $import);
            }
            DB::table('fi_imports')->where('id', $importId)->update(['status' => $decision, 'reviewed_by' => $actor->id,
                'review_reason_cipher' => Crypt::encryptString($reason), 'reviewed_at' => now(), 'updated_at' => now()]);
            $this->audit->record('intelligence.import_'.$decision, $actor, $space, ['import_id' => $importId, 'source_id' => $source->id]);
            return $this->importSummary($this->import($space, $importId));
        });
    }

    public function comparison(FinancialSpace $space, User $actor, int $fromId, int $toId): array
    {
        $this->access->require($space, $actor, 'overview');
        $from = $this->import($space, $fromId);
        $to = $this->import($space, $toId);
        abort_unless($from->status === 'published' && $to->status === 'published' && $from->source_id === $to->source_id, 422, 'Choose two published snapshots of the same source.');
        $this->audit->record('intelligence.comparison_viewed', $actor, $space, ['from_id' => $fromId, 'to_id' => $toId]);
        return $this->engine->compare($this->decode($from->payload_cipher), $this->decode($to->payload_cipher));
    }

    public function stress(FinancialSpace $space, User $actor, int $importId, int $inflowReduction, int $outflowIncrease): array
    {
        $this->access->require($space, $actor, 'overview');
        $import = $this->import($space, $importId);
        abort_unless($import->status === 'published', 409, 'Use a published snapshot.');
        $result = $this->engine->stress($this->decode($import->payload_cipher)['financials'], $inflowReduction, $outflowIncrease);
        $this->audit->record('intelligence.scenario_calculated', $actor, $space, ['import_id' => $importId, 'inflow_reduction_bps' => $inflowReduction, 'outflow_increase_bps' => $outflowIncrease]);
        return ['import_id' => $importId, 'source_hash' => $import->source_hash, 'scenario' => $result];
    }

    public function cases(FinancialSpace $space, User $actor, int $page): array
    {
        $role = $this->access->require($space, $actor, 'case');
        $query = DB::table('fi_cases')->where('financial_space_id', $space->id);
        if ($role === 'collections') {
            $query->where('assigned_to', $actor->id);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, 50)->get()->map(fn ($case) => $this->caseSummary($case))->all();
        $this->audit->record('intelligence.cases_viewed', $actor, $space, ['page' => $page]);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => 50];
    }

    public function caseEvent(FinancialSpace $space, User $actor, int $caseId, array $data, string $key): array
    {
        $role = $this->access->require($space, $actor, 'case');
        $key = Values::text($key, 'Idempotency-Key');
        $hash = Values::hash($data);
        return DB::transaction(function () use ($space, $actor, $caseId, $data, $key, $hash, $role): array {
            $case = DB::table('fi_cases')->where('financial_space_id', $space->id)->where('id', $caseId)->lockForUpdate()->first();
            abort_unless($case, 404);
            abort_if($role === 'collections' && (int) $case->assigned_to !== (int) $actor->id, 403);
            $existing = DB::table('fi_case_events')->where('case_id', $caseId)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->instruction_hash, $hash) && (int) $existing->actor_id === (int) $actor->id, 409, 'Event key already used for a different instruction.');
                return ['case_id' => $caseId, 'event_id' => $existing->id, 'version' => $existing->result_version, 'replayed' => true];
            }
            abort_unless((int) $data['expected_version'] === (int) $case->version, 409, 'This case changed. Reload before updating.');
            $action = $data['action'];
            $updates = ['version' => (int) $case->version + 1, 'updated_at' => now()];
            if ($action === 'assign') {
                abort_if($role === 'collections', 403);
                $this->access->assertAssignee($space, (int) $data['assigned_to']);
                $updates['assigned_to'] = (int) $data['assigned_to'];
                $updates['due_on'] = $data['due_on'];
            } elseif ($action === 'start') {
                abort_unless(in_array($case->status, ['open', 'monitoring'], true), 409);
                $updates['status'] = 'in_progress';
            } elseif ($action === 'monitor') {
                abort_unless(in_array($case->status, ['open', 'in_progress', 'monitoring'], true), 409);
                $updates['status'] = 'monitoring';
                $updates['due_on'] = $data['due_on'];
            } elseif ($action === 'close') {
                abort_unless($case->status !== 'closed', 409);
                $updates['status'] = 'closed';
            } elseif ($action === 'reopen') {
                abort_unless($case->status === 'closed', 409);
                $updates['status'] = 'open';
            } else {
                abort_unless($action === 'note', 422);
            }
            // Descriptions are evidence of staff actions, never proof that money settled.
            $eventId = DB::table('fi_case_events')->insertGetId(['financial_space_id' => $space->id, 'case_id' => $caseId,
                'actor_id' => $actor->id, 'idempotency_key' => $key, 'instruction_hash' => $hash, 'action' => $action,
                'result_version' => $updates['version'], 'event_cipher' => $this->encode($data), 'created_at' => now()]);
            DB::table('fi_cases')->where('id', $caseId)->update($updates);
            $this->audit->record('intelligence.case_'.$action, $actor, $space, ['case_id' => $caseId, 'event_id' => $eventId]);
            return ['case_id' => $caseId, 'event_id' => $eventId, 'version' => $updates['version'], 'replayed' => false];
        });
    }

    public function caseHistory(FinancialSpace $space, User $actor, int $caseId, int $page): array
    {
        $role = $this->access->require($space, $actor, 'case');
        $case = DB::table('fi_cases')->where('financial_space_id', $space->id)->where('id', $caseId)->first();
        abort_unless($case, 404);
        abort_if($role === 'collections' && (int) $case->assigned_to !== (int) $actor->id, 403);
        $events = DB::table('fi_case_events')->where('case_id', $caseId)->orderByDesc('id')->forPage($page, 50)->get();
        $this->audit->record('intelligence.case_history_viewed', $actor, $space, ['case_id' => $caseId, 'page' => $page]);
        return ['case' => $this->caseSummary($case), 'events' => $events->map(fn ($event) => ['id' => $event->id, 'action' => $event->action,
            'actor_id' => $event->actor_id, 'version' => $event->result_version, 'created_at' => $event->created_at, 'detail' => $this->decode($event->event_cipher)])->all(), 'page' => $page];
    }

    public function grant(FinancialSpace $space, User $actor, array $data): array
    {
        $this->access->require($space, $actor, 'grant');
        abort_unless(FinancialSpaceMembership::query()->where('financial_space_id', $space->id)->where('user_id', $data['user_id'])->where('status', 'active')->whereHas('user')->exists(), 422, 'Choose an active member of this Space.');
        DB::table('fi_grants')->updateOrInsert(['financial_space_id' => $space->id, 'user_id' => $data['user_id']],
            ['role' => $data['role'], 'expires_at' => $data['expires_at'], 'revoked_at' => ($data['revoke'] ?? false) ? now() : null,
                'granted_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
        $this->audit->record('intelligence.grant_changed', $actor, $space, ['grantee_id' => $data['user_id'], 'role' => $data['role'], 'revoked' => $data['revoke'] ?? false]);
        return ['user_id' => $data['user_id'], 'role' => $data['role'], 'expires_at' => $data['expires_at'], 'revoked' => $data['revoke'] ?? false];
    }

    public function report(FinancialSpace $space, User $actor, int $importId): array
    {
        $this->access->require($space, $actor, 'report');
        $import = $this->import($space, $importId);
        abort_unless($import->status === 'published', 409, 'Freeze a report only from a published snapshot.');
        $payload = ['title' => 'OpFin Financial Intelligence management report', 'space_name' => $space->name, 'space_id' => $space->id,
            'as_of' => $import->as_of, 'import_id' => $importId, 'source_hash' => $import->source_hash, 'engine_version' => $import->engine_version,
            'generated_at' => now()->toIso8601String(), 'analysis' => $this->aggregate($this->decode($import->analysis_cipher)),
            'disclaimer' => 'Management analysis of institution-supplied records. Not an audit opinion, regulatory certification, bank statement or credit decision. No FX conversion.'];
        return DB::transaction(function () use ($space, $actor, $importId, $payload): array {
            $id = DB::table('fi_reports')->insertGetId(['public_id' => (string) Str::uuid(), 'financial_space_id' => $space->id,
                'import_id' => $importId, 'created_by' => $actor->id, 'content_hash' => Values::hash($payload), 'report_cipher' => $this->encode($payload), 'created_at' => now()]);
            $this->audit->record('intelligence.report_frozen', $actor, $space, ['report_id' => $id]);
            return ['report_id' => $id, 'content_hash' => Values::hash($payload)];
        });
    }

    public function readReport(FinancialSpace $space, User $actor, int $reportId): array
    {
        $this->access->require($space, $actor, 'report');
        $report = DB::table('fi_reports')->where('financial_space_id', $space->id)->where('id', $reportId)->first();
        abort_unless($report, 404);
        $this->audit->record('intelligence.report_viewed', $actor, $space, ['report_id' => $reportId]);
        return ['report_id' => $reportId, 'content_hash' => $report->content_hash, 'report' => $this->decode($report->report_cipher)];
    }

    public function shareReport(FinancialSpace $space, User $actor, int $reportId, array $data): array
    {
        $this->access->require($space, $actor, 'share');
        $this->readReport($space, $actor, $reportId);
        abort_if((int) $data['recipient_space_id'] === (int) $space->id, 422);
        abort_unless(FinancialSpace::query()->whereKey($data['recipient_space_id'])->where('status', 'active')->whereIn('type', ['business', 'sacco', 'investment_fund', 'partner'])->exists(), 422, 'Recipient institution is unavailable.');
        DB::table('fi_network_grants')->updateOrInsert(['report_id' => $reportId, 'recipient_space_id' => $data['recipient_space_id']],
            ['financial_space_id' => $space->id, 'granted_by' => $actor->id, 'expires_at' => $data['expires_at'],
                'revoked_at' => ($data['revoke'] ?? false) ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('intelligence.report_share_changed', $actor, $space, ['report_id' => $reportId, 'recipient_space_id' => $data['recipient_space_id'], 'revoked' => $data['revoke'] ?? false]);
        return ['report_id' => $reportId, 'recipient_space_id' => $data['recipient_space_id'], 'expires_at' => $data['expires_at']];
    }

    public function network(FinancialSpace $space, User $actor, int $page): array
    {
        $this->access->require($space, $actor, 'report');
        $grants = DB::table('fi_network_grants as g')->join('financial_spaces as s', 's.id', '=', 'g.financial_space_id')
            ->join('fi_reports as r', 'r.id', '=', 'g.report_id')->where('g.recipient_space_id', $space->id)->whereNull('g.revoked_at')
            ->where('g.expires_at', '>', now())->whereNull('s.deleted_at')->where('s.status', 'active')
            ->orderByDesc('g.id')->forPage($page, 25)->select('g.id', 'g.expires_at', 'r.content_hash', 'r.report_cipher')->get();
        $items = $grants->map(function ($grant): array {
            $report = $this->decode($grant->report_cipher);
            // Suppress all detailed breakdowns externally; no customer identifiers are shared.
            $report['analysis']['concentrations'] = [];
            $report['analysis']['vintages'] = [];
            foreach ($report['analysis']['currency_metrics'] as $code => $metrics) {
                if (($metrics['distinct_borrower_count'] ?? 0) < 5) {
                    $report['analysis']['currency_metrics'][$code] = ['suppressed' => true, 'reason' => 'Fewer than five distinct borrowers'];
                }
            }
            $report['analysis']['financials'] = []; // Accounting information is not included in the portfolio-sharing mandate.
            return ['grant_id' => $grant->id, 'expires_at' => $grant->expires_at, 'source_report_hash' => $grant->content_hash,
                'shared_payload_hash' => Values::hash($report), 'report' => $report];
        })->all();
        $this->audit->record('intelligence.network_viewed', $actor, $space, ['page' => $page]);
        return ['items' => $items, 'page' => $page, 'note' => 'Reports are independently authorised and dated. Do not combine overlapping populations or currencies.'];
    }

    private function observeCases(FinancialSpace $space, User $actor, object $source, object $import): void
    {
        foreach ($this->decode($import->payload_cipher)['loans'] as $loan) {
            if ($loan['principal_outstanding_minor'] === 0) {
                continue;
            }
            $signal = $loan['dpd'] === null ? 'incomplete_schedule' : ($loan['unlikely_to_pay'] ? 'documented_repayment_concern' : ($loan['dpd'] > 0 ? 'overdue_payment' : null));
            if ($signal === null) {
                continue;
            }
            $fingerprint = Values::hash([$source->id, $loan['loan_ref'], $signal]);
            $case = DB::table('fi_cases')->where('source_id', $source->id)->where('fingerprint', $fingerprint)->lockForUpdate()->first();
            $evidence = ['loan_ref' => $loan['loan_ref'], 'borrower_ref' => $loan['borrower_ref'], 'currency' => $loan['currency'],
                'principal_outstanding_minor' => $loan['principal_outstanding_minor'], 'dpd' => $loan['dpd'], 'arrears_minor' => $loan['arrears_minor'],
                'branch' => $loan['branch'], 'import_id' => $import->id, 'as_of' => $import->as_of, 'source_hash' => $import->source_hash,
                'warning' => 'Review source evidence before contacting a customer. This signal is not fraud confirmation or a payment instruction.'];
            $priority = $loan['unlikely_to_pay'] || ($loan['dpd'] !== null && $loan['dpd'] >= 90) ? 'high' : 'normal';
            $version = $case ? (int) $case->version + 1 : 1;
            if ($case) {
                $id = $case->id;
                DB::table('fi_cases')->where('id', $id)->update(['import_id' => $import->id, 'evidence_cipher' => $this->encode($evidence), 'priority' => $priority,
                    'status' => $case->status === 'closed' ? 'open' : $case->status, 'version' => $version, 'updated_at' => now()]);
            } else {
                $id = DB::table('fi_cases')->insertGetId(['public_id' => (string) Str::uuid(), 'financial_space_id' => $space->id, 'source_id' => $source->id,
                    'import_id' => $import->id, 'fingerprint' => $fingerprint, 'signal' => $signal, 'status' => 'open', 'priority' => $priority,
                    'evidence_cipher' => $this->encode($evidence), 'version' => $version, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('fi_case_events')->insert(['financial_space_id' => $space->id, 'case_id' => $id, 'actor_id' => $actor->id,
                'idempotency_key' => 'observation:'.$import->id, 'instruction_hash' => Values::hash($evidence), 'action' => 'observation',
                'result_version' => $version, 'event_cipher' => $this->encode($evidence), 'created_at' => now()]);
        }
    }

    private function aggregate(array $analysis): array
    {
        $analysis['concentrations'] = array_values(array_filter($analysis['concentrations'] ?? [], fn ($group) => ! in_array($group['dimension'] ?? '', ['borrower_ref', 'guarantor_ref', 'officer'], true)));
        return $analysis;
    }

    private function source(FinancialSpace $space, int $id, bool $lock = false): object
    {
        $query = DB::table('fi_sources')->where('financial_space_id', $space->id)->where('id', $id);
        $source = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($source, 404);
        return $source;
    }

    private function import(FinancialSpace $space, int $id, bool $lock = false): object
    {
        $query = DB::table('fi_imports')->where('financial_space_id', $space->id)->where('id', $id);
        $import = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($import, 404);
        return $import;
    }

    private function importSummary(object $import): array
    {
        return ['id' => $import->id, 'public_id' => $import->public_id, 'source_id' => $import->source_id, 'as_of' => $import->as_of,
            'row_count' => $import->row_count, 'status' => $import->status, 'submitted_by' => $import->submitted_by, 'reviewed_by' => $import->reviewed_by,
            'source_hash' => $import->source_hash, 'engine_version' => $import->engine_version, 'created_at' => $import->created_at,
            'reviewed_at' => $import->reviewed_at, 'source_authenticity' => 'institution_supplied_not_independently_authenticated'];
    }

    private function caseSummary(object $case): array
    {
        return ['id' => $case->id, 'public_id' => $case->public_id, 'source_id' => $case->source_id, 'import_id' => $case->import_id,
            'signal' => $case->signal, 'status' => $case->status, 'priority' => $case->priority, 'assigned_to' => $case->assigned_to,
            'due_on' => $case->due_on, 'version' => $case->version, 'evidence' => $this->decode($case->evidence_cipher)];
    }

    private function encode(array $value): string { return Crypt::encryptString(Values::canonical($value)); }
    private function decode(string $value): array { return json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR); }
}
