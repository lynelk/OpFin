<?php

namespace App\Services;

use App\Models\EssentialsAccount;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsBiller;
use App\Models\EssentialsRepayment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CpayEssentialsClient
{
    public function lookup(EssentialsAccount $account): array
    {
        $path = $this->path('bill_lookup_path');
        $biller = EssentialsBiller::query()->findOrFail($account->biller_id);
        return $this->sendSigned($path, [
            'merchantNumber' => $this->merchantNumber(),
            'country' => strtoupper((string) config('services.cpay.country', 'UG')),
            'currency' => strtoupper((string) config('services.cpay.currency', 'UGX')),
            'billerCode' => $biller->code, 'accountReference' => $account->account_reference,
            'customerReference' => 'user:'.$account->user_id, 'requestReference' => 'ESS-LOOKUP-'.$account->public_id,
        ]);
    }

    public function payBill(EssentialsAdvance $advance, EssentialsAccount $account, EssentialsBiller $biller): array
    {
        $path = $this->path($biller->route === 'manual_verification' ? 'beneficiary_payment_path' : 'bill_payment_path');
        $metadata = (array) $account->metadata;
        return $this->sendSigned($path, [
            'merchantNumber' => $this->merchantNumber(), 'country' => strtoupper((string) config('services.cpay.country', 'UG')),
            'currency' => $advance->currency, 'billerCode' => $biller->code, 'accountReference' => $account->account_reference,
            'beneficiaryName' => $metadata['beneficiary_name'] ?? null,
            'beneficiaryChannel' => $metadata['beneficiary_channel'] ?? null, 'landlordName' => $metadata['landlord_name'] ?? null,
            'amountMinor' => $advance->principal_minor, 'requestReference' => $advance->reference,
            'lenderFundingReference' => $advance->lender_funding_reference, 'purpose' => 'opfin_essentials_finance',
            'metadata' => ['opfinAdvanceReference' => $advance->reference, 'lenderPartnerId' => $advance->lender_partner_id,
                'partnerProductId' => $advance->partner_product_id, 'financialSpaceId' => $advance->financial_space_id],
        ]);
    }

    public function collectRepayment(EssentialsAdvance $advance, EssentialsRepayment $repayment,
        string $payerMsisdn, ?string $payerProvider = null): array
    {
        return $this->sendSigned($this->path('lender_repayment_path'), [
            'merchantNumber' => $this->merchantNumber(), 'country' => strtoupper((string) config('services.cpay.country', 'UG')),
            'currency' => $advance->currency, 'amountMinor' => $repayment->amount_minor,
            'payerMsisdn' => $payerMsisdn, 'payerProvider' => $payerProvider,
            'requestReference' => $repayment->reference, 'purpose' => 'opfin_essentials_repayment',
            'beneficiaryReference' => $advance->lender_contract_reference ?: $advance->reference,
            'metadata' => ['opfinAdvanceReference' => $advance->reference, 'lenderPartnerId' => $advance->lender_partner_id,
                'partnerProductId' => $advance->partner_product_id],
        ]);
    }

    public function status(string $reference, string $referenceType = 'provider'): array
    {
        return $this->sendSigned($this->path('transaction_status_path'), [
            'merchantNumber' => $this->merchantNumber(), 'reference' => $reference,
            'referenceType' => $referenceType === 'internal' ? 'REQUEST_REFERENCE' : 'PROVIDER_REFERENCE',
        ]);
    }

    public function configuredForBiller(EssentialsBiller $biller): bool
    {
        return $this->configured($biller->route === 'manual_verification' ? 'beneficiary_payment_path' : 'bill_payment_path');
    }

    public function configured(string $capability): bool
    {
        return trim((string) config('services.cpay.base_url')) !== ''
            && trim((string) config('services.cpay.merchant_number')) !== ''
            && trim((string) config('services.cpay.private_key')) !== ''
            && trim((string) config('services.cpay.'.$capability)) !== '';
    }

    private function path(string $key): string
    {
        if (! $this->configured($key)) {
            throw new RuntimeException('The required CPay Essentials capability is not configured.');
        }
        $path = '/'.ltrim(trim((string) config('services.cpay.'.$key)), '/');
        if (str_contains($path, '://') || str_contains($path, '..') || strpbrk($path, "?#\r\n") !== false) {
            throw new RuntimeException('The configured CPay capability path is invalid.');
        }
        return $path;
    }

    private function sendSigned(string $path, array $payload): array
    {
        $base = rtrim((string) config('services.cpay.base_url'), '/');
        $parts = parse_url($base);
        if (! is_array($parts) || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || (app()->environment('production') && ($parts['scheme'] ?? '') !== 'https')) {
            throw new RuntimeException('The configured CPay origin is not valid for this environment.');
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['POST', $path, '', $timestamp, $nonce, hash('sha256', $body)]);
        $response = Http::withoutRedirecting()->withHeaders([
            'Accept' => 'application/json', 'Content-Type' => 'application/json',
            'X-CPay-Merchant-Number' => $this->merchantNumber(), 'X-CPay-Signature-Version' => 'v2',
            'X-CPay-Timestamp' => $timestamp, 'X-CPay-Nonce' => $nonce, 'X-CPay-Signature' => $this->sign($canonical),
            'X-CPay-Environment' => strtoupper((string) config('services.cpay.environment', 'sandbox')),
        ])->connectTimeout(10)->timeout((int) config('services.cpay.timeout_seconds', 30))
            ->withBody($body, 'application/json')->send('POST', $base.$path);
        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('CPay Essentials did not return a successful response (HTTP '.$response->status().').');
        }
        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('CPay Essentials response was not valid JSON.');
        }
        return $result;
    }

    private function sign(string $canonical): string
    {
        $privateKeyValue = str_replace('\\n', "\n", (string) config('services.cpay.private_key'));
        $privateKey = openssl_pkey_get_private($privateKeyValue);
        if ($privateKey === false) {
            throw new RuntimeException('CPay private key is invalid or unreadable.');
        }
        if (! openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign CPay Essentials request.');
        }
        return base64_encode($signature);
    }

    private function merchantNumber(): string
    {
        return (string) config('services.cpay.merchant_number');
    }
}
