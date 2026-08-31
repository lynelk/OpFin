<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppGateway
{
    public function sendText(string $phone, string $message): void
    {
        $baseUrl = rtrim((string) config('services.whatsapp.base_url'), '/');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');

        if ($baseUrl === '' || $phoneNumberId === '' || $token === '') {
            throw new \RuntimeException('WhatsApp provider is not configured.');
        }

        Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 250)
            ->post("{$baseUrl}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $message],
            ])
            ->throw();
    }
}
