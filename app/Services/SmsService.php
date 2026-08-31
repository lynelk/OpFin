<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Models\SmsMessage;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendSms($recipients, $content)
    {
        try {
            if (config('app.env') === 'local') {
                return [
                    'success' => true,
                    'message' => 'Message simulated locally.',
                ];
            }

            $smsGateway = strtoupper(trim((string) config('services.sms_gateway')));
            if ($smsGateway === '') {
                throw new Exception('No SMS gateway configured.');
            }

            return match ($smsGateway) {
                'CPAY' => $this->cpay($recipients, $content),
                'YO' => $this->yo($recipients, $content),
                default => throw new Exception('Unsupported SMS gateway configured.'),
            };
        } catch (Exception $exception) {
            Log::error('SMS sending failed', [
                'error' => $exception->getMessage(),
                'recipients' => $recipients,
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => 500,
            ];
        }
    }

    public function yo($recipients, $content)
    {
        $url = config('services.yo.base_url');
        $account = config('services.yo.account');
        $password = config('services.yo.password');
        if (! $url || ! $account || ! $password) {
            throw new Exception('YO SMS gateway configuration is incomplete.');
        }

        $response = Http::get($url, [
            'ybsacctno' => $account,
            'password' => $password,
            'sms_content' => $content,
            'destinations' => $recipients,
        ])->body();

        if (str_contains($response, 'ybs_autocreate_status=OK')) {
            return [
                'success' => true,
                'message' => 'SMS submitted successfully',
                'raw' => $response,
            ];
        }

        return [
            'success' => false,
            'message' => 'SMS sending failed',
            'raw' => $response,
        ];
    }

    public function cpay($recipients, $content)
    {
        $baseUrl = rtrim((string) config('services.cpay.base_url'), '/');
        $smsPath = trim((string) config('services.cpay.sms_path'));
        $merchantNumber = trim((string) config('services.cpay.merchant_number'));
        $privateKey = (string) config('services.cpay.private_key');
        $privateKey = str_replace('\\n', "\n", $privateKey);

        if ($baseUrl === '' || $smsPath === '' || $merchantNumber === '' || trim($privateKey) === '') {
            throw new Exception('CPay SMS configuration is incomplete. Configure base URL, merchant number, private key and the certified SMS path.');
        }

        $signatureData = $merchantNumber.$content.$recipients;
        if (! openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Unable to sign CPay SMS request.');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($baseUrl.'/'.ltrim($smsPath, '/'), [
            'merchant_number' => $merchantNumber,
            'recipients' => $recipients,
            'signature' => base64_encode($signature),
            'content' => $content,
        ]);

        if (! $response->successful()) {
            throw new Exception('CPay SMS request failed with status '.$response->status().'.');
        }

        $data = $response->json();
        if (($data['state'] ?? null) !== 'OK') {
            throw new Exception('CPay SMS gateway rejected the request: '.($data['message'] ?? 'unknown error'));
        }

        return [
            'success' => true,
            'message' => $data['message'] ?? 'SMS submitted successfully',
            'code' => $data['code'] ?? 200,
        ];
    }

    public function queueSms($to, $message)
    {
        $smsMessage = SmsMessage::create([
            'to' => $to,
            'message' => $message,
            'status' => 'Pending',
        ]);

        SendSms::dispatch($smsMessage);
    }
}
