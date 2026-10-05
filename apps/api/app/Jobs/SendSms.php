<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSms implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $smsMessage;

    /**
     * The deliverable text. The stored SmsMessage may hold a redacted copy, so
     * the real content only exists in this encrypted job payload. Jobs queued
     * before this property existed fall back to the stored message.
     */
    protected ?string $content;

    public function __construct(SmsMessage $smsMessage, ?string $content = null)
    {
        $this->smsMessage = $smsMessage;
        $this->content = $content;
    }

    public function content(): string
    {
        return $this->content ?? $this->smsMessage->message;
    }

    public function handle(SmsService $smsService)
    {
        $response = $smsService->sendSms($this->smsMessage->to, $this->content());
        $this->smsMessage->status = $response['success'] ? 'Sent' : 'Failed';
        $this->smsMessage->save();
    }
}
