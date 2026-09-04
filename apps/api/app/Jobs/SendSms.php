<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $smsMessage;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(SmsMessage $smsMessage)
    {
        $this->smsMessage = $smsMessage;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SmsService $smsService)
    {
        $response = $smsService->sendSms($this->smsMessage->to, $this->smsMessage->message);
        $this->smsMessage->status = $response['success'] ? 'Sent' : 'Failed';
        $this->smsMessage->save();
    }
}
