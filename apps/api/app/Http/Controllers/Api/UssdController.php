<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UssdJourneyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UssdController extends Controller
{
    public function __invoke(Request $request, UssdJourneyService $journey): Response
    {
        $sessionId = (string) ($request->input('sessionId') ?? $request->input('session_id') ?? '');
        $phone = (string) ($request->input('phoneNumber') ?? $request->input('phone') ?? '');
        $text = (string) ($request->input('text') ?? '');

        $result = $journey->handle($sessionId, $phone, $text);

        return response(($result['action'] ?? 'END').' '.($result['message'] ?? ''), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
