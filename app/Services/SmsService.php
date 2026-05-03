<?php

namespace App\Services;

use App\Models\SmsMessage;
use App\Jobs\SendSms;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendSms($recipients, $content)
    {
        try {
            $env = config('app.env');
            if ($env == 'local') {
                return [
                    'success' => true,
                    'message' => 'Message sent successfully through CPAY || YO',
                ];
            }
            $smsGateway = config('services.sms_gateway');
            if (!$smsGateway) {
                throw new Exception('No sms gateway configured');
            }
            if ($smsGateway == 'CPAY') {
                return $this->cpay($recipients, $content);
            }
            if ($smsGateway == 'YO') {
                return $this->yo($recipients, $content);
            }
        } catch (Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500
            ];
        }
    }

    public function yo($recipients, $content)
    {
        $url = config('services.yo.base_url');

        $params = [
            'ybsacctno'   => config('services.yo.account'),
            'password'    => config('services.yo.password'),
            'sms_content' => $content,
            'destinations' => $recipients,
        ];

        // Send GET request
        $response = Http::get($url, $params);

        // Parse response
        $response = $response->body();

        if (str_contains($response, 'ybs_autocreate_status=OK')) {
            return [
                'success' => true,
                'message' => 'SMS submitted successfully',
                'raw'     => $response
            ];
        }
        return [
            'success' => false,
            'message' => 'SMS sending failed',
            'raw'     => $response
        ];
    }

    public function cpay($recipients, $content)
    {
        $merchantNumber = config('services.cpay.account');
        // Concatenate the fields in the specified order
        $signatureData = $merchantNumber . $content . $recipients;

        // Load the private key (store this securely in your .env or config)
        $privateKey = config('services.cpay.password');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        $base64Signature = base64_encode($signature);

        $signature = $base64Signature;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(config('services.cpay.base_url') . '/doSendSms', [
                'merchant_number' => $merchantNumber,
                'recipients' => $recipients,
                'signature' => $signature,
                'content' => $content,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['state'] === 'OK') {
                    return [
                        'success' => true,
                        'message' => $data['message'],
                        'code' => $data['code']
                    ];
                }

                throw new Exception('Api returned an error: ' . $data['message']);
            }

            throw new Exception('API request failed with status: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients
            ]);

            return [
                'success' => false,
                'message' => 'Error making API request: ' . $e->getMessage(),
                'code' => 500
            ];
        }
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
