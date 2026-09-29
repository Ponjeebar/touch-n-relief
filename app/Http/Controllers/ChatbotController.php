<?php

namespace App\Http\Controllers;

use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function session(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function reply(Request $request, ChatbotService $chatbot): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:500']]);
        $identity = $request->user()?->id ? 'user:'.$request->user()->id : 'guest';
        $stored = $request->session()->get('chatbot.context', []);
        $context = is_array($stored) && ($stored['identity'] ?? null) === $identity ? $stored : [];
        $result = $chatbot->answer($validated['message'], $request->user(), $context);

        $nextContext = is_array($result['context'] ?? null) ? $result['context'] : [];
        $request->session()->put('chatbot.context', [...$nextContext, 'identity' => $identity]);
        unset($result['context']);

        return response()->json($result);
    }
}
