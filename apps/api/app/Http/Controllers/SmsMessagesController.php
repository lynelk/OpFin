<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use Illuminate\Http\Request;

class SmsMessagesController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $smsMessages = SmsMessage::when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10);

        return view('sms-messages.index', compact('smsMessages', 'status'));
    }
}
