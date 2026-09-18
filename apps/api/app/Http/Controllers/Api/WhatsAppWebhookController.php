<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppGateway;
use App\Services\WhatsAppJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppJourneyService $journeys,
        private readonly WhatsAppGateway $gateway,
    ) {}

    public function verify(Request $request)
    {
        $verifyToken = (string) config('services.whatsapp.verify_token');
        if ($verifyToken !== ''
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))
            && $request->query('hub_mode') === 'subscribe') {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->validSignature($request)) {
            return response()->json(['ok' => false, 'message' => 'Invalid webhook signature.'], 401);
        }

        $processed = 0;
        foreach ($this->extractMessages($request->all()) as $message) {
            try {
                if ($message['type'] === 'image') {
                    $media = $this->gateway->downloadMedia($message['media_id']);
                    $result = $this->journeys->handleImage(
                        $message['phone'],
                        $media['bytes'],
                        $media['mime_type'],
                        $message['provider_message_id'],
                    );
                } else {
                    $result = $this->journeys->handle(
                        $message['phone'],
                        $message['body'],
                        $message['provider_message_id'],
                    );
                }

                $this->gateway->sendText($message['phone'], (string) $result['reply']);
                $processed++;
            } catch (\Throwable $e) {
                Log::error('WhatsApp journey processing failed.', [
                    'provider_message_id' => $message['provider_message_id'],
                    'type' => $message['type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true, 'processed' => $processed]);
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');
        if ($secret === '') {
            return ! app()->environment('production');
        }

        $provided = (string) $request->header('X-Hub-Signature-256');
        if (! str_starts_with($provided, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    private function extractMessages(array $payload): array
    {
        $messages = [];
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $phone = (string) ($message['from'] ?? '');
                    $id = (string) ($message['id'] ?? '');
                    $type = (string) ($message['type'] ?? '');
                    if ($phone === '' || $id === '') {
                        continue;
                    }

                    if ($type === 'text') {
                        $body = (string) ($message['text']['body'] ?? '');
                        if ($body !== '') {
                            $messages[] = [
                                'type' => 'text',
                                'phone' => $phone,
                                'body' => $body,
                                'media_id' => null,
                                'provider_message_id' => $id,
                            ];
                        }
                    }

                    if ($type === 'image') {
                        $mediaId = (string) ($message['image']['id'] ?? '');
                        if ($mediaId !== '') {
                            $messages[] = [
                                'type' => 'image',
                                'phone' => $phone,
                                'body' => '',
                                'media_id' => $mediaId,
                                'provider_message_id' => $id,
                            ];
                        }
                    }
                }
            }
        }

        return $messages;
    }
}
