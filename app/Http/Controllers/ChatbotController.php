<?php

namespace App\Http\Controllers;

use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function reply(Request $request, ChatbotService $chatbot): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:500']]);

        return response()->json($chatbot->answer($validated['message'], $request->user()));
    }
}
