<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Jobs\AnalyseStatementDocument;
use App\Models\FinancialSpace;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Encrypted evidence storage. Document integrity is never promoted to issuer authenticity. */
final class StatementVault
{
    public function __construct(
        private readonly Access $access,
        private readonly CsvImportReader $csv,
        private readonly AuditLogger $audit,
        private readonly StatementReviews $reviews,
    ) {}

    public function issuers(FinancialSpace $space, User $actor): array
    {
        $this->access->require($space, $actor, 'statement');

        return ['items' => DB::table('fi_issuer_versions')->where('country', $space->country)->whereNotNull('approved_at')
            ->whereNull('revoked_at')->where('review_due_on', '>=', now()->toDateString())
            ->orderBy('legal_name')->get(['id', 'issuer_code', 'legal_name', 'country', 'product_type', 'regulator', 'licence_reference', 'valid_from', 'valid_until', 'review_due_on'])->all(),
            'note' => 'Issuer eligibility does not authenticate a particular document. Historical documents must fall within the recorded licence period.'];
    }

    public function proposeIssuer(User $actor, array $data): array
    {
        $this->issuerAdministrator($actor);
        $id = DB::table('fi_issuer_versions')->insertGetId([...$data, 'public_id' => (string) Str::uuid(), 'proposed_by' => $actor->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('intelligence.issuer_proposed', $actor, null, ['issuer_version_id' => $id]);

        return ['issuer_version_id' => $id, 'status' => 'pending_independent_approval'];
    }

    public function reviewIssuer(User $actor, int $id, bool $revoke): array
    {
        $this->issuerAdministrator($actor);

        return DB::transaction(function () use ($actor, $id, $revoke): array {
            $issuer = DB::table('fi_issuer_versions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($issuer, 404);
            if ($revoke) {
                DB::table('fi_issuer_versions')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            } else {
                abort_if((int) $issuer->proposed_by === (int) $actor->id, 403, 'A different authorised reviewer must confirm issuer eligibility.');
                abort_if($issuer->revoked_at !== null, 409, 'Propose a new issuer version after revocation.');
                abort_if($issuer->review_due_on < now()->toDateString(), 409, 'Issuer evidence is stale. Propose a reviewed version.');
                abort_if($issuer->approved_at !== null && (int) $issuer->approved_by !== (int) $actor->id, 409);
                if ($issuer->approved_at === null) {
                    DB::table('fi_issuer_versions')->where('id', $id)->update(['approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);
                }
            }
            $this->audit->record($revoke ? 'intelligence.issuer_revoked' : 'intelligence.issuer_approved', $actor, null, ['issuer_version_id' => $id]);

            return ['issuer_version_id' => $id, 'status' => $revoke ? 'revoked' : 'approved'];
        });
    }

    public function upload(FinancialSpace $space, User $actor, UploadedFile $file, array $data, string $key): array
    {
        $this->access->require($space, $actor, 'statement');
        $key = Values::text($key, 'Idempotency-Key');
        abort_unless($file->isValid() && $file->getSize() > 0 && $file->getSize() <= config('financial_intelligence.max_upload_bytes'), 422, 'Choose a supported file within the upload limit.');
        $bytes = file_get_contents($file->getRealPath());
        abort_if($bytes === false, 422, 'The uploaded file could not be read.');
        $media = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = strtolower($file->getClientOriginalExtension());
        $isCsv = $extension === 'csv' && in_array($media, ['text/plain', 'text/csv', 'application/csv', 'text/x-csv'], true);
        $isPdf = $extension === 'pdf' && $media === 'application/pdf' && str_starts_with($bytes, '%PDF-');
        abort_unless($isCsv || $isPdf, 422, 'Upload the original issuer PDF or a UTF-8 CSV export. Other formats are not accepted by this parser.');
        abort_if($isPdf && strlen($bytes) > (int) config('financial_intelligence.statement_pdf_max_bytes'), 422,
            'PDF statements are limited to 10 MB. Upload a shorter period or the issuer CSV export.');
        $start = Values::date($data['period_start'], 'period_start');
        $end = Values::date($data['period_end'], 'period_end');
        abort_if($start > $end || $end > now()->toDateString() || Values::days($start, $end) > 1096, 422, 'Check the statement period.');
        $fileHash = hash('sha256', $bytes);
        $instructionHash = Values::hash(['file_hash' => $fileHash, 'metadata' => $data]);
        $path = null;
        try {
            return DB::transaction(function () use ($space, $actor, $data, $key, $bytes, $media, $isCsv, $start, $end, $fileHash, $instructionHash, &$path): array {
                // Locks the owner Space to serialise retries without exposing cross-Space hashes.
                FinancialSpace::query()->whereKey($space->id)->lockForUpdate()->firstOrFail();
                $this->access->require($space->fresh(), $actor, 'statement');
                $existing = DB::table('fi_statements')->where('financial_space_id', $space->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->instruction_hash, $instructionHash), 409, 'This upload key was used for a different document or permission.');
                    abort_if($existing->revoked_at !== null, 409, 'This upload permission has been withdrawn.');

                    return $this->summary($existing);
                }
                $issuer = DB::table('fi_issuer_versions')->where('id', $data['issuer_version_id'])->where('country', $space->country)
                    ->whereNotNull('approved_at')->whereNull('revoked_at')->where('valid_from', '<=', $start)->where('valid_until', '>=', $end)
                    ->where('review_due_on', '>=', now()->toDateString())->lockForUpdate()->first();
                abort_unless($issuer, 422, 'The issuer is not approved for this jurisdiction, product or historical period. Request issuer review; this is not a fraud finding.');
                abort_unless($data['authority_confirmed'] === true && $data['purpose'] === 'financial_analysis', 422, 'Confirm your authority and the analysis purpose. Credit use is not included.');
                $previous = null;
                if (! empty($data['supersedes_statement_id'])) {
                    $previous = DB::table('fi_statements')->where('financial_space_id', $space->id)
                        ->where('id', (int) $data['supersedes_statement_id'])->lockForUpdate()->first();
                    abort_unless($previous !== null && (int) $previous->uploaded_by === (int) $actor->id && $previous->revoked_at === null, 422,
                        'Choose one of your own current statements in this Space to replace.');
                }
                $analysis = null;
                $pipeline = ['currency' => $data['currency'], 'minor_unit_exponent' => isset($data['minor_unit_exponent'])
                    ? (int) $data['minor_unit_exponent'] : Values::exponent($data['currency'])];
                foreach (['opening_balance_minor', 'closing_balance_minor'] as $field) {
                    if (isset($data[$field])) {
                        $pipeline[$field] = (int) $data[$field];
                    }
                }
                if ($isCsv) {
                    $metadata = ['currency' => $data['currency'], 'period_start' => $start, 'period_end' => $end];
                    foreach (['opening_balance_minor', 'closing_balance_minor'] as $field) {
                        if (isset($data[$field])) {
                            $metadata[$field] = $data[$field];
                        }
                    }
                    $analysis = $this->csv->statement($bytes, $metadata, $data['mapping'] ?? []);
                }
                $status = $isCsv ? 'analysed_unconfirmed' : 'queued_for_analysis';
                $publicId = (string) Str::uuid();
                $path = trim((string) config('financial_intelligence.statement_prefix'), '/').'/'.$space->id.'/'.$publicId.'.enc';
                $stored = Storage::disk(config('financial_intelligence.statement_disk'))->put($path, Crypt::encryptString($bytes), ['visibility' => 'private']);
                abort_unless($stored, 503, 'Private evidence storage is unavailable.');
                $id = DB::table('fi_statements')->insertGetId(['public_id' => $publicId, 'financial_space_id' => $space->id, 'issuer_version_id' => $issuer->id,
                    'uploaded_by' => $actor->id, 'idempotency_key' => $key, 'instruction_hash' => $instructionHash, 'file_hash' => $fileHash,
                    'storage_path' => $path, 'media_type' => $media, 'bytes' => strlen($bytes), 'status' => $status,
                    'period_start' => $start, 'period_end' => $end, 'authority_expires_at' => $data['authority_expires_at'],
                    'authority_cipher' => Crypt::encryptString(Values::canonical(['purpose' => $data['purpose'], 'authority_reference' => $data['authority_reference'],
                        'account_reference' => $data['account_reference'], 'confirmed_by' => $actor->id, 'confirmed_at' => now()->toIso8601String()])),
                    'analysis_cipher' => $analysis ? Crypt::encryptString(Values::canonical($analysis)) : null,
                    'pipeline_cipher' => Crypt::encryptString(Values::canonical($pipeline)),
                    'assurance' => json_encode(StatementAssurance::from($status, $analysis)), 'analysed_at' => $isCsv ? now() : null,
                    'supersedes_statement_id' => $previous?->id, 'created_at' => now(), 'updated_at' => now()]);
                if ($previous !== null) {
                    $this->reviews->supersede($previous, $actor, $key);
                }
                $this->audit->record('intelligence.statement_uploaded', $actor, $space, ['statement_id' => $id, 'file_hash' => $fileHash,
                    'issuer_version_id' => $issuer->id, 'supersedes_statement_id' => $previous?->id]);
                if (! $isCsv) {
                    AnalyseStatementDocument::dispatch($id)->afterCommit();
                }

                return $this->summary(DB::table('fi_statements')->find($id));
            });
        } catch (Throwable $error) {
            if ($path !== null) {
                Storage::disk(config('financial_intelligence.statement_disk'))->delete($path);
            }
            throw $error;
        }
    }

    public function listing(FinancialSpace $space, User $actor, int $page): array
    {
        $this->access->require($space, $actor, 'statement');
        $rows = DB::table('fi_statements')->where('financial_space_id', $space->id)->orderByDesc('id')->forPage($page, 25)->get();
        $this->audit->record('intelligence.statements_viewed', $actor, $space, ['page' => $page]);

        return ['items' => $rows->map(fn ($row) => $this->summary($row))->all(), 'page' => $page, 'page_size' => 25];
    }

    public function detail(FinancialSpace $space, User $actor, int $id, int $page): array
    {
        $role = $this->access->require($space, $actor, 'statement');
        $row = DB::table('fi_statements')->where('financial_space_id', $space->id)->where('id', $id)->first();
        abort_unless($row, 404);
        abort_if($row->revoked_at !== null || Carbon::parse($row->authority_expires_at)->isPast(), 403, 'Analysis permission has expired or been withdrawn.');
        $issuerCurrent = DB::table('fi_issuer_versions')->where('id', $row->issuer_version_id)->whereNull('revoked_at')
            ->whereNotNull('approved_at')->where('review_due_on', '>=', now()->toDateString())->exists();
        $analysis = $row->analysis_cipher ? json_decode(Crypt::decryptString($row->analysis_cipher), true, 512, JSON_THROW_ON_ERROR) : null;
        if ($analysis !== null) {
            $rows = $analysis['transactions'];
            $analysis['transactions'] = array_slice($rows, ($page - 1) * 50, 50);
            $analysis['transaction_total'] = count($rows);
            $analysis['page'] = $page;
        }
        $this->audit->record('intelligence.statement_analysed_viewed', $actor, $space, ['statement_id' => $id, 'page' => $page]);

        return ['statement' => $this->summary($row), 'issuer_eligibility_current' => $issuerCurrent, 'analysis' => $analysis,
            'assurance' => $this->assurance($row), 'status_explanation' => $this->explanation($row->status),
            'supersedes_statement_id' => $row->supersedes_statement_id,
            ...$this->reviews->forDetail($row, $actor, $role),
            'source_authenticity' => 'unconfirmed', 'credit_decision_eligible' => false];
    }

    public function revoke(FinancialSpace $space, User $actor, int $id): array
    {
        $this->access->require($space, $actor, 'statement');
        $row = DB::table('fi_statements')->where('financial_space_id', $space->id)->where('id', $id)->first();
        abort_unless($row, 404);
        abort_unless((int) $row->uploaded_by === (int) $actor->id || $this->access->require($space, $actor, 'grant') === 'administrator', 403);
        DB::table('fi_statements')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
        $this->audit->record('intelligence.statement_permission_revoked', $actor, $space, ['statement_id' => $id]);

        return ['statement_id' => $id, 'analysis_access' => 'revoked', 'retention' => 'Original evidence remains subject to the approved retention policy.'];
    }

    private function issuerAdministrator(User $actor): void
    {
        abort_unless(config('financial_intelligence.enabled', false), 503);
        abort_if($actor->trashed() || $actor->role !== User::ROLE_PLATFORM_ADMIN, 403);
    }

    private function summary(object $row): array
    {
        return ['id' => $row->id, 'public_id' => $row->public_id, 'issuer_version_id' => $row->issuer_version_id, 'file_hash' => $row->file_hash,
            'status' => $row->status, 'period_start' => $row->period_start, 'period_end' => $row->period_end, 'authority_expires_at' => $row->authority_expires_at,
            'revoked_at' => $row->revoked_at, 'analysed_at' => $row->analysed_at, 'original_purged_at' => $row->original_purged_at,
            'assurance' => $this->assurance($row),
            'source_authenticity' => 'unconfirmed', 'account_ownership' => 'unconfirmed', 'credit_decision_eligible' => false];
    }

    private function assurance(object $row): array
    {
        if ($row->assurance !== null) {
            return json_decode($row->assurance, true, 8, JSON_THROW_ON_ERROR);
        }
        // Statements analysed before assurance codes were stored.
        $analysis = $row->analysis_cipher !== null
            ? json_decode(Crypt::decryptString($row->analysis_cipher), true, 512, JSON_THROW_ON_ERROR) : null;

        return StatementAssurance::from($row->status, $analysis);
    }

    /** Plain-language status; an unsupported or unreadable document is never described as suspicious. */
    private function explanation(string $status): string
    {
        return match ($status) {
            'queued_for_analysis', 'analysing' => 'We are reading this statement. Check back in a few minutes.',
            'analysed_unconfirmed' => 'Read and checked for consistency. The issuer has not confirmed this document.',
            'layout_not_supported' => 'This statement layout is not yet supported. Upload the issuer CSV export if one is available.',
            'export_required_password_protected' => 'This PDF is password-protected. Download an unprotected copy from your provider. OpFin never asks for statement passwords, PINs or OTPs.',
            'rejected_active_content' => 'This PDF contains scripts or embedded files, so it was not opened. Upload the statement exactly as your provider issues it.',
            'rejected_processing_limits' => 'This PDF is too large to process. Upload a shorter period or the issuer CSV export.',
            'unreadable' => 'This file could not be read. Upload the original statement from your provider.',
            default => 'Status recorded.',
        };
    }
}
