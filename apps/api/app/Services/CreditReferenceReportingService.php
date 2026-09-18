<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class CreditReferenceReportingService
{
    public function queueLoanEvent(Loan $loan, string $eventType): object
    {
        $loan->loadMissing(['user', 'creditOffer']);
        $payload = $this->buildPayload($loan, $eventType);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $hash = hash('sha256', $canonical);
        $negative = in_array($eventType, ['delinquency', 'non_performing', 'write_off'], true);

        $complete = (bool) data_get($payload, 'quality_controls.complete_customer_identity')
            && (bool) data_get($payload, 'quality_controls.identity_verified');

        DB::table('credit_information_reports')->updateOrInsert(
            [
                'loan_id' => $loan->id,
                'event_type' => $eventType,
                'payload_hash' => $hash,
            ],
            [
                'user_id' => $loan->user_id,
                'report_type' => $negative ? 'negative' : 'positive',
                'status' => $complete ? 'pending' : 'blocked_data_quality',
                'provider' => config('services.credit_reference_reporting.provider'),
                'payload' => $canonical,
                'due_at' => now()->addDays((int) config('opfin.regulatory.credit_reporting_due_days', 30)),
                'failure_reason' => $complete ? null : 'Complete verified customer identity is required before external credit reporting.',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return DB::table('credit_information_reports')
            ->where('loan_id', $loan->id)
            ->where('event_type', $eventType)
            ->where('payload_hash', $hash)
            ->first();
    }

    public function submitPending(int $limit = 100): array
    {
        $url = trim((string) config('services.credit_reference_reporting.url'));
        $token = trim((string) config('services.credit_reference_reporting.token'));
        $provider = trim((string) config('services.credit_reference_reporting.provider', 'configured_credit_reference'));

        if ($url === '') {
            return ['submitted' => 0, 'failed' => 0, 'pending' => DB::table('credit_information_reports')->where('status', 'pending')->count(), 'provider_configured' => false];
        }

        $submitted = 0;
        $failed = 0;
        $records = DB::table('credit_information_reports')
            ->whereIn('status', ['pending', 'failed', 'blocked_consent'])
            ->where('attempts', '<', 10)
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        foreach ($records as $record) {
            $consent = ConsentRecord::query()
                ->where('user_id', $record->user_id)
                ->where('purpose', ConsentRecord::PURPOSE_CREDIT_INFORMATION_REPORTING)
                ->where('status', ConsentRecord::STATUS_GRANTED)
                ->exists();

            if (! $consent) {
                DB::table('credit_information_reports')->where('id', $record->id)->update([
                    'status' => 'blocked_consent',
                    'failure_reason' => 'Active customer consent for credit-information reporting is required before external submission.',
                    'updated_at' => now(),
                ]);

                continue;
            }

            try {
                $request = Http::acceptJson()
                    ->timeout(15)
                    ->withHeaders(['Idempotency-Key' => 'opfin-credit-report-'.$record->id.'-'.$record->payload_hash]);

                if ($token !== '') {
                    $request = $request->withToken($token);
                }

                $response = $request->post($url, json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR));
                $response->throw();

                DB::table('credit_information_reports')->where('id', $record->id)->update([
                    'status' => 'submitted',
                    'provider' => $provider,
                    'provider_reference' => $response->json('reference') ?? $response->json('id'),
                    'submitted_at' => now(),
                    'attempts' => $record->attempts + 1,
                    'failure_reason' => null,
                    'updated_at' => now(),
                ]);
                $submitted++;
            } catch (Throwable $exception) {
                report($exception);
                DB::table('credit_information_reports')->where('id', $record->id)->update([
                    'status' => 'failed',
                    'provider' => $provider,
                    'attempts' => $record->attempts + 1,
                    'failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        return [
            'submitted' => $submitted,
            'failed' => $failed,
            'pending' => DB::table('credit_information_reports')->whereIn('status', ['pending', 'failed', 'blocked_consent'])->count(),
            'provider_configured' => true,
        ];
    }

    private function buildPayload(Loan $loan, string $eventType): array
    {
        $user = $loan->user ?? User::withoutGlobalScopes()->findOrFail($loan->user_id);
        $schedule = CreditRepaymentScheduleItem::query()->where('loan_id', $loan->id);
        $outstanding = (int) (clone $schedule)->sum('total_outstanding_minor');
        $amountDue = (int) (clone $schedule)->where('due_date', '<=', now()->toDateString())->sum('total_outstanding_minor');
        $oldestUnpaid = (clone $schedule)->where('total_outstanding_minor', '>', 0)->orderBy('due_date')->value('due_date');

        return [
            'schema_version' => 'opfin-umra-credit-information-v1',
            'event_type' => $eventType,
            'reported_at' => now()->toIso8601String(),
            'customer' => [
                'customer_reference' => (string) $user->id,
                'first_name' => $user->first_name,
                'other_name' => $user->other_name,
                'last_name' => $user->last_name,
                'phone' => $user->phone,
                'national_id' => $user->national_id,
            ],
            'facility' => [
                'loan_reference' => (string) $loan->id,
                'product_id' => $loan->loan_product_id,
                'principal_minor' => (int) $loan->amount,
                'currency' => (string) ($loan->creditOffer?->currency ?? 'UGX'),
                'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
                'duration_days' => (int) $loan->duration,
                'status' => $loan->status,
                'outstanding_minor' => $outstanding,
                'amount_due_minor' => $amountDue,
                'oldest_unpaid_due_date' => $oldestUnpaid,
                'non_performing_at' => $loan->non_performing_at?->toIso8601String(),
            ],
            'quality_controls' => [
                'identity_verified' => $user->nin_status === 'VALID',
                'complete_customer_identity' => filled($user->first_name) && filled($user->last_name) && filled($user->phone) && filled($user->national_id),
                'source_system' => 'OpFin',
                'payload_hash_algorithm' => 'sha256',
            ],
        ];
    }
}
