<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EfrisService
{
    public function createForRevenue(int $revenueEventId, array $taxQuote): ?object
    {
        if (! ($taxQuote['invoice_required'] ?? false)) {
            return null;
        }

        $event = DB::table('revenue_events')->where('id', $revenueEventId)->first();
        if (! $event) {
            throw new InvalidArgumentException('Revenue event not found for EFRIS document.');
        }

        $payload = [
            'document_reference' => 'EFR-'.Str::upper(Str::random(18)),
            'source' => 'opfin_revenue_event',
            'source_reference' => $event->public_id,
            'event_type' => $event->event_type,
            'amount_minor' => (int) $event->gross_amount_minor,
            'tax_amount_minor' => (int) $event->tax_amount_minor,
            'currency' => $event->currency,
            'occurred_at' => $event->occurred_at,
            'tax_policy' => $taxQuote['policy'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existing = DB::table('efris_documents')->where('revenue_event_id', $revenueEventId)->first();
        if ($existing) {
            return $existing;
        }

        $taxEvent = DB::table('tax_events')->where('revenue_event_id', $revenueEventId)->latest('id')->first();
        $id = DB::table('efris_documents')->insertGetId([
            'document_reference' => $payload['document_reference'],
            'tax_event_id' => $taxEvent?->id,
            'revenue_event_id' => $revenueEventId,
            'document_type' => $taxQuote['efris_document_type'] ?? 'invoice',
            'status' => config('opfin.accounting.efris_enabled', false) ? 'pending_submission' : 'pending_configuration',
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'payload_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('efris_documents')->find($id);
    }

    public function submitPending(): array
    {
        if (! config('opfin.accounting.efris_enabled', false)) {
            return ['submitted' => 0, 'failed' => 0, 'status' => 'disabled'];
        }

        $url = trim((string) config('services.efris.url'));
        $token = trim((string) config('services.efris.token'));
        $tin = trim((string) config('services.efris.tin'));
        if ($url === '' || $token === '' || $tin === '') {
            throw new InvalidArgumentException('EFRIS submission is enabled but production endpoint, token or TIN is not configured.');
        }

        $submitted = 0;
        $failed = 0;
        $documents = DB::table('efris_documents')->whereIn('status', ['pending_submission', 'failed'])->orderBy('id')->limit(100)->get();
        foreach ($documents as $document) {
            $payload = json_decode((string) $document->payload, true, 512, JSON_THROW_ON_ERROR);
            try {
                $response = Http::withToken($token)->acceptJson()->post($url, [
                    'tin' => $tin,
                    'document' => $payload,
                ]);
                if (! $response->successful()) {
                    throw new \RuntimeException('EFRIS HTTP '.$response->status());
                }
                $providerReference = (string) ($response->json('reference') ?? $response->json('id') ?? '');
                DB::table('efris_documents')->where('id', $document->id)->update([
                    'status' => 'submitted',
                    'provider_reference' => $providerReference !== '' ? $providerReference : null,
                    'submitted_at' => now(),
                    'failure_reason' => null,
                    'updated_at' => now(),
                ]);
                $submitted++;
            } catch (\Throwable $exception) {
                DB::table('efris_documents')->where('id', $document->id)->update([
                    'status' => 'failed',
                    'failure_reason' => $exception->getMessage(),
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        return compact('submitted', 'failed');
    }
}
