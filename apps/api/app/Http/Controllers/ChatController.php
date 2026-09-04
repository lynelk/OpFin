<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Services\RAG\PromptBuilder;
use App\Services\RAG\RAGService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenAI;

class ChatController extends Controller
{
    protected $userId;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->userId = Auth::id();
            return $next($request);
        });
    }
    public function index()
    {
        $chats = Chat::where('user_id', $this->userId)
            ->latest()
            ->get();
        return view('chats.index', compact('chats'));
    }

    public function store()
    {
        return Chat::create([
            'user_id' => $this->userId,
            'title' => 'New chat',
        ]);
    }

    public function show(Chat $chat)
    {
        abort_unless($chat->user_id === $this->userId, 403);

        return $chat->load('messages');
    }

    public function destroy(Chat $chat)
    {
        abort_unless($chat->user_id === $this->userId, 403);
        $chat->delete();

        return response()->noContent();
    }

    public function stream(Chat $chat, Request $request)
    {
        abort_unless($chat->user_id === $this->userId, 403);
        $question = $request->query('question');
        ChatMessage::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $question,
        ]);
        $response = response()->stream(function () use ($chat, $question) {
            $client = OpenAI::client(config('services.openai_api_key'));
            $history = $chat->messages()->latest()->take(8)->get()->reverse();
            Log::info('Chat history for RAG context', ['history' => $history->pluck('content')]);
            $context = app(RAGService::class)->retrieve($question);
            Log::info('RAG retrieved context', ['context' => $context]);
            $prompt = app(PromptBuilder::class)->build($context, $history->all(), $question);
            Log::info('Constructed prompt for LLM', ['prompt' => $prompt]);
            $stream = $client->chat()->createStreamed([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                ],
            ]);
            Log::info('Started streaming response from LLM');
            $assistantContent = '';
            foreach ($stream as $event) {
                if (isset($event['choices'][0]['delta']['content'])) {
                    $assistantContent .= $event['choices'][0]['delta']['content'];
                    echo "data: " . json_encode([
                        'delta' => $event['choices'][0]['delta']['content'],
                        'sources' => $context['sources'] ?? [] // if your RAG returns sources
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            ChatMessage::create([
                'chat_id' => $chat->id,
                'role' => 'assistant',
                'content' => $assistantContent,
            ]);
            Log::info('Completed streaming response from LLM and saved assistant message', ['assistantContent' => $assistantContent]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
        return $response;
    }
}
