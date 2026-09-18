<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CreditReferenceSubmission;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\CustomerPhoneNumber;
use App\Models\Loan;
use Illuminate\Support\Facades\Http;
use Throwable;

class CreditReferenceReportingService
{
    public function stageLoan(Loan $loan, string $eventType, ?string $sourceReference = null): CreditReferenceSubmission
    {
        $loan->loadMissing(['user', 'creditOffer']);
        $payload = $this->payload($loan, $eventType);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $payloadHash = hash('sha256', $canonical);
        $dedupeSource = $sourceReference ?: now()->toDateString();
        $dedupeKey = hash('sha256', implode('|', [$loan->id, $eventType, $dedupeSource, $payloadHash]));
        $errors = $this->validatePayload($payload);

        return CreditReferenceSubmission::updateOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'user_id' => $loan->user_id,
                'loan_id' => $loan->id,
                'credit_offer_id' => $loan->credit_offer_id,
                'event_type' => $eventType,
                'information_type' => $payload['credit']['information_type'],
                'reporting_date' => now()->toDateString(),
                'status' => $errors === [] ? CreditReferenceSubmission::STATUS_PENDING : CreditReferenceSubmission::STATUS_FAILED,
                'payload' => $payload,
                'payload_hash' => $payloadHash,
                'due_at' => now(),
                'error_message' => $errors === [] ? null : implode('; ', $errors),
            ],
        );
    }

    public function stagePortfolioSnapshot(): int
    {
        $count = 0;
        Loan::withoutGlobalScopes()
            ->whereNotNull('disbursed_at')
            ->whereNotIn('status', ['Reversed', 'Cancelled', 'Rejected'])
            ->orderBy('id')
            ->chunkById(200, function ($loans) use (&$count) {
                foreach ($loans as $loan) {
                    $this->stageLoan($loan, 'daily_performance', now()->toDateString());
                    $count++;
                }
            });

        return $count;
    }

    public function submitDue(int $limit = 200): array
    {
        $result = ['submitted' => 0, 'failed' => 0, 'skipped' => 0];

        $submissions = CreditReferenceSubmission::query()
            ->whereIn('status', [CreditReferenceSubmission::STATUS_PENDING, CreditReferenceSubmission::STATUS_FAILED])
            ->where('retry_count', '<', (int) config('services.crb.reporting_max_retries', 10))
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        foreach ($submissions as $submission) {
            if ($submission->error_message && str_starts_with($submission->error_message, 'Incomplete credit-reference payload')) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->submit($submission);
                $result['submitted']++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
        }

        return $result;
    }

    public function submit(CreditReferenceSubmission $submission): CreditReferenceSubmission
    {
        $consent = ConsentRecord::query()
            ->where('user_id', $submission->user_id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_REPORTING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            $this->markFailed($submission, 'Outbound credit-information reporting consent is not active.');
            throw new \RuntimeException('Outbound credit-information reporting consent is not active.');
        }

        $url = trim((string) config('services.crb.reporting_url'));
        $token = trim((string) config('services.crb.reporting_token'));

        if ($url === '') {
            $this->markFailed($submission, 'CRB outbound reporting provider is not configured.');
            throw new \RuntimeException('CRB outbound reporting provider is not configured.');
        }

        try {
            $request = Http::acceptJson()
                ->timeout((int) config('services.crb.reporting_timeout_seconds', 15))
                ->withHeaders(['Idempotency-Key' => $submission->dedupe_key]);

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, $submission->payload);
            $response->throw();

            $submission->update([
                'status' => CreditReferenceSubmission::STATUS_SUBMITTED,
                'provider_reference' => $response->json('reference') ?? $response->json('id'),
                'submitted_at' => now(),
                'last_attempt_at' => now(),
                'retry_count' => $submission->retry_count + 1,
                'error_message' => null,
            ]);

            return $submission->fresh();
        } catch (Throwable $exception) {
            $this->markFailed($submission, $exception->getMessage());
            throw $exception;
        }
    }

    private function payload(Loan $loan, string $eventType): array
    {
        $user = $loan->user;
        $phones = CustomerPhoneNumber::query()
            ->where('user_id', $loan->user_id)
            ->whereNotNull('verified_at')
            ->orderByRaw("CASE WHEN kind = 'primary' THEN 0 ELSE 1 END")
            ->pluck('phone')
            ->values()
            ->all();

        if ($phones === []) {
            $phones = array_values(array_filter([(string) $user?->phone]));
        }

        $schedule = $loan->credit_offer_id
            ? CreditRepaymentScheduleItem::query()->where('loan_id', $loan->id)->get()
            : collect();

        $principalOutstanding = $loan->credit_offer_id
            ? (int) $schedule->sum('principal_outstanding_minor')
            : (int) round((float) $loan->schedules()->sum('principal_outstanding'));
        $interestOutstanding = $loan->credit_offer_id
            ? (int) $schedule->sum('interest_outstanding_minor')
            : (int) round((float) $loan->schedules()->sum('interest_outstanding'));
        $feesOutstanding = $loan->credit_offer_id
            ? (int) $schedule->sum('fees_outstanding_minor')
            : 0;
        $totalOutstanding = $principalOutstanding + $interestOutstanding + $feesOutstanding;

        $oldestPastDue = $loan->credit_offer_id
            ? $schedule->filter(fn ($item) => $item->total_outstanding_minor > 0 && $item->due_date->isBefore(today()))->sortBy('due_date')->first()
            : $loan->schedules()->where('total_outstanding', '>', 0)->whereDate('due_date', '<', today())->orderBy('due_date')->first();

        $daysPastDue = $oldestPastDue
            ? max(1, \Illuminate\Support\Carbon::parse($oldestPastDue->due_date)->diffInDays(today()))
            : 0;

        $informationType = $daysPastDue > 0 || in_array(strtolower((string) $loan->status), ['defaulted', 'non-performing', 'npl'], true)
            ? 'negative'
            : 'positive';

        $nextDue = $loan->credit_offer_id
            ? $schedule->filter(fn ($item) => $item->total_outstanding_minor > 0)->sortBy('due_date')->first()?->due_date?->toDateString()
            : $loan->schedules()->where('total_outstanding', '>', 0)->orderBy('due_date')->value('due_date');

        return [
            'schema_version' => 'opfin-crb-outbound-v1',
            'event_type' => $eventType,
            'reported_at' => now()->toIso8601String(),
            'customer' => [
                'customer_reference' => (string) $loan->user_id,
                'nin' => $user?->national_id,
                'name' => $user?->name,
                'phones' => $phones,
            ],
            'credit' => [
                'account_reference' => 'OPF-LOAN-'.$loan->id,
                'loan_id' => $loan->id,
                'credit_offer_reference' => $loan->creditOffer?->offer_reference,
                'currency' => 'UGX',
                'original_principal_minor' => (int) $loan->amount,
                'principal_outstanding_minor' => $principalOutstanding,
                'interest_outstanding_minor' => $interestOutstanding,
                'fees_outstanding_minor' => $feesOutstanding,
                'total_outstanding_minor' => $totalOutstanding,
                'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
                'duration_days' => (int) $loan->duration,
                'next_due_date' => $nextDue,
                'days_past_due' => $daysPastDue,
                'status' => (string) $loan->status,
                'information_type' => $informationType,
            ],
        ];
    }

    private function validatePayload(array $payload): array
    {
        $required = [
            'customer.nin',
            'customer.name',
            'customer.phones.0',
            'credit.account_reference',
            'credit.original_principal_minor',
            'credit.disbursed_at',
            'credit.status',
            'credit.information_type',
        ];

        $missing = [];
        foreach ($required as $key) {
            $value = data_get($payload, $key);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }

        return $missing === [] ? [] : ['Incomplete credit-reference payload: '.implode(', ', $missing)];
    }

    private function markFailed(CreditReferenceSubmission $submission, string $error): void
    {
        $submission->update([
            'status' => CreditReferenceSubmission::STATUS_FAILED,
            'last_attempt_at' => now(),
            'retry_count' => $submission->retry_count + 1,
            'due_at' => now()->addMinutes(min(1440, 5 * (2 ** min($submission->retry_count, 8)))),
            'error_message' => mb_substr($error, 0, 2000),
        ]);
    }
}
